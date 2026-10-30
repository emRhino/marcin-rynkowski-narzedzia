<?php
/**
 * Zakładki produktu: ustawienia modułu.
 *
 * @package Rynkowski\Narzedzia
 */

namespace Rynkowski\Narzedzia\Moduly\Zakladki;

use Rynkowski\Narzedzia\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * Przełącznik dla każdej zakładki (zakładka w Narzędzia → Ustawienia).
 *
 * @phpstan-import-type SettingsField from \Rynkowski\Narzedzia\Has_Settings
 */
final class Tab_Settings {

	const OPTION = 'rynkowski_narzedzia_zakladki';

	/**
	 * Pola ustawień: id pola = klucz zakładki.
	 *
	 * @return SettingsField[]
	 */
	public static function fields(): array {
		$fields = array();
		foreach ( Product_Tabs::labels() as $key => $title ) {
			$fields[] = array(
				'id'      => $key,
				'type'    => 'checkbox',
				'title'   => $title,
				'desc'    => __( 'Pole w edycji produktu i zakładka na karcie produktu. Wyłączenie ukrywa oba, ale nie kasuje wpisanych treści.', 'marcin-rynkowski-narzedzia' ),
				'default' => 'yes',
			);
		}

		return $fields;
	}

	/**
	 * Czy zakładka jest włączona.
	 *
	 * @param string $key Klucz zakładki.
	 */
	public static function is_enabled( string $key ): bool {
		return 'yes' === Settings::get( self::OPTION, self::fields(), $key );
	}
}
