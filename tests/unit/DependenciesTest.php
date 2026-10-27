<?php
// phpcs:ignoreFile -- plik testowy.

namespace Rynkowski\Narzedzia\Tests;

use Brain\Monkey\Functions;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use Rynkowski\Narzedzia\Dependencies;

final class DependenciesTest extends TestCase {

	public function test_woocommerce_is_met_after_its_loaded_action(): void {
		Functions\expect( 'did_action' )->with( 'woocommerce_loaded' )->andReturn( 1, 0 );

		$this->assertTrue( Dependencies::is_met( 'woocommerce' ) );
		$this->assertFalse( Dependencies::is_met( 'woocommerce' ) );
	}

	public function test_blocksy_is_met_for_parent_template(): void {
		Functions\when( 'get_template' )->justReturn( 'blocksy' );
		$this->assertTrue( Dependencies::is_met( 'blocksy' ) );
	}

	public function test_blocksy_is_not_met_for_other_theme(): void {
		Functions\when( 'get_template' )->justReturn( 'twentytwentyfive' );
		$this->assertFalse( Dependencies::is_met( 'blocksy' ) );
	}

	public function test_stackable_is_not_met_without_constant(): void {
		$this->assertFalse( Dependencies::is_met( 'stackable' ) );
	}

	#[RunInSeparateProcess]
	#[PreserveGlobalState( false )]
	public function test_stackable_is_met_with_constant(): void {
		define( 'STACKABLE_VERSION', '3.0.0' );
		$this->assertTrue( Dependencies::is_met( 'stackable' ) );
	}

	public function test_unknown_dependency_is_never_met(): void {
		$this->assertFalse( Dependencies::is_met( 'nieznana' ) );
	}

	public function test_missing_returns_labels_and_unknown_keys(): void {
		Functions\when( 'did_action' )->justReturn( 1 );
		Functions\when( 'get_template' )->justReturn( 'astra' );

		$this->assertSame(
			array( 'Blocksy', 'nieznana' ),
			Dependencies::missing( array( 'woocommerce', 'blocksy', 'nieznana' ) )
		);
	}
}
