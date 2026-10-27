<?php
/**
 * Kafle promocyjne: ustawienia modułu.
 *
 * @package Rynkowski\Narzedzia
 */

namespace Rynkowski\Narzedzia\Moduly\Kafle;

use Rynkowski\Narzedzia\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * Schemat i odczyt ustawień kafli (zakładka w Narzędzia → Ustawienia).
 *
 * @phpstan-import-type SettingsField from \Rynkowski\Narzedzia\Has_Settings
 */
final class Tile_Settings {

	const OPTION = 'rynkowski_narzedzia_kafle';

	/**
	 * Pola ustawień.
	 *
	 * @return SettingsField[]
	 */
	public static function fields(): array {
		return array(
			array(
				'id'      => 'min_wysokosc',
				'type'    => 'number',
				'title'   => __( 'Minimalna wysokość kafla', 'marcin-rynkowski-narzedzia' ),
				'desc'    => __( 'Kafel ma wysokość wiersza produktów, ale nie mniejszą niż ta.', 'marcin-rynkowski-narzedzia' ),
				'default' => 288,
				'min'     => 100,
				'max'     => 1000,
				'suffix'  => 'px',
			),
			array(
				'id'      => 'kolor_tla',
				'type'    => 'color',
				'title'   => __( 'Domyślny kolor tła kafla tekstowego', 'marcin-rynkowski-narzedzia' ),
				'desc'    => __( 'Puste pole = główny kolor motywu. Kolor ustawiony w kaflu ma pierwszeństwo.', 'marcin-rynkowski-narzedzia' ),
				'default' => '',
			),
			array(
				'id'      => 'autostart',
				'type'    => 'checkbox',
				'title'   => __( 'Autostart wideo', 'marcin-rynkowski-narzedzia' ),
				'desc'    => __( 'Wideo startuje samo (bez dźwięku). Klient zawsze może je zatrzymać.', 'marcin-rynkowski-narzedzia' ),
				'default' => 'yes',
			),
		);
	}

	/**
	 * Wartość ustawienia, zwalidowana albo domyślna.
	 *
	 * @param string $key Id pola.
	 * @return int|string|null
	 */
	public static function get( string $key ) {
		return Settings::get( self::OPTION, self::fields(), $key );
	}
}
