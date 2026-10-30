<?php
// phpcs:ignoreFile -- plik testowy.

namespace Rynkowski\Narzedzia\Moduly\Zakladki\Tests;

use Rynkowski\Narzedzia\Moduly\Zakladki\Product_Tabs;
use Rynkowski\Narzedzia\Moduly\Zakladki\Tab_Settings;
use Rynkowski\Narzedzia\Settings;
use Rynkowski\Narzedzia\Tests\TestCase;

final class TabSettingsTest extends TestCase {

	public function test_switch_per_tab_on_by_default(): void {
		$fields = Tab_Settings::fields();

		$this->assertSame( array( 'pielegnacja', 'cechy', 'bezpieczenstwo' ), array_column( $fields, 'id' ) );
		$this->assertSame( array_values( Product_Tabs::labels() ), array_column( $fields, 'title' ) );
		foreach ( $fields as $field ) {
			$this->assertSame( 'checkbox', $field['type'] );
			$this->assertSame( 'yes', Settings::sanitize( $field, $field['default'] ) );
			$this->assertNotSame( '', $field['desc'] );
			$this->assertTrue( Tab_Settings::is_enabled( $field['id'] ) );
		}
		$this->assertSame( Product_Tabs::labels(), Product_Tabs::enabled() );
	}

	public function test_only_safety_enabled(): void {
		$this->options[ Tab_Settings::OPTION ] = array( 'pielegnacja' => 'no', 'cechy' => 'no', 'bezpieczenstwo' => 'yes' );

		$this->assertFalse( Tab_Settings::is_enabled( 'pielegnacja' ) );
		$this->assertSame( array( 'bezpieczenstwo' => 'Bezpieczeństwo produktu' ), Product_Tabs::enabled() );
	}

	public function test_garbage_value_counts_as_off(): void {
		$this->options[ Tab_Settings::OPTION ] = array( 'cechy' => '<script>' );

		$this->assertFalse( Tab_Settings::is_enabled( 'cechy' ) );
		$this->assertTrue( Tab_Settings::is_enabled( 'pielegnacja' ) );
	}
}
