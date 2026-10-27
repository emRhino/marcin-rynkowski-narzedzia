<?php
/**
 * Bazowa klasa testów: Brain Monkey i proste zaślepki funkcji WordPressa.
 *
 * @package Rynkowski\Narzedzia
 */

// phpcs:ignoreFile -- plik testowy.

namespace Rynkowski\Narzedzia\Tests;

use Brain\Monkey;
use Brain\Monkey\Functions;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use PHPUnit\Framework\TestCase as Base;

abstract class TestCase extends Base {

	use MockeryPHPUnitIntegration;

	/**
	 * Opcje WordPressa trzymane w pamięci na czas testu.
	 *
	 * @var array<string, mixed>
	 */
	protected array $options = array();

	/**
	 * Meta wpisów trzymane w pamięci: [ID][klucz] => wartość.
	 *
	 * @var array<int, array<string, mixed>>
	 */
	protected array $meta = array();

	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();

		$this->options = array();
		$this->meta    = array();
		$_POST         = array();
		$_GET          = array();

		// Tłumaczenia i escapowanie zachowują się jak w WordPressie bez tłumaczeń.
		Functions\stubTranslationFunctions();
		Functions\stubEscapeFunctions();

		Functions\when( 'get_option' )->alias(
			function ( string $name, $default = false ) {
				return array_key_exists( $name, $this->options ) ? $this->options[ $name ] : $default;
			}
		);
		Functions\when( 'update_option' )->alias(
			function ( string $name, $value ): bool {
				$this->options[ $name ] = $value;
				return true;
			}
		);
		Functions\when( 'delete_option' )->alias(
			function ( string $name ): bool {
				unset( $this->options[ $name ] );
				return true;
			}
		);
		Functions\when( 'get_post_meta' )->alias(
			function ( int $id, string $key = '', bool $single = false ) {
				return $this->meta[ $id ][ $key ] ?? '';
			}
		);
		Functions\when( 'update_post_meta' )->alias(
			function ( int $id, string $key, $value ): bool {
				$this->meta[ $id ][ $key ] = $value;
				return true;
			}
		);
		Functions\when( 'wp_unslash' )->returnArg();
		Functions\when( 'absint' )->alias(
			static function ( $value ): int {
				return abs( (int) $value );
			}
		);
		Functions\when( 'sanitize_key' )->alias(
			static function ( $key ): string {
				return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $key ) );
			}
		);
		Functions\when( 'sanitize_text_field' )->alias(
			static function ( $text ): string {
				return trim( wp_strip_tags_for_tests( (string) $text ) );
			}
		);
		Functions\when( 'sanitize_textarea_field' )->alias(
			static function ( $text ): string {
				return trim( wp_strip_tags_for_tests( (string) $text ) );
			}
		);
		Functions\when( 'sanitize_hex_color' )->alias(
			static function ( $color ) {
				if ( '' === $color ) {
					return '';
				}
				return preg_match( '|^#([A-Fa-f0-9]{3}){1,2}$|', (string) $color ) ? $color : null;
			}
		);
		Functions\when( 'checked' )->alias(
			static function ( $checked, $current = true, bool $display = true ): string {
				$result = (string) $checked === (string) $current ? " checked='checked'" : '';
				if ( $display ) {
					echo $result;
				}
				return $result;
			}
		);
		Functions\when( 'selected' )->alias(
			static function ( $selected, $current = true, bool $display = true ): string {
				$result = (string) $selected === (string) $current ? " selected='selected'" : '';
				if ( $display ) {
					echo $result;
				}
				return $result;
			}
		);
	}

	protected function tearDown(): void {
		Monkey\tearDown();
		parent::tearDown();
	}

	/**
	 * Zwraca wyjście wywołania.
	 */
	protected function capture( callable $callback ): string {
		ob_start();
		$callback();
		return (string) ob_get_clean();
	}
}

/**
 * Uproszczone usuwanie tagów, jak sanitize_text_field.
 */
function wp_strip_tags_for_tests( string $text ): string {
	return strip_tags( $text );
}
