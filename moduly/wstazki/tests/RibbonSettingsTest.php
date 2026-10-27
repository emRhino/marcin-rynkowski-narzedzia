<?php
// phpcs:ignoreFile -- plik testowy.

namespace Rynkowski\Narzedzia\Moduly\Wstazki\Tests;

use Rynkowski\Narzedzia\Moduly\Wstazki\Ribbon_Settings;
use Rynkowski\Narzedzia\Settings;
use Rynkowski\Narzedzia\Tests\TestCase;

final class RibbonSettingsTest extends TestCase {

	public function test_fields_are_gap_and_shape(): void {
		$this->assertSame( array( 'odstep', 'ksztalt' ), array_column( Ribbon_Settings::fields(), 'id' ) );
	}

	public function test_defaults_match_previous_behaviour_and_own_schema(): void {
		foreach ( Ribbon_Settings::fields() as $field ) {
			$this->assertSame( $field['default'], Settings::sanitize( $field, $field['default'] ), $field['id'] );
		}

		$this->assertSame( 5, Ribbon_Settings::get( 'odstep' ) );
		$this->assertSame( 'blocksy', Ribbon_Settings::get( 'ksztalt' ) );
	}

	public function test_shapes_are_blocksy_or_its_three_types(): void {
		$this->assertSame( array( 'blocksy', 'type-1', 'type-2', 'type-3' ), array_keys( Ribbon_Settings::fields()[1]['options'] ) );
	}

	public function test_get_reads_saved_and_rejects_invalid(): void {
		$this->options[ Ribbon_Settings::OPTION ] = array( 'odstep' => '12', 'ksztalt' => 'type-9' );

		$this->assertSame( 12, Ribbon_Settings::get( 'odstep' ) );
		$this->assertSame( 'blocksy', Ribbon_Settings::get( 'ksztalt' ) );
	}

	public function test_gap_is_limited_to_0_40(): void {
		$this->options[ Ribbon_Settings::OPTION ] = array( 'odstep' => '41' );
		$this->assertSame( 5, Ribbon_Settings::get( 'odstep' ) );

		$this->options[ Ribbon_Settings::OPTION ] = array( 'odstep' => '0' );
		$this->assertSame( 0, Ribbon_Settings::get( 'odstep' ) );
	}
}
