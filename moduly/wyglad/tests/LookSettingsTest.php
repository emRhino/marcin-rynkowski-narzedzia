<?php
// phpcs:ignoreFile -- plik testowy.

namespace Rynkowski\Narzedzia\Moduly\Wyglad\Tests;

use Rynkowski\Narzedzia\Moduly\Wyglad\Look_Settings;
use Rynkowski\Narzedzia\Settings;
use Rynkowski\Narzedzia\Tests\TestCase;

final class LookSettingsTest extends TestCase {

	public function test_switches_are_checkboxes_on_by_default(): void {
		$fields = Look_Settings::fields();

		$this->assertSame( array( 'breadcrumb_w_linii', 'zdjecie_pelna_szerokosc' ), array_column( $fields, 'id' ) );
		$this->assertSame( 'Breadcrumb w linii', $fields[0]['title'] );
		$this->assertSame( 'Zdjęcie produktu na całą szerokość', $fields[1]['title'] );
		foreach ( $fields as $field ) {
			$this->assertSame( 'checkbox', $field['type'] );
			$this->assertSame( 'yes', Settings::sanitize( $field, $field['default'] ) );
			$this->assertSame( 'yes', Look_Settings::get( $field['id'] ) );
		}
	}

	public function test_saved_value_and_garbage(): void {
		$this->options[ Look_Settings::OPTION ] = array( 'breadcrumb_w_linii' => 'no' );
		$this->assertSame( 'no', Look_Settings::get( 'breadcrumb_w_linii' ) );
		// Przełączniki są niezależne.
		$this->assertSame( 'yes', Look_Settings::get( 'zdjecie_pelna_szerokosc' ) );

		$this->options[ Look_Settings::OPTION ] = array( 'breadcrumb_w_linii' => '<script>' );
		$this->assertSame( 'no', Look_Settings::get( 'breadcrumb_w_linii' ) );
	}
}
