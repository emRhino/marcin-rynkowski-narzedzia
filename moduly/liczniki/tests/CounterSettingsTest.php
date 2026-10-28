<?php
// phpcs:ignoreFile -- plik testowy.

namespace Rynkowski\Narzedzia\Moduly\Liczniki\Tests;

use Rynkowski\Narzedzia\Moduly\Liczniki\Counter_Settings;
use Rynkowski\Narzedzia\Settings;
use Rynkowski\Narzedzia\Tests\TestCase;

final class CounterSettingsTest extends TestCase {

	public function test_fields_cover_all_requested_controls(): void {
		$this->assertSame(
			array( 'kolor_tekstu', 'grubosc_tekstu', 'rozmiar_tekstu', 'kolor_cyfr', 'grubosc_cyfr', 'rozmiar_cyfr', 'kolor_ramki', 'kolor_tla', 'padding', 'odstep' ),
			array_column( Counter_Settings::fields(), 'id' )
		);
	}

	public function test_defaults_pass_own_schema(): void {
		foreach ( Counter_Settings::fields() as $field ) {
			$this->assertSame( $field['default'], Settings::sanitize( $field, $field['default'] ), $field['id'] );
		}
	}

	public function test_weights_are_normal_semibold_bold(): void {
		$this->assertSame( array( 400, 600, 700 ), array_keys( Counter_Settings::fields()[1]['options'] ) );
		$this->assertSame( '600', Counter_Settings::get( 'grubosc_tekstu' ) );
	}

	public function test_get_reads_saved_and_rejects_invalid(): void {
		$this->options[ Counter_Settings::OPTION ] = array(
			'grubosc_cyfr' => '700',
			'rozmiar_cyfr' => '200',
			'kolor_ramki'  => '#c9a96e',
			'kolor_tla'    => 'red;}body{',
			'odstep'       => '0',
		);

		$this->assertSame( '700', Counter_Settings::get( 'grubosc_cyfr' ) );
		$this->assertSame( 28, Counter_Settings::get( 'rozmiar_cyfr' ) );
		$this->assertSame( '#c9a96e', Counter_Settings::get( 'kolor_ramki' ) );
		$this->assertSame( '', Counter_Settings::get( 'kolor_tla' ) );
		$this->assertSame( 0, Counter_Settings::get( 'odstep' ) );
	}
}
