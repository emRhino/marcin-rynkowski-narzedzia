<?php
/**
 * Wstążki: definicja modułu.
 *
 * @package Rynkowski\Narzedzia
 */

namespace Rynkowski\Narzedzia\Moduly\Wstazki;

use Rynkowski\Narzedzia\Cleanup;
use Rynkowski\Narzedzia\Has_Settings;

defined( 'ABSPATH' ) || exit;

/**
 * Moduł „Wstążki”: etykiety na zdjęciach produktów w stylu odznak Blocksy.
 *
 * @phpstan-import-type SettingsTab from Has_Settings
 */
final class Module implements \Rynkowski\Narzedzia\Module, Has_Settings {

	/**
	 * Slug modułu.
	 */
	public function slug(): string {
		return 'wstazki';
	}

	/**
	 * Nazwa modułu.
	 */
	public function name(): string {
		return __( 'Wstążki', 'marcin-rynkowski-narzedzia' );
	}

	/**
	 * Opis modułu.
	 */
	public function description(): string {
		return __( 'Etykiety na zdjęciach produktów w stylu odznak Blocksy, przypisywane do produktów, kategorii i tagów.', 'marcin-rynkowski-narzedzia' );
	}

	/**
	 * Wymagane zależności.
	 *
	 * @return string[]
	 */
	public function requires(): array {
		return array( 'woocommerce', 'blocksy' );
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
		Ribbons::register();
		Ribbon_Admin::register();
		Ribbon_Product_Tab::register();
		Ribbon_Frontend::register();
	}

	/**
	 * Zakładka „Wstążki” w Narzędzia → Ustawienia.
	 *
	 * @return array Tytuł, nazwa opcji i pola.
	 * @phpstan-return SettingsTab
	 */
	public function settings_tab(): array {
		return array(
			'title'  => $this->name(),
			'option' => Ribbon_Settings::OPTION,
			'fields' => Ribbon_Settings::fields(),
		);
	}

	/**
	 * Usuwa wszystkie wstążki i ustawienia modułu.
	 */
	public function uninstall(): void {
		Cleanup::delete_posts( Ribbons::POST_TYPE );
		delete_option( Ribbon_Settings::OPTION );
	}
}
