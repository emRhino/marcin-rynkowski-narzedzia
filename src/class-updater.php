<?php
/**
 * Aktualizacje z wydań na GitHubie.
 *
 * @package Rynkowski\Narzedzia
 */

namespace Rynkowski\Narzedzia;

use YahnisElsts\PluginUpdateChecker\v5\PucFactory;

defined( 'ABSPATH' ) || exit;

/**
 * Podpina Plugin Update Checker pod publiczne repo wtyczki.
 */
final class Updater {

	const REPO = 'https://github.com/emRhino/marcin-rynkowski-narzedzia/';

	/**
	 * Callback hooka plugins_loaded.
	 *
	 * Bez parametrów celowo: do_action() bez argumentów przekazuje callbackowi pusty tekst,
	 * a register() przyjmuje tylko fabrykę albo null (v0.4.0–0.5.0: krytyczny błąd).
	 */
	public static function boot(): void {
		self::register();
	}

	/**
	 * Rejestruje sprawdzanie aktualizacji.
	 *
	 * @param callable|null $factory Fabryka sprawdzacza; domyślnie PucFactory (parametr dla testów).
	 */
	public static function register( ?callable $factory = null ): void {
		$factory = $factory ?? array( PucFactory::class, 'buildUpdateChecker' );
		$checker = $factory( self::REPO, Plugin::file(), 'marcin-rynkowski-narzedzia' );

		// Paczka z wydania (zip z GitHub Actions), nie archiwum kodu źródłowego.
		$api = $checker->getVcsApi();
		if ( method_exists( $api, 'enableReleaseAssets' ) ) {
			$api->enableReleaseAssets( '/marcin-rynkowski-narzedzia\.zip$/' );
		}
	}
}
