<?php
/**
 * Zakładki produktu: definicja modułu.
 *
 * @package Rynkowski\Narzedzia
 */

namespace Rynkowski\Narzedzia\Moduly\Zakladki;

use Rynkowski\Narzedzia\Has_Settings;

defined( 'ABSPATH' ) || exit;

/**
 * Moduł „Zakładki produktu”: dodatkowe opisy produktu jako zakładki WooCommerce.
 *
 * @phpstan-import-type SettingsTab from Has_Settings
 */
final class Module implements \Rynkowski\Narzedzia\Module, Has_Settings {

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
	 * Zakładka „Zakładki produktu” w Narzędzia → Ustawienia.
	 *
	 * @return array Tytuł, nazwa opcji i pola.
	 * @phpstan-return SettingsTab
	 */
	public function settings_tab(): array {
		return array(
			'title'  => $this->name(),
			'option' => Tab_Settings::OPTION,
			'fields' => Tab_Settings::fields(),
		);
	}

	/**
	 * Usuwa treści zakładek ze wszystkich produktów i ustawienia.
	 */
	public function uninstall(): void {
		foreach ( array_keys( Product_Tabs::labels() ) as $key ) {
			delete_post_meta_by_key( Product_Tabs::meta_key( $key ) );
		}
		delete_option( Tab_Settings::OPTION );
	}
}
