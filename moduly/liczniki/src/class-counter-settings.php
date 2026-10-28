<?php
/**
 * Liczniki promocji: wspólny wygląd (ustawienia modułu).
 *
 * @package Rynkowski\Narzedzia
 */

namespace Rynkowski\Narzedzia\Moduly\Liczniki;

use Rynkowski\Narzedzia\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * Schemat i odczyt wyglądu liczników (zakładka w Narzędzia → Ustawienia).
 *
 * @phpstan-import-type SettingsField from \Rynkowski\Narzedzia\Has_Settings
 */
final class Counter_Settings {

	const OPTION = 'rynkowski_narzedzia_liczniki';

	/**
	 * Pola ustawień.
	 *
	 * @return SettingsField[]
	 */
	public static function fields(): array {
		$weights = array(
			'400' => __( 'Normalne', 'marcin-rynkowski-narzedzia' ),
			'600' => __( 'Półgrube', 'marcin-rynkowski-narzedzia' ),
			'700' => __( 'Grube', 'marcin-rynkowski-narzedzia' ),
		);
		$theme   = __( 'Puste pole = kolor z motywu.', 'marcin-rynkowski-narzedzia' );

		return array(
			array(
				'id'      => 'kolor_tekstu',
				'type'    => 'color',
				'title'   => __( 'Kolor tekstu nad licznikiem', 'marcin-rynkowski-narzedzia' ),
				'desc'    => $theme,
				'default' => '',
			),
			array(
				'id'      => 'grubosc_tekstu',
				'type'    => 'select',
				'title'   => __( 'Pogrubienie tekstu', 'marcin-rynkowski-narzedzia' ),
				'default' => '600',
				'options' => $weights,
			),
			array(
				'id'      => 'rozmiar_tekstu',
				'type'    => 'number',
				'title'   => __( 'Wielkość tekstu', 'marcin-rynkowski-narzedzia' ),
				'default' => 16,
				'min'     => 10,
				'max'     => 48,
				'suffix'  => 'px',
			),
			array(
				'id'      => 'kolor_cyfr',
				'type'    => 'color',
				'title'   => __( 'Kolor cyfr', 'marcin-rynkowski-narzedzia' ),
				'desc'    => $theme,
				'default' => '',
			),
			array(
				'id'      => 'grubosc_cyfr',
				'type'    => 'select',
				'title'   => __( 'Pogrubienie cyfr', 'marcin-rynkowski-narzedzia' ),
				'default' => '600',
				'options' => $weights,
			),
			array(
				'id'      => 'rozmiar_cyfr',
				'type'    => 'number',
				'title'   => __( 'Wielkość cyfr', 'marcin-rynkowski-narzedzia' ),
				'default' => 28,
				'min'     => 12,
				'max'     => 72,
				'suffix'  => 'px',
			),
			array(
				'id'      => 'kolor_ramki',
				'type'    => 'color',
				'title'   => __( 'Kolor obramowania', 'marcin-rynkowski-narzedzia' ),
				'desc'    => $theme,
				'default' => '',
			),
			array(
				'id'      => 'kolor_tla',
				'type'    => 'color',
				'title'   => __( 'Kolor wypełnienia', 'marcin-rynkowski-narzedzia' ),
				'desc'    => __( 'Puste pole = przezroczyste.', 'marcin-rynkowski-narzedzia' ),
				'default' => '',
			),
			array(
				'id'      => 'padding',
				'type'    => 'number',
				'title'   => __( 'Padding pola', 'marcin-rynkowski-narzedzia' ),
				'desc'    => __( 'Odstęp od ramki do cyfr.', 'marcin-rynkowski-narzedzia' ),
				'default' => 12,
				'min'     => 0,
				'max'     => 40,
				'suffix'  => 'px',
			),
			array(
				'id'      => 'odstep',
				'type'    => 'number',
				'title'   => __( 'Odstęp między polami', 'marcin-rynkowski-narzedzia' ),
				'default' => 12,
				'min'     => 0,
				'max'     => 40,
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
