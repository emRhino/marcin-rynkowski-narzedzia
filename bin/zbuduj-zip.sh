#!/usr/bin/env bash
# Buduje zip wtyczki z zacommitowanego stanu (HEAD) i go sprawdza. Ten sam skrypt w CI i lokalnie.
#
# Użycie:  bin/zbuduj-zip.sh [KATALOG_WYJŚCIOWY]   (domyślnie build/)
# Wynik: ścieżka gotowego zipa; przy błędzie opis na stderr i kod 1.
#
# Niezacommitowane zmiany NIE trafiają do zipa. Budowa idzie w osobnym git worktree,
# więc robocze drzewo, indeks i vendor/ projektu zostają nietknięte.

set -euo pipefail

SLUG='marcin-rynkowski-narzedzia'

blad() {
	echo "BŁĄD: $*" >&2
	exit 1
}

[ $# -le 1 ] || blad "użycie: bin/zbuduj-zip.sh [KATALOG_WYJŚCIOWY]"

cd "$(dirname "$0")/.."
PROJEKT="$(pwd)"
WYJSCIE="${1:-build}"
mkdir -p "$WYJSCIE"
WYJSCIE="$(cd "$WYJSCIE" && pwd)"
ZIP="$WYJSCIE/$SLUG.zip"

TYMCZASOWY="$(mktemp -d)"
DRZEWO="$TYMCZASOWY/drzewo"
DZIENNIK="$TYMCZASOWY/dziennik.txt"

sprzataj() {
	local kod=$?
	if [ -d "$DRZEWO" ]; then
		git -C "$PROJEKT" worktree remove --force "$DRZEWO" >/dev/null 2>&1 || true
	fi
	if [ "$kod" != 0 ] && [ -f "$DZIENNIK" ]; then
		echo "--- ostatnie linie dziennika ---" >&2
		tail -n 30 "$DZIENNIK" >&2
	fi
	rm -rf "$TYMCZASOWY"
	exit "$kod"
}
trap sprzataj EXIT

# Osobna kopia HEAD z plikami deweloperskimi (composer.json potrzebny do vendor/).
git worktree add --quiet --detach "$DRZEWO" HEAD >>"$DZIENNIK" 2>&1 || blad "nie udało się utworzyć worktree z HEAD"

WERSJA="$(grep -Po '^\s*\*\s*Version:\s*\K\S+' "$DRZEWO/$SLUG.php" || true)"
[ -n "$WERSJA" ] || blad "nie znaleziono wersji w nagłówku $SLUG.php"

# Zależności produkcyjne bez wtyczek i skryptów Composera; autoload classmap z src/ i moduly/.
(
	cd "$DRZEWO"
	COMPOSER_ALLOW_SUPERUSER=1 timeout 300 composer install --no-dev --optimize-autoloader \
		--no-interaction --no-progress --no-plugins --no-scripts
) >>"$DZIENNIK" 2>&1 || blad "composer install --no-dev się nie udał"

# Pliki wtyczki według .gitattributes (export-ignore wycina testy i narzędzia) + vendor/.
mkdir -p "$TYMCZASOWY/paczka/$SLUG"
git -C "$DRZEWO" archive HEAD | tar -x -C "$TYMCZASOWY/paczka/$SLUG" || blad "git archive się nie udał"
cp -r "$DRZEWO/vendor" "$TYMCZASOWY/paczka/$SLUG/"

rm -f "$ZIP"
(cd "$TYMCZASOWY/paczka" && zip -rq "$ZIP" "$SLUG") || blad "nie udało się spakować zipa"

"$PROJEKT/bin/sprawdz-zip.sh" "$ZIP" "$WERSJA" >>"$DZIENNIK" || blad "zbudowany zip nie przeszedł kontroli"

echo "$ZIP"
