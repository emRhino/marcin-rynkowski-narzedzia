<?php
/**
 * Rejestr modułów.
 *
 * @package Rynkowski\Narzedzia
 */

namespace Rynkowski\Narzedzia;

defined( 'ABSPATH' ) || exit;

/**
 * Wykrywa moduły, pilnuje ich włączenia, zależności i wersji danych.
 */
final class Registry {

	const OPTION_ENABLED  = 'rynkowski_narzedzia_moduly';
	const OPTION_VERSIONS = 'rynkowski_narzedzia_wersje_danych';

	/**
	 * Wykryte moduły według sluga.
	 *
	 * @var array<string, Module>
	 */
	private array $modules = array();

	/**
	 * Włączone moduły z brakującymi zależnościami: slug => nazwy zależności.
	 *
	 * @var array<string, string[]>
	 */
	private array $missing = array();

	/**
	 * Moduły uruchomione w tym żądaniu (włączone i z zależnościami).
	 *
	 * @var array<string, Module>
	 */
	private array $active = array();

	/**
	 * Tworzy rejestr dla folderu z modułami.
	 *
	 * @param string $dir Folder z podfolderami modułów.
	 */
	public function __construct( private string $dir ) {}

	/**
	 * Wczytuje manifesty `<dir>/<slug>/module.php`.
	 */
	public function discover(): void {
		$files = glob( $this->dir . '/*/module.php' );

		foreach ( $files ? $files : array() as $file ) {
			$module = require $file;

			if ( $module instanceof Module ) {
				$this->modules[ $module->slug() ] = $module;
			}
		}

		ksort( $this->modules );
	}

	/**
	 * Wykryte moduły.
	 *
	 * @return array<string, Module>
	 */
	public function all(): array {
		return $this->modules;
	}

	/**
	 * Czy moduł jest włączony. Nowy moduł jest domyślnie wyłączony.
	 *
	 * @param string $slug Slug modułu.
	 */
	public function is_enabled( string $slug ): bool {
		$enabled = get_option( self::OPTION_ENABLED, array() );

		return is_array( $enabled ) && ! empty( $enabled[ $slug ] );
	}

	/**
	 * Zapisuje listę włączonych modułów, pomijając nieznane slugi.
	 *
	 * @param string[] $slugs Slugi do włączenia.
	 */
	public function set_enabled( array $slugs ): void {
		$known = array_values( array_intersect( $slugs, array_keys( $this->modules ) ) );

		update_option( self::OPTION_ENABLED, array_fill_keys( $known, true ) );
	}

	/**
	 * Uruchamia włączone moduły, które mają swoje zależności.
	 */
	public function boot(): void {
		foreach ( $this->modules as $slug => $module ) {
			if ( ! $this->is_enabled( $slug ) ) {
				continue;
			}

			$missing = Dependencies::missing( $module->requires() );
			if ( $missing ) {
				$this->missing[ $slug ] = $missing;
				continue;
			}

			$this->migrate( $module );
			$module->register();
			$this->active[ $slug ] = $module;
		}
	}

	/**
	 * Moduły uruchomione w tym żądaniu.
	 *
	 * @return array<string, Module>
	 */
	public function active(): array {
		return $this->active;
	}

	/**
	 * Włączone moduły z brakującymi zależnościami.
	 *
	 * @return array<string, string[]>
	 */
	public function missing(): array {
		return $this->missing;
	}

	/**
	 * Podnosi dane modułu do bieżącej wersji, migracja po migracji.
	 *
	 * @param Module $module Moduł.
	 */
	public function migrate( Module $module ): void {
		$versions = get_option( self::OPTION_VERSIONS, array() );
		$versions = is_array( $versions ) ? $versions : array();
		$slug     = $module->slug();
		$target   = $module->data_version();

		// Pierwsze uruchomienie: dane powstają od razu w najnowszej wersji.
		if ( ! isset( $versions[ $slug ] ) ) {
			$versions[ $slug ] = $target;
			update_option( self::OPTION_VERSIONS, $versions );
			return;
		}

		$migrations = $module->migrations();

		for ( $version = (int) $versions[ $slug ] + 1; $version <= $target; $version++ ) {
			if ( isset( $migrations[ $version ] ) ) {
				( $migrations[ $version ] )();
			}

			// Zapis po każdym kroku, żeby przerwana aktualizacja ruszyła od właściwego miejsca.
			$versions[ $slug ] = $version;
			update_option( self::OPTION_VERSIONS, $versions );
		}
	}

	/**
	 * Usuwa dane wszystkich modułów i ustawienia rejestru.
	 */
	public function uninstall_all(): void {
		foreach ( $this->modules as $module ) {
			$module->uninstall();
		}

		delete_option( self::OPTION_ENABLED );
		delete_option( self::OPTION_VERSIONS );
	}
}
