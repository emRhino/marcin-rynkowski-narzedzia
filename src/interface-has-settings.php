<?php
/**
 * Kontrakt modułu z ustawieniami.
 *
 * @package Rynkowski\Narzedzia
 */

namespace Rynkowski\Narzedzia;

defined( 'ABSPATH' ) || exit;

/**
 * Moduł, który ma własną zakładkę w Narzędzia → Ustawienia.
 *
 * Pole: id, type (number|color|select|checkbox), title, desc, default,
 * dla number: min, max, suffix; dla select: options (wartość => etykieta).
 *
 * @phpstan-type SettingsField array{
 *     id: string,
 *     type: string,
 *     title: string,
 *     desc?: string,
 *     default: int|string,
 *     min?: int,
 *     max?: int,
 *     suffix?: string,
 *     options?: array<string, string>
 * }
 * @phpstan-type SettingsTab array{title: string, option: string, fields: SettingsField[]}
 */
interface Has_Settings {

	/**
	 * Zakładka ustawień: tytuł, nazwa opcji w bazie i pola.
	 *
	 * @return SettingsTab
	 */
	public function settings_tab(): array;
}
