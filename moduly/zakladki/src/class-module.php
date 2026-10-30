<?php
/**
 * Zakładki produktu: definicja modułu.
 *
 * @package Rynkowski\Narzedzia
 */

namespace Rynkowski\Narzedzia\Moduly\Zakladki;

defined( 'ABSPATH' ) || exit;

/**
 * Moduł „Zakładki produktu”: dodatkowe opisy produktu jako zakładki WooCommerce.
 */
final class Module implements \Rynkowski\Narzedzia\Module {

	/**
	 * Slug modułu.
	 */
	public function slug(): string {
		return 'zakladki';
	}

	/**
	 * Nazwa modułu.
	 */
	public function name(): string {
		return __( 'Zakładki produktu', 'marcin-rynkowski-narzedzia' );
	}

	/**
	 * Opis modułu.
	 */
	public function description(): string {
		return __( 'Dodatkowe opisy produktu: Pielęgnacja, Cechy produktu i Bezpieczeństwo produktu, wyświetlane jako zakładki obok opisu (w Blocksy także jako akordeon).', 'marcin-rynkowski-narzedzia' );
	}

	/**
	 * Wymagane zależności.
	 *
	 * @return string[]
	 */
	public function requires(): array {
		return array( 'woocommerce' );
	}

	/**
	 * Wersja danych.
	 */
	public function data_version(): int {
		return 1;
	}

	/**
	 * Migracje danych.
	 *
	 * @return array<int, callable(): void>
	 */
	public function migrations(): array {
		return array();
	}

	/**
	 * Podpina części modułu.
	 */
	public function register(): void {
		Tab_Editor::register();
		Product_Tabs::register();
	}

	/**
	 * Usuwa treści zakładek ze wszystkich produktów.
	 */
	public function uninstall(): void {
		foreach ( array_keys( Product_Tabs::labels() ) as $key ) {
			delete_post_meta_by_key( Product_Tabs::meta_key( $key ) );
		}
	}
}
