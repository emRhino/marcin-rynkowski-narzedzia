<?php
/**
 * Wstążki: ustawienia modułu.
 *
 * @package Rynkowski\Narzedzia
 */

namespace Rynkowski\Narzedzia\Moduly\Wstazki;

use Rynkowski\Narzedzia\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * Schemat i odczyt ustawień wstążek (zakładka w Narzędzia → Ustawienia).
 *
 * @phpstan-import-type SettingsField from \Rynkowski\Narzedzia\Has_Settings
 */
final class Ribbon_Settings {

	const OPTION = 'rynkowski_narzedzia_wstazki';

	/**
	 * Pola ustawień.
	 *
	 * @return SettingsField[]
	 */
	public static function fields(): array {
		return array(
			array(
				'id'      => 'odstep',
				'type'    => 'number',
				'title'   => __( 'Odstęp między odznakami', 'marcin-rynkowski-narzedzia' ),
				'desc'    => __( 'Gdy na zdjęciu jest kilka odznak, stają jedna pod drugą z tym odstępem.', 'marcin-rynkowski-narzedzia' ),
				'default' => 5,
				'min'     => 0,
				'max'     => 40,
				'suffix'  => 'px',
			),
			array(
				'id'      => 'ksztalt',
				'type'    => 'select',
				'title'   => __( 'Kształt odznak', 'marcin-rynkowski-narzedzia' ),
				'desc'    => __( '„Jak w Blocksy” bierze kształt z ustawień odznak motywu.', 'marcin-rynkowski-narzedzia' ),
				'default' => 'blocksy',
				'options' => array(
					'blocksy' => __( 'Jak w Blocksy', 'marcin-rynkowski-narzedzia' ),
					'type-1'  => __( 'Prostokąt', 'marcin-rynkowski-narzedzia' ),
					'type-2'  => __( 'Zaokrąglony prostokąt', 'marcin-rynkowski-narzedzia' ),
					'type-3'  => __( 'Koło', 'marcin-rynkowski-narzedzia' ),
				),
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
