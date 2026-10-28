<?php
/**
 * Poprawki wyglądu: ustawienia modułu.
 *
 * @package Rynkowski\Narzedzia
 */

namespace Rynkowski\Narzedzia\Moduly\Wyglad;

use Rynkowski\Narzedzia\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * Schemat i odczyt przełączników poprawek wyglądu (zakładka w Narzędzia → Ustawienia).
 *
 * @phpstan-import-type SettingsField from \Rynkowski\Narzedzia\Has_Settings
 */
final class Look_Settings {

	const OPTION = 'rynkowski_narzedzia_wyglad';

	/**
	 * Pola ustawień.
	 *
	 * @return SettingsField[]
	 */
	public static function fields(): array {
		return array(
			array(
				'id'      => 'breadcrumb_w_linii',
				'type'    => 'checkbox',
				'title'   => __( 'Breadcrumb w linii', 'marcin-rynkowski-narzedzia' ),
				'desc'    => __( 'Na telefonie breadcrumb zostaje w jednej linii i przewija się w bok; startuje przewinięty do ostatniego elementu.', 'marcin-rynkowski-narzedzia' ),
				'default' => 'yes',
			),
			array(
				'id'      => 'zdjecie_pelna_szerokosc',
				'type'    => 'checkbox',
				'title'   => __( 'Zdjęcie produktu na całą szerokość', 'marcin-rynkowski-narzedzia' ),
				'desc'    => __( 'Na telefonie główne zdjęcie na stronie produktu zajmuje całą szerokość ekranu, bez marginesów i zaokrągleń. Miniatury zostają w marginesach.', 'marcin-rynkowski-narzedzia' ),
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
