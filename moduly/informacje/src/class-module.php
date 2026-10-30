<?php
/**
 * Informacje o zakupie: definicja modułu.
 *
 * @package Rynkowski\Narzedzia
 */

namespace Rynkowski\Narzedzia\Moduly\Informacje;

use Rynkowski\Narzedzia\Cleanup;
use Rynkowski\Narzedzia\Has_Settings;

defined( 'ABSPATH' ) || exit;

/**
 * Moduł „Informacje o zakupie”: wiersze z ikoną i podpisem pod przyciskiem „Dodaj do koszyka”.
 *
 * @phpstan-import-type SettingsTab from Has_Settings
 */
final class Module implements \Rynkowski\Narzedzia\Module, Has_Settings {

	/**
	 * Slug modułu.
	 */
	public function slug(): string {
		return 'informacje';
	}

	/**
	 * Nazwa modułu.
	 */
	public function name(): string {
		return __( 'Informacje o zakupie', 'marcin-rynkowski-narzedzia' );
	}

	/**
	 * Opis modułu.
	 */
	public function description(): string {
		return __( 'Sekcja pod przyciskiem „Dodaj do koszyka”: wiersze z ikoną i podpisem (np. czas wysyłki, zwroty, płatności), z tekstem domyślnym i własnym dla produktu.', 'marcin-rynkowski-narzedzia' );
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
		Info_Rows::register();
		Info_Frontend::register();

		if ( is_admin() ) {
			Info_Row_Admin::register();
			Info_Product_Box::register();
		}
	}

	/**
	 * Zakładka „Informacje o zakupie” w Narzędzia → Ustawienia.
	 *
	 * @return array Tytuł, nazwa opcji i pola.
	 * @phpstan-return SettingsTab
	 */
	public function settings_tab(): array {
		return array(
			'title'  => $this->name(),
			'option' => Info_Settings::OPTION,
			'fields' => Info_Settings::fields(),
		);
	}

	/**
	 * Usuwa wiersze, teksty produktów i ustawienia.
	 */
	public function uninstall(): void {
		Cleanup::delete_posts( Info_Rows::POST_TYPE );
		delete_post_meta_by_key( Info_Rows::META_PRODUCT_TEXTS );
		delete_option( Info_Settings::OPTION );
	}
}
