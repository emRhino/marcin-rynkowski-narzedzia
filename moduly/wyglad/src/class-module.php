<?php
/**
 * Poprawki wyglądu: definicja modułu.
 *
 * @package Rynkowski\Narzedzia
 */

namespace Rynkowski\Narzedzia\Moduly\Wyglad;

use Rynkowski\Narzedzia\Has_Settings;

defined( 'ABSPATH' ) || exit;

/**
 * Moduł „Poprawki wyglądu”: drobne zmiany wyglądu Blocksy włączane przełącznikami.
 *
 * @phpstan-import-type SettingsTab from Has_Settings
 */
final class Module implements \Rynkowski\Narzedzia\Module, Has_Settings {

	/**
	 * Slug modułu.
	 */
	public function slug(): string {
		return 'wyglad';
	}

	/**
	 * Nazwa modułu.
	 */
	public function name(): string {
		return __( 'Poprawki wyglądu', 'marcin-rynkowski-narzedzia' );
	}

	/**
	 * Opis modułu.
	 */
	public function description(): string {
		return __( 'Drobne poprawki wyglądu Blocksy, każda z osobnym przełącznikiem: breadcrumb w jednej linii i zdjęcie produktu na całą szerokość na telefonie.', 'marcin-rynkowski-narzedzia' );
	}

	/**
	 * Wymagane zależności.
	 *
	 * @return string[]
	 */
	public function requires(): array {
		return array( 'blocksy' );
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
		Breadcrumbs::register();
		Product_Image::register();
	}

	/**
	 * Zakładka „Poprawki wyglądu” w Narzędzia → Ustawienia.
	 *
	 * @return array Tytuł, nazwa opcji i pola.
	 * @phpstan-return SettingsTab
	 */
	public function settings_tab(): array {
		return array(
			'title'  => $this->name(),
			'option' => Look_Settings::OPTION,
			'fields' => Look_Settings::fields(),
		);
	}

	/**
	 * Usuwa ustawienia modułu (moduł nie ma innych danych).
	 */
	public function uninstall(): void {
		delete_option( Look_Settings::OPTION );
	}
}
