<?php
/**
 * Odczyt i walidacja ustawień modułów.
 *
 * @package Rynkowski\Narzedzia
 */

namespace Rynkowski\Narzedzia;

defined( 'ABSPATH' ) || exit;

/**
 * Ścisła walidacja pól według schematu: wszystko spoza schematu wraca do wartości domyślnej.
 *
 * @phpstan-import-type SettingsField from Has_Settings
 */
final class Settings {

	/**
	 * Waliduje jedną wartość według typu pola.
	 *
	 * @param array $field Pole ze schematu.
	 * @param mixed $raw   Surowa wartość (z formularza albo z bazy).
	 * @return int|string
	 * @phpstan-param SettingsField $field
	 */
	public static function sanitize( array $field, $raw ) {
		switch ( $field['type'] ) {
			case 'number':
				if ( ! is_scalar( $raw ) || ! preg_match( '/^-?\d+$/', trim( (string) $raw ) ) ) {
					return $field['default'];
				}
				$number = (int) $raw;
				$min    = $field['min'] ?? PHP_INT_MIN;
				$max    = $field['max'] ?? PHP_INT_MAX;
				return $number < $min || $number > $max ? $field['default'] : $number;

			case 'color':
				// Puste pole = kolor z motywu.
				if ( '' === $raw ) {
					return '';
				}
				$color = is_string( $raw ) ? sanitize_hex_color( $raw ) : null;
				return $color ? $color : $field['default'];

			case 'select':
				$options = array_map( 'strval', array_keys( $field['options'] ?? array() ) );
				return is_scalar( $raw ) && in_array( (string) $raw, $options, true ) ? (string) $raw : $field['default'];

			case 'checkbox':
				return 'yes' === $raw || '1' === $raw || true === $raw ? 'yes' : 'no';

			default:
				return $field['default'];
		}
	}

	/**
	 * Wszystkie wartości opcji, zwalidowane i uzupełnione domyślnymi.
	 *
	 * @param string $option Nazwa opcji w bazie.
	 * @param array  $fields Pola ze schematu.
	 * @return array<string, int|string>
	 * @phpstan-param SettingsField[] $fields
	 */
	public static function values( string $option, array $fields ): array {
		$stored = get_option( $option, array() );
		$stored = is_array( $stored ) ? $stored : array();
		$values = array();

		foreach ( $fields as $field ) {
			$values[ $field['id'] ] = array_key_exists( $field['id'], $stored )
				? self::sanitize( $field, $stored[ $field['id'] ] )
				: $field['default'];
		}

		return $values;
	}

	/**
	 * Jedna wartość opcji.
	 *
	 * @param string $option Nazwa opcji w bazie.
	 * @param array  $fields Pola ze schematu.
	 * @param string $key    Id pola.
	 * @return int|string|null Null dla pola spoza schematu.
	 * @phpstan-param SettingsField[] $fields
	 */
	public static function get( string $option, array $fields, string $key ) {
		return self::values( $option, $fields )[ $key ] ?? null;
	}

	/**
	 * Wartości z wysłanego formularza; brak pola tak/nie znaczy „nie”.
	 *
	 * Wołający musi wcześniej sprawdzić nonce i uprawnienia.
	 *
	 * @param array $raw    Surowe dane formularza dla tej opcji.
	 * @param array $fields Pola ze schematu.
	 * @return array<string, int|string>
	 * @phpstan-param array<string, mixed> $raw
	 * @phpstan-param SettingsField[] $fields
	 */
	public static function from_form( array $raw, array $fields ): array {
		$values = array();

		foreach ( $fields as $field ) {
			if ( array_key_exists( $field['id'], $raw ) ) {
				$values[ $field['id'] ] = self::sanitize( $field, $raw[ $field['id'] ] );
			} else {
				$values[ $field['id'] ] = 'checkbox' === $field['type'] ? 'no' : $field['default'];
			}
		}

		return $values;
	}
}
