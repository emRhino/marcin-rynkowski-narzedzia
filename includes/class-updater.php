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
	 * Rejestruje sprawdzanie aktualizacji.
	 *
	 * Działa niezależnie od zależności, żeby poprawka zawsze mogła dotrzeć do sklepu.
	 */
	public static function register(): void {
		if ( ! class_exists( PucFactory::class ) ) {
			return;
		}

		$checker = PucFactory::buildUpdateChecker( self::REPO, FILE, 'marcin-rynkowski-narzedzia' );

		// Paczka z wydania (zip z GitHub Actions), nie archiwum kodu źródłowego.
		$api = $checker->getVcsApi();
		if ( method_exists( $api, 'enableReleaseAssets' ) ) {
			$api->enableReleaseAssets( '/marcin-rynkowski-narzedzia\.zip$/' );
		}
	}
}
