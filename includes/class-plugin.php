<?php
/**
 * Główna klasa wtyczki.
 *
 * @package Rynkowski\Narzedzia
 */

namespace Rynkowski\Narzedzia;

defined( 'ABSPATH' ) || exit;

/**
 * Punkt startowy: sprawdza zależności i podpina moduły.
 */
final class Plugin {

	/**
	 * Uruchamia wtyczkę po załadowaniu pozostałych wtyczek.
	 */
	public static function init(): void {
		load_plugin_textdomain( 'marcin-rynkowski-narzedzia', false, dirname( plugin_basename( FILE ) ) . '/languages' );

		$missing = self::missing_dependencies();
		if ( $missing ) {
			add_action(
				'admin_notices',
				static function () use ( $missing ) {
					self::render_missing_notice( $missing );
				}
			);
			return;
		}

		require_once DIR . 'includes/wstazki/class-ribbons.php';
		require_once DIR . 'includes/wstazki/class-ribbon-admin.php';
		require_once DIR . 'includes/wstazki/class-ribbon-product-tab.php';
		require_once DIR . 'includes/wstazki/class-ribbon-frontend.php';

		Ribbons::register();
		Ribbon_Admin::register();
		Ribbon_Product_Tab::register();
		Ribbon_Frontend::register();
	}

	/**
	 * Zwraca nazwy wymaganych, a nieaktywnych zależności.
	 *
	 * @return string[]
	 */
	private static function missing_dependencies(): array {
		$missing = array();

		if ( ! class_exists( 'WooCommerce' ) ) {
			$missing[] = 'WooCommerce';
		}

		// Stała jest ustawiana zarówno przez darmowy Stackable, jak i Premium.
		if ( ! defined( 'STACKABLE_VERSION' ) ) {
			$missing[] = 'Stackable';
		}

		// Szablon nadrzędny, więc motyw potomny Blocksy też się liczy.
		if ( 'blocksy' !== get_template() ) {
			$missing[] = 'Blocksy';
		}

		return $missing;
	}

	/**
	 * Wyświetla w panelu informację o brakujących zależnościach.
	 *
	 * @param string[] $missing Nazwy brakujących zależności.
	 */
	private static function render_missing_notice( array $missing ): void {
		if ( ! current_user_can( 'activate_plugins' ) ) {
			return;
		}

		printf(
			'<div class="notice notice-error"><p>%s</p></div>',
			esc_html(
				sprintf(
					/* translators: %s: lista brakujących wtyczek lub motywu */
					__( 'Marcin Rynkowski – narzędzia nie działa, bo brakuje aktywnych: %s.', 'marcin-rynkowski-narzedzia' ),
					implode( ', ', $missing )
				)
			)
		);
	}
}
