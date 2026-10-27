<?php
// phpcs:ignoreFile -- plik testowy.

namespace Rynkowski\Narzedzia\Tests;

use Rynkowski\Narzedzia\Settings;

final class SettingsTest extends TestCase {

	private const OPTION = 'rynkowski_test_ustawiony';

	/**
	 * @return array<int, array<string, mixed>>
	 */
	private function fields(): array {
		return ( new Fake_Settings_Module() )->settings_tab()['fields'];
	}

	/**
	 * @return array<string, mixed>
	 */
	private function field( string $id ): array {
		foreach ( $this->fields() as $field ) {
			if ( $id === $field['id'] ) {
				return $field;
			}
		}
		$this->fail( "Brak pola $id" );
	}

	public function test_number_accepts_integers_in_range_only(): void {
		$field = $this->field( 'liczba' );

		$this->assertSame( 7, Settings::sanitize( $field, '7' ) );
		$this->assertSame( 0, Settings::sanitize( $field, 0 ) );
		$this->assertSame( 10, Settings::sanitize( $field, ' 10 ' ) );
		$this->assertSame( 5, Settings::sanitize( $field, '11' ) );
		$this->assertSame( 5, Settings::sanitize( $field, '-1' ) );
		$this->assertSame( 5, Settings::sanitize( $field, '3.5' ) );
		$this->assertSame( 5, Settings::sanitize( $field, '3px' ) );
		$this->assertSame( 5, Settings::sanitize( $field, '1;}body{' ) );
		$this->assertSame( 5, Settings::sanitize( $field, array( 3 ) ) );
		$this->assertSame( 5, Settings::sanitize( $field, null ) );
	}

	public function test_number_without_range_accepts_any_integer(): void {
		$field = $this->field( 'bez_zakresu' );

		$this->assertSame( -40, Settings::sanitize( $field, '-40' ) );
		$this->assertSame( 99999, Settings::sanitize( $field, '99999' ) );
	}

	public function test_color_accepts_hex_or_empty(): void {
		$field = $this->field( 'kolor' ) + array();
		$field['default'] = '#123456';

		$this->assertSame( '#ff0000', Settings::sanitize( $field, '#ff0000' ) );
		$this->assertSame( '', Settings::sanitize( $field, '' ) );
		$this->assertSame( '#123456', Settings::sanitize( $field, 'red;}body{display:none' ) );
		$this->assertSame( '#123456', Settings::sanitize( $field, 'javascript:alert(1)' ) );
		$this->assertSame( '#123456', Settings::sanitize( $field, array( '#fff' ) ) );
	}

	public function test_select_accepts_only_listed_options(): void {
		$field = $this->field( 'wybor' );

		$this->assertSame( 'b', Settings::sanitize( $field, 'b' ) );
		$this->assertSame( 'a', Settings::sanitize( $field, 'c' ) );
		$this->assertSame( 'a', Settings::sanitize( $field, '<script>' ) );
		$this->assertSame( 'a', Settings::sanitize( $field, array( 'b' ) ) );
	}

	public function test_select_without_options_falls_back_to_default(): void {
		$field = array( 'id' => 'x', 'type' => 'select', 'title' => 'X', 'default' => 'd' );

		$this->assertSame( 'd', Settings::sanitize( $field, 'd' ) );
	}

	public function test_checkbox_is_yes_or_no(): void {
		$field = $this->field( 'flaga' );

		$this->assertSame( 'yes', Settings::sanitize( $field, 'yes' ) );
		$this->assertSame( 'yes', Settings::sanitize( $field, '1' ) );
		$this->assertSame( 'yes', Settings::sanitize( $field, true ) );
		$this->assertSame( 'no', Settings::sanitize( $field, 'no' ) );
		$this->assertSame( 'no', Settings::sanitize( $field, 'tak' ) );
		$this->assertSame( 'no', Settings::sanitize( $field, null ) );
	}

	public function test_unknown_type_always_returns_default(): void {
		$field = array( 'id' => 'x', 'type' => 'textarea', 'title' => 'X', 'default' => 'domyślna' );

		$this->assertSame( 'domyślna', Settings::sanitize( $field, '<script>alert(1)</script>' ) );
	}

	public function test_values_use_defaults_and_clean_stored_garbage(): void {
		$this->options[ self::OPTION ] = array(
			'liczba' => '999',
			'kolor'  => '#abcdef',
			'wybor'  => 'b',
			'obce'   => 'pomijane',
		);

		$this->assertSame(
			array(
				'liczba'      => 5,
				'bez_zakresu' => 1,
				'kolor'       => '#abcdef',
				'wybor'       => 'b',
				'flaga'       => 'yes',
			),
			Settings::values( self::OPTION, $this->fields() )
		);
	}

	public function test_values_survive_broken_option(): void {
		$this->options[ self::OPTION ] = 'zepsute';

		$this->assertSame( 5, Settings::values( self::OPTION, $this->fields() )['liczba'] );
	}

	public function test_get_returns_single_value_or_null_for_unknown_key(): void {
		$this->options[ self::OPTION ] = array( 'wybor' => 'b' );

		$this->assertSame( 'b', Settings::get( self::OPTION, $this->fields(), 'wybor' ) );
		$this->assertNull( Settings::get( self::OPTION, $this->fields(), 'nie_ma' ) );
	}

	public function test_from_form_treats_missing_checkbox_as_no_and_keeps_defaults_for_rest(): void {
		$this->assertSame(
			array(
				'liczba'      => 8,
				'bez_zakresu' => 1,
				'kolor'       => '',
				'wybor'       => 'a',
				'flaga'       => 'no',
			),
			Settings::from_form( array( 'liczba' => '8', 'wybor' => 'zly', 'obce' => 'x' ), $this->fields() )
		);
	}

	public function test_from_form_with_checked_checkbox(): void {
		$this->assertSame( 'yes', Settings::from_form( array( 'flaga' => 'yes' ), $this->fields() )['flaga'] );
	}
}
