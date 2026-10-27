<?php
// phpcs:ignoreFile -- plik testowy.

namespace Rynkowski\Narzedzia\Moduly\Kafle\Tests;

use Rynkowski\Narzedzia\Moduly\Kafle\Tile_Settings;
use Rynkowski\Narzedzia\Settings;
use Rynkowski\Narzedzia\Tests\TestCase;

final class TileSettingsTest extends TestCase {

	public function test_fields_are_height_color_and_autostart(): void {
		$this->assertSame( array( 'min_wysokosc', 'kolor_tla', 'autostart' ), array_column( Tile_Settings::fields(), 'id' ) );
	}

	public function test_defaults_match_previous_behaviour_and_own_schema(): void {
		foreach ( Tile_Settings::fields() as $field ) {
			$this->assertSame( $field['default'], Settings::sanitize( $field, $field['default'] ), $field['id'] );
		}

		// 288 px = dawne 18rem przy 16 px; puste tło = kolor motywu; wideo startuje samo.
		$this->assertSame( 288, Tile_Settings::get( 'min_wysokosc' ) );
		$this->assertSame( '', Tile_Settings::get( 'kolor_tla' ) );
		$this->assertSame( 'yes', Tile_Settings::get( 'autostart' ) );
	}

	public function test_get_reads_saved_and_rejects_invalid(): void {
		$this->options[ Tile_Settings::OPTION ] = array(
			'min_wysokosc' => '50',
			'kolor_tla'    => 'red;}body{',
			'autostart'    => 'no',
		);

		$this->assertSame( 288, Tile_Settings::get( 'min_wysokosc' ) );
		$this->assertSame( '', Tile_Settings::get( 'kolor_tla' ) );
		$this->assertSame( 'no', Tile_Settings::get( 'autostart' ) );

		$this->options[ Tile_Settings::OPTION ] = array( 'min_wysokosc' => '400', 'kolor_tla' => '#aabbcc' );

		$this->assertSame( 400, Tile_Settings::get( 'min_wysokosc' ) );
		$this->assertSame( '#aabbcc', Tile_Settings::get( 'kolor_tla' ) );
	}
}
