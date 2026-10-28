<?php
/**
 * Główna klasa wtyczki.
 *
 * @package Rynkowski\Narzedzia
 */

namespace Rynkowski\Narzedzia;

defined( 'ABSPATH' ) || exit;

/**
 * Punkt startowy: podpina hooki rdzenia i uruchamia moduły.
 */
final class Plugin {

	const VERSION            = '0.7.0';
	const OPTION_DELETE_DATA = 'rynkowski_narzedzia_usun_dane';

	/**
	 * Ścieżka głównego pliku wtyczki.
	 *
	 * @var string
	 */
	private static string $file = '';

	/**
	 * Start z głównego pliku wtyczki.
	 *
	 * @param string $file Ścieżka głównego pliku wtyczki.
	 */
	public static function boot( string $file ): void {
		self::$file = $file;

		add_action( 'before_woocommerce_init', array( self::class, 'declare_hpos' ) );
		// Aktualizacje działają niezależnie od modułów, żeby poprawka zawsze mogła dotrzeć do sklepu.
		add_action( 'plugins_loaded', array( Updater::class, 'boot' ), 0 );
		add_action( 'plugins_loaded', array( self::class, 'init' ) );
	}

	/**
	 * Ścieżka głównego pliku wtyczki.
	 */
	public static function file(): string {
		return self::$file;
	}

	/**
	 * Folder wtyczki ze slashem na końcu.
	 */
	public static function dir(): string {
		return dirname( self::$file ) . '/';
	}

	/**
	 * Deklaruje zgodność z HPOS (tabele zamówień WooCommerce).
	 */
	public static function declare_hpos(): void {
		if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', self::$file, true );
		}
	}

	/**
	 * Uruchamia moduły po załadowaniu pozostałych wtyczek.
	 */
	public static function init(): void {
		load_plugin_textdomain( 'marcin-rynkowski-narzedzia', false, dirname( plugin_basename( self::$file ) ) . '/languages' );

		$registry = new Registry( self::dir() . 'moduly' );
		$registry->discover();
		$registry->boot();

		( new Settings_Page( $registry ) )->register();

		$missing = $registry->missing();
		if ( $missing ) {
			add_action(
				'admin_notices',
				static function () use ( $registry, $missing ) {
					self::render_missing_notice( $registry, $missing );
				}
			);
		}
	}

	/**
	 * Informacja w panelu o włączonych modułach bez zależności.
	 *
	 * @param Registry                $registry Rejestr modułów.
	 * @param array<string, string[]> $missing  Slug modułu => brakujące zależności.
	 */
	public static function render_missing_notice( Registry $registry, array $missing ): void {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}

		$modules = $registry->all();

		foreach ( $missing as $slug => $dependencies ) {
			printf(
				'<div class="notice notice-error"><p>%s</p></div>',
				esc_html(
					sprintf(
						/* translators: 1: nazwa modułu, 2: lista brakujących wtyczek lub motywu */
						__( 'Moduł „%1$s” nie działa, bo brakuje aktywnych: %2$s.', 'marcin-rynkowski-narzedzia' ),
						isset( $modules[ $slug ] ) ? $modules[ $slug ]->name() : $slug,
						implode( ', ', $dependencies )
					)
				)
			);
		}
	}
}
