<?php
// phpcs:ignoreFile -- plik testowy.

namespace Rynkowski\Narzedzia\Moduly\Informacje\Tests;

use Rynkowski\Narzedzia\Moduly\Informacje\Info_Settings;
use Rynkowski\Narzedzia\Settings;
use Rynkowski\Narzedzia\Tests\TestCase;

final class InfoSettingsTest extends TestCase {

	public function test_schema_and_defaults(): void {
		$fields = Info_Settings::fields();

		$this->assertSame( array( 'sekcja', 'kolor_wyroznienia', 'kolor_ramki', 'rozmiar_ikony', 'padding', 'odstep' ), array_column( $fields, 'id' ) );
		$this->assertSame( array( 'checkbox', 'color', 'color', 'number', 'number', 'number' ), array_column( $fields, 'type' ) );
		foreach ( $fields as $field ) {
			$this->assertSame( $field['default'], Settings::sanitize( $field, $field['default'] ) );
			$this->assertSame( $field['default'], Info_Settings::get( $field['id'] ) );
		}
		$this->assertSame( 'yes', Info_Settings::get( 'sekcja' ) );
		$this->assertSame( 20, Info_Settings::get( 'rozmiar_ikony' ) );
	}

	public function test_saved_values_and_garbage(): void {
		$this->options[ Info_Settings::OPTION ] = array(
			'sekcja'            => 'no',
			'kolor_wyroznienia' => '#c00',
			'kolor_ramki'       => 'red;}body{display:none',
			'rozmiar_ikony'     => 500,
		);

		$this->assertSame( 'no', Info_Settings::get( 'sekcja' ) );
		$this->assertSame( '#c00', Info_Settings::get( 'kolor_wyroznienia' ) );
		$this->assertSame( '', Info_Settings::get( 'kolor_ramki' ) );
		$this->assertSame( 20, Info_Settings::get( 'rozmiar_ikony' ) );
	}
}
