#!/usr/bin/env bash
# Sprawdza zip wtyczki: folder główny, obie wersje, vendor, brak plików deweloperskich, autoload.
#
# Użycie:  bin/sprawdz-zip.sh ŚCIEŻKA_ZIPA X.Y.Z
# Wynik: jedna linia; przy błędzie opis problemu na stderr i kod 1.
# Używają go: bin/zbuduj-zip.sh (po budowie, także w CI) i bin/wydaj.sh (zip pobrany z wydania).

set -euo pipefail

SLUG='marcin-rynkowski-narzedzia'

blad() {
	echo "BŁĄD: $*" >&2
	exit 1
}

[ $# -eq 2 ] || blad "użycie: bin/sprawdz-zip.sh ŚCIEŻKA_ZIPA X.Y.Z"
ZIP=$1
WERSJA=$2
[ -f "$ZIP" ] || blad "nie ma pliku $ZIP"
[[ "$WERSJA" =~ ^[0-9]+\.[0-9]+\.[0-9]+$ ]] || blad "wersja musi mieć postać X.Y.Z, podano: '$WERSJA'"

TYMCZASOWY="$(mktemp -d)"
trap 'rm -rf "$TYMCZASOWY"' EXIT

unzip -q "$ZIP" -d "$TYMCZASOWY" || blad "nie da się rozpakować $ZIP"
KORZEN="$TYMCZASOWY/$SLUG"

NAZWY="$(cd "$TYMCZASOWY" && find . -mindepth 1 -maxdepth 1 -printf '%f\n')"
[ "$NAZWY" = "$SLUG" ] || blad "zip ma inny folder główny niż $SLUG: $(echo "$NAZWY" | tr '\n' ' ')"
[ "$(grep -Po '^\s*\*\s*Version:\s*\K\S+' "$KORZEN/$SLUG.php" || true)" = "$WERSJA" ] || blad "zip ma inną wersję w nagłówku niż $WERSJA"
[ "$(grep -Po "const VERSION\s*= '\K[^']+" "$KORZEN/src/class-plugin.php" 2>/dev/null || true)" = "$WERSJA" ] || blad "zip ma inną wersję w klasie Plugin niż $WERSJA"
[ -f "$KORZEN/vendor/autoload.php" ] || blad "w zipie brakuje vendor/autoload.php"

# Pliki deweloperskie poza vendor/ (zależności mają własne README i testy, to ich sprawa).
ZBEDNE="$(cd "$KORZEN" && find . -path ./vendor -prune -o \( -name tests -o -name .github -o -name bin -o -name node_modules \
	-o -name composer.json -o -name composer.lock -o -name package.json -o -name package-lock.json \
	-o -name phpunit.xml -o -name phpcs.xml -o -name phpstan.neon -o -name vitest.config.js -o -name README.md \) -print)"
[ -z "$ZBEDNE" ] || blad "w zipie są pliki deweloperskie: $(echo "$ZBEDNE" | tr '\n' ' ')"

# W paczce produkcyjnej nie ma narzędzi deweloperskich w vendor/.
for pakiet in phpunit brain mockery phpstan squizlabs; do
	[ ! -e "$KORZEN/vendor/$pakiet" ] || blad "w zipie jest zależność deweloperska vendor/$pakiet"
done

# shellcheck disable=SC2016 # zmienne PHP, nie powłoki
php -r '
	define( "ABSPATH", "/" );
	require $argv[1] . "/vendor/autoload.php";
	$klasy = array(
		"Rynkowski\\Narzedzia\\Plugin",
		"Rynkowski\\Narzedzia\\Registry",
		"YahnisElsts\\PluginUpdateChecker\\v5\\PucFactory",
	);
	foreach ( $klasy as $klasa ) {
		if ( ! class_exists( $klasa ) ) {
			fwrite( STDERR, "brak klasy $klasa\n" );
			exit( 1 );
		}
	}
	// Każdy manifest modułu musi zwrócić moduł, czyli jego klasa jest w autoloadzie.
	$manifesty = glob( $argv[1] . "/moduly/*/module.php" );
	if ( ! $manifesty ) {
		fwrite( STDERR, "brak modułów w moduly/\n" );
		exit( 1 );
	}
	foreach ( $manifesty as $manifest ) {
		$modul = require $manifest;
		if ( ! $modul instanceof Rynkowski\Narzedzia\Module ) {
			fwrite( STDERR, "manifest $manifest nie zwraca modułu\n" );
			exit( 1 );
		}
	}
' "$KORZEN" || blad "autoload w zipie nie ładuje wszystkich klas (rdzeń, moduły, PUC)"

echo "Zip w porządku: $ZIP ($WERSJA)"
