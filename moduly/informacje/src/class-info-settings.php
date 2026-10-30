<?php
/**
 * Informacje o zakupie: ustawienia modułu.
 *
 * @package Rynkowski\Narzedzia
 */

namespace Rynkowski\Narzedzia\Moduly\Informacje;

use Rynkowski\Narzedzia\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * Schemat i odczyt ustawień sekcji (zakładka w Narzędzia → Ustawienia).
 *
 * @phpstan-import-type SettingsField from \Rynkowski\Narzedzia\Has_Settings
 */
final class Info_Settings {

	const OPTION = 'rynkowski_narzedzia_informacje';

	/**
	 * Pola ustawień.
	 *
	 * @return SettingsField[]
	 */
	public static function fields(): array {
		return array(
			array(
				'id'      => 'sekcja',
				'type'    => 'checkbox',
				'title'   => __( 'Pokazuj sekcję', 'marcin-rynkowski-narzedzia' ),
				'desc'    => __( 'Sekcja pod przyciskiem „Dodaj do koszyka”. Wiersze dodajesz w Narzędzia → Informacje o zakupie.', 'marcin-rynkowski-narzedzia' ),
				'default' => 'yes',
			),
			array(
				'id'      => 'kolor_wyroznienia',
				'type'    => 'color',
				'title'   => __( 'Kolor wyróżnienia', 'marcin-rynkowski-narzedzia' ),
				'desc'    => __( 'Kolor fraz oznaczonych przyciskiem „wyróżnij”. Puste = główny kolor motywu.', 'marcin-rynkowski-narzedzia' ),
				'default' => '',
			),
			array(
				'id'      => 'kolor_ramki',
				'type'    => 'color',
				'title'   => __( 'Kolor ramki', 'marcin-rynkowski-narzedzia' ),
				'desc'    => __( 'Ramka i linie między wierszami. Puste = kolor obramowań motywu.', 'marcin-rynkowski-narzedzia' ),
				'default' => '',
			),
			array(
				'id'      => 'rozmiar_ikony',
				'type'    => 'number',
				'title'   => __( 'Rozmiar ikony', 'marcin-rynkowski-narzedzia' ),
				'default' => 20,
				'min'     => 12,
				'max'     => 64,
				'suffix'  => 'px',
			),
			array(
				'id'      => 'padding',
				'type'    => 'number',
				'title'   => __( 'Odstęp w wierszu', 'marcin-rynkowski-narzedzia' ),
				'desc'    => __( 'Wewnętrzny odstęp każdego wiersza.', 'marcin-rynkowski-narzedzia' ),
				'default' => 16,
				'min'     => 0,
				'max'     => 40,
				'suffix'  => 'px',
			),
			array(
				'id'      => 'odstep',
				'type'    => 'number',
				'title'   => __( 'Odstęp pod sekcją', 'marcin-rynkowski-narzedzia' ),
				'default' => 24,
				'min'     => 0,
				'max'     => 80,
				'suffix'  => 'px',
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
