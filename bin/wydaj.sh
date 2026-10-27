#!/usr/bin/env bash
# Wydanie wtyczki: podbija wersję, sprawdza kod, wypycha, czeka na CI, taguje i weryfikuje zip.
#
# Użycie:  bin/wydaj.sh X.Y.Z [--proba]
#   --proba  wszystko do lokalnych kontroli włącznie, potem przywraca pliki; nic nie wypycha.
#          bin/wydaj.sh --sprawdz-zip X.Y.Z
#   sprawdza zip z istniejącego wydania (bez zmian w repo).
#
# Stopka commita (np. atrybucja) ze zmiennej WYDANIE_STOPKA, jeśli ustawiona.
# Wynik: jedna linia na krok, szczegóły tylko przy błędzie (ogon dziennika).

set -euo pipefail

SLUG='marcin-rynkowski-narzedzia'
REPO='emRhino/marcin-rynkowski-narzedzia'
PLIK_GLOWNY="$SLUG.php"
PLIK_KLASY='src/class-plugin.php'
LIMIT_CI=900      # sekundy na jeden workflow
LIMIT_STARTU=120  # sekundy na pojawienie się uruchomienia workflow

cd "$(dirname "$0")/.."

DZIENNIK="$(mktemp)"
TYMCZASOWY="$(mktemp -d)"
ZMIENIONE_PLIKI=0
ZACOMMITOWANO=0

sprzataj() {
	local kod=$?
	# Wersja podbita, ale bez commita: przywracamy pliki, żeby nie zostawić połowicznego stanu.
	if [ "$ZMIENIONE_PLIKI" = 1 ] && [ "$ZACOMMITOWANO" = 0 ]; then
		git checkout -- "$PLIK_GLOWNY" "$PLIK_KLASY"
	fi
	rm -rf "$TYMCZASOWY"
	if [ "$kod" != 0 ]; then
		echo "--- ostatnie linie dziennika ($DZIENNIK) ---" >&2
		tail -n 40 "$DZIENNIK" >&2
	else
		rm -f "$DZIENNIK"
	fi
	exit "$kod"
}
trap sprzataj EXIT

blad() {
	echo "BŁĄD: $*" >&2
	exit 1
}

krok() {
	echo "• $*"
}

# Uruchamia polecenie z wyjściem do dziennika.
cicho() {
	echo "\$ $*" >>"$DZIENNIK"
	"$@" >>"$DZIENNIK" 2>&1
}

wersja_naglowka() {
	grep -Po '^\s*\*\s*Version:\s*\K\S+' "$PLIK_GLOWNY"
}

wersja_klasy() {
	grep -Po "const VERSION\s*= '\K[^']+" "$PLIK_KLASY"
}

# Czeka na uruchomienie workflow dla podanego filtra, potem na jego koniec; kończy błędem, gdy nie przeszedł.
czekaj_na_workflow() {
	local workflow=$1 filtr=$2 wartosc=$3 id='' start
	start=$(date +%s)
	while [ -z "$id" ]; do
		id=$(timeout 30 gh run list --repo "$REPO" --workflow "$workflow" "$filtr" "$wartosc" --limit 1 --json databaseId -q '.[0].databaseId' 2>>"$DZIENNIK" || true)
		if [ -z "$id" ]; then
			[ $(($(date +%s) - start)) -lt "$LIMIT_STARTU" ] || blad "workflow $workflow nie wystartował w ${LIMIT_STARTU}s"
			sleep 5
		fi
	done
	timeout "$LIMIT_CI" gh run watch "$id" --repo "$REPO" --exit-status >>"$DZIENNIK" 2>&1 || true
	local wynik
	wynik=$(timeout 30 gh run view "$id" --repo "$REPO" --json conclusion -q .conclusion)
	[ "$wynik" = 'success' ] || blad "workflow $workflow zakończony: ${wynik:-brak wyniku w ${LIMIT_CI}s} (https://github.com/$REPO/actions/runs/$id)"
}

# Pobiera zip z wydania $TAG i sprawdza go tym samym skryptem co CI (bin/sprawdz-zip.sh).
sprawdz_zip() {
	krok "Sprawdzam zip z wydania"
	cicho timeout 120 gh release download "$TAG" --repo "$REPO" --pattern "$SLUG.zip" --dir "$TYMCZASOWY" || blad "nie udało się pobrać $SLUG.zip"
	cicho bin/sprawdz-zip.sh "$TYMCZASOWY/$SLUG.zip" "$WERSJA" || blad "zip z wydania $TAG nie przeszedł kontroli"
}

# --- Argumenty ---

# Tryb samej weryfikacji istniejącego wydania: bin/wydaj.sh --sprawdz-zip X.Y.Z
if [ "${1:-}" = '--sprawdz-zip' ]; then
	WERSJA="${2:-}"
	if [ $# -ne 2 ] || [[ ! "$WERSJA" =~ ^[0-9]+\.[0-9]+\.[0-9]+$ ]]; then
		blad "użycie: bin/wydaj.sh --sprawdz-zip X.Y.Z"
	fi
	TAG="v$WERSJA"
	sprawdz_zip
	echo "Zip $TAG w porządku"
	exit 0
fi

WERSJA="${1:-}"
PROBA=0
[ "${2:-}" = '--proba' ] && PROBA=1
[ $# -le 2 ] || blad "za dużo argumentów. Użycie: bin/wydaj.sh X.Y.Z [--proba]"
[ -z "${2:-}" ] || [ "$PROBA" = 1 ] || blad "nieznana opcja: $2"
[[ "$WERSJA" =~ ^[0-9]+\.[0-9]+\.[0-9]+$ ]] || blad "wersja musi mieć postać X.Y.Z, podano: '${WERSJA}'"
TAG="v$WERSJA"

# --- Warunki wstępne ---

krok "Sprawdzam stan repo"
[ "$(git rev-parse --abbrev-ref HEAD)" = 'main' ] || blad "wydajemy tylko z gałęzi main"
[ -z "$(git status --porcelain)" ] || blad "w repo są niezacommitowane zmiany; najpierw je zacommituj"
timeout 60 git fetch --quiet origin main --tags >>"$DZIENNIK" 2>&1 || blad "nie udało się pobrać stanu z GitHuba"
[ "$(git rev-parse HEAD)" = "$(git rev-parse origin/main)" ] || blad "lokalny main różni się od origin/main"
! git rev-parse -q --verify "refs/tags/$TAG" >/dev/null || blad "tag $TAG już istnieje lokalnie"
[ -z "$(timeout 30 git ls-remote --tags origin "refs/tags/$TAG")" ] || blad "tag $TAG już istnieje na GitHubie"

OBECNA="$(wersja_naglowka)"
[ "$OBECNA" = "$(wersja_klasy)" ] || blad "wersje się rozjechały: nagłówek $OBECNA, klasa $(wersja_klasy)"
[ "$WERSJA" != "$OBECNA" ] || blad "wersja $WERSJA jest już obecną wersją"
[ "$(printf '%s\n%s\n' "$OBECNA" "$WERSJA" | sort -V | tail -n 1)" = "$WERSJA" ] || blad "wersja $WERSJA nie jest wyższa od obecnej $OBECNA"
[ "$PROBA" = 1 ] || timeout 20 gh api user -q .login >/dev/null 2>>"$DZIENNIK" || blad "gh nie jest zalogowane"

# --- Podbicie wersji ---

krok "Podbijam wersję $OBECNA → $WERSJA"
ZMIENIONE_PLIKI=1
OBECNA_WZOR="${OBECNA//./\\.}"  # kropki dosłownie, nie „dowolny znak”
sed -i -E "s/^( \* Version:\s+)$OBECNA_WZOR$/\1$WERSJA/" "$PLIK_GLOWNY"
sed -i -E "s/^(\s*const VERSION\s*= ')$OBECNA_WZOR(';)$/\1$WERSJA\2/" "$PLIK_KLASY"
# Potwierdzenie, że edycje się zapisały, zanim cokolwiek pójdzie dalej.
[ "$(wersja_naglowka)" = "$WERSJA" ] || blad "nie udało się podbić wersji w $PLIK_GLOWNY"
[ "$(wersja_klasy)" = "$WERSJA" ] || blad "nie udało się podbić wersji w $PLIK_KLASY"
[ "$(git diff --numstat | wc -l)" = 2 ] || blad "podbicie zmieniło inne pliki niż oczekiwane"

# --- Lokalne kontrole (te same co w CI) ---

krok "Kontrole lokalne: PHPCS, PHPStan, PHPUnit, Vitest"
cicho vendor/bin/phpcs -q || blad "PHPCS zgłosił problemy"
cicho vendor/bin/phpstan analyse --no-progress --memory-limit=1G || blad "PHPStan zgłosił błędy"
php -d pcov.directory=. vendor/bin/phpunit --coverage-text --only-summary-for-coverage-text >"$TYMCZASOWY/pokrycie.txt" 2>&1 \
	|| { cat "$TYMCZASOWY/pokrycie.txt" >>"$DZIENNIK"; blad "testy PHP nie przeszły"; }
cat "$TYMCZASOWY/pokrycie.txt" >>"$DZIENNIK"
grep -qE '^\s+Lines:\s+100\.00%' "$TYMCZASOWY/pokrycie.txt" || blad "pokrycie linii PHP poniżej 100%"
cicho npx vitest run --coverage || blad "testy JS nie przeszły albo pokrycie poniżej 100%"

if [ "$PROBA" = 1 ]; then
	krok "Próba OK: kontrole przeszły dla $WERSJA; przywracam pliki, nic nie wypycham"
	exit 0
fi

# --- Commit i push ---

krok "Commit i push na main"
{
	echo "Wersja $WERSJA"
	if [ -n "${WYDANIE_STOPKA:-}" ]; then
		echo
		echo "$WYDANIE_STOPKA"
	fi
} >"$TYMCZASOWY/wiadomosc.txt"
cicho git commit --quiet -F "$TYMCZASOWY/wiadomosc.txt" -- "$PLIK_GLOWNY" "$PLIK_KLASY" || blad "commit się nie udał"
ZACOMMITOWANO=1
SHA="$(git rev-parse HEAD)"
cicho timeout 60 git push --quiet origin main || blad "push na main się nie udał (commit $SHA zostaje lokalnie)"
[ "$(timeout 30 git ls-remote origin refs/heads/main | cut -f1)" = "$SHA" ] || blad "GitHub nie pokazuje commita $SHA na main"

# --- CI na main, dopiero potem tag ---

krok "Czekam na Kontrolę na main (do ${LIMIT_CI}s)"
# Gdy Kontrola padnie, commit z wersją zostaje na main bez tagu: popraw błąd i wydaj kolejną wersję.
czekaj_na_workflow kontrola.yml --commit "$SHA"

krok "Tag $TAG i push"
cicho git tag -a "$TAG" -m "$TAG"
cicho timeout 60 git push --quiet origin "$TAG" || blad "push tagu $TAG się nie udał"
[ "$(timeout 30 git ls-remote origin "refs/tags/$TAG^{}" | cut -f1)" = "$SHA" ] || blad "GitHub nie pokazuje tagu $TAG na $SHA"

krok "Czekam na Wydanie (do ${LIMIT_CI}s)"
czekaj_na_workflow release.yml --branch "$TAG"

# --- Weryfikacja zipa z wydania ---

sprawdz_zip

echo "Wydano $TAG: https://github.com/$REPO/releases/tag/$TAG"
