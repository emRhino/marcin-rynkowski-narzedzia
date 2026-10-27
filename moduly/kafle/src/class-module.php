<?php
/**
 * Kafle promocyjne: definicja modułu.
 *
 * @package Rynkowski\Narzedzia
 */

namespace Rynkowski\Narzedzia\Moduly\Kafle;

use Rynkowski\Narzedzia\Cleanup;

defined( 'ABSPATH' ) || exit;

/**
 * Moduł „Kafle promocyjne”: grafika, wideo albo tekst w siatce produktów.
 */
final class Module implements \Rynkowski\Narzedzia\Module {

	/**
	 * Slug modułu.
	 */
	public function slug(): string {
		return 'kafle';
	}

	/**
	 * Nazwa modułu.
	 */
	public function name(): string {
		return __( 'Kafle promocyjne', 'marcin-rynkowski-narzedzia' );
	}

	/**
	 * Opis modułu.
	 */
	public function description(): string {
		return __( 'Grafika, wideo albo tekst na kolorowym tle w miejscu produktu na liście sklepu, kategorii i tagu.', 'marcin-rynkowski-narzedzia' );
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
		Tiles::register();
		Tile_Admin::register();
		Tile_Frontend::register();
	}

	/**
	 * Usuwa wszystkie kafle.
	 */
	public function uninstall(): void {
		Cleanup::delete_posts( Tiles::POST_TYPE );
	}
}
