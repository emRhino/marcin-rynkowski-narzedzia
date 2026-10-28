<?php
/**
 * Liczniki promocji: definicja modułu.
 *
 * @package Rynkowski\Narzedzia
 */

namespace Rynkowski\Narzedzia\Moduly\Liczniki;

use Rynkowski\Narzedzia\Cleanup;
use Rynkowski\Narzedzia\Has_Settings;

defined( 'ABSPATH' ) || exit;

/**
 * Moduł „Liczniki promocji”: odliczanie do końca promocji na stronie produktu.
 *
 * @phpstan-import-type SettingsTab from Has_Settings
 */
final class Module implements \Rynkowski\Narzedzia\Module, Has_Settings {

	/**
	 * Slug modułu.
	 */
	public function slug(): string {
		return 'liczniki';
	}

	/**
	 * Nazwa modułu.
	 */
	public function name(): string {
		return __( 'Liczniki promocji', 'marcin-rynkowski-narzedzia' );
	}

	/**
	 * Opis modułu.
	 */
	public function description(): string {
		return __( 'Tekst promocji z odliczaniem do jej końca pod ceną na stronie produktu, przypisywany do produktów, kategorii i tagów.', 'marcin-rynkowski-narzedzia' );
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
		Counters::register();
		Counter_Admin::register();
		Counter_Frontend::register();
	}

	/**
	 * Zakładka „Liczniki promocji” w Narzędzia → Ustawienia: wspólny wygląd liczników.
	 *
	 * @return array Tytuł, nazwa opcji i pola.
	 * @phpstan-return SettingsTab
	 */
	public function settings_tab(): array {
		return array(
			'title'  => $this->name(),
			'option' => Counter_Settings::OPTION,
			'fields' => Counter_Settings::fields(),
		);
	}

	/**
	 * Usuwa wszystkie liczniki i ustawienia modułu.
	 */
	public function uninstall(): void {
		Cleanup::delete_posts( Counters::POST_TYPE );
		delete_option( Counter_Settings::OPTION );
	}
}
