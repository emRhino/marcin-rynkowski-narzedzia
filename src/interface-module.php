<?php
/**
 * Kontrakt modułu.
 *
 * @package Rynkowski\Narzedzia
 */

namespace Rynkowski\Narzedzia;

defined( 'ABSPATH' ) || exit;

/**
 * Każdy moduł w `moduly/<slug>/module.php` zwraca obiekt tego typu.
 *
 * Moduł korzysta tylko z rdzenia, nigdy z innych modułów.
 */
interface Module {

	/**
	 * Krótki identyfikator, taki sam jak nazwa folderu modułu.
	 */
	public function slug(): string;

	/**
	 * Nazwa widoczna w panelu.
	 */
	public function name(): string;

	/**
	 * Jedno zdanie o tym, co moduł robi.
	 */
	public function description(): string;

	/**
	 * Wymagane zależności, klucze z Dependencies::LABELS.
	 *
	 * @return string[]
	 */
	public function requires(): array;

	/**
	 * Bieżąca wersja danych modułu, od 1.
	 */
	public function data_version(): int;

	/**
	 * Migracje danych: wersja docelowa => funkcja podnosząca dane do tej wersji.
	 *
	 * @return array<int, callable(): void>
	 */
	public function migrations(): array;

	/**
	 * Podpina hooki modułu. Wołane tylko, gdy moduł jest włączony i ma zależności.
	 */
	public function register(): void;

	/**
	 * Usuwa wszystkie dane modułu. Wołane przy odinstalowaniu, gdy właściciel na to pozwolił.
	 */
	public function uninstall(): void;
}
