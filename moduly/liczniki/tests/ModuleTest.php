<?php
// phpcs:ignoreFile -- plik testowy.

namespace Rynkowski\Narzedzia\Moduly\Liczniki\Tests;

use Brain\Monkey\Actions;
use Brain\Monkey\Functions;
use Rynkowski\Narzedzia\Has_Settings;
use Rynkowski\Narzedzia\Moduly\Liczniki\Module;
use Rynkowski\Narzedzia\Moduly\Liczniki\Counter_Settings;
use Rynkowski\Narzedzia\Moduly\Liczniki\Counters;
use Rynkowski\Narzedzia\Tests\TestCase;

final class ModuleTest extends TestCase {

	public function test_manifest_returns_module(): void {
		$module = require dirname( __DIR__ ) . '/module.php';

		$this->assertInstanceOf( Module::class, $module );
	}

	public function test_describes_itself(): void {
		$module = new Module();

		$this->assertSame( 'liczniki', $module->slug() );
		$this->assertSame( 'Liczniki promocji', $module->name() );
		$this->assertNotSame( '', $module->description() );
		$this->assertSame( array( 'woocommerce' ), $module->requires() );
		$this->assertSame( 1, $module->data_version() );
		$this->assertSame( array(), $module->migrations() );
	}

	public function test_slug_matches_folder(): void {
		$this->assertSame( basename( dirname( __DIR__ ) ), ( new Module() )->slug() );
	}

	public function test_register_hooks_all_parts(): void {
		Actions\expectAdded( 'init' )->once();
		Actions\expectAdded( 'save_post_' . Counters::POST_TYPE )->once();
		Actions\expectAdded( 'woocommerce_single_product_summary' )->once();

		( new Module() )->register();
	}

	public function test_uninstall_deletes_counters_and_settings(): void {
		Functions\expect( 'get_posts' )->once()->with( \Mockery::on( static fn( array $args ): bool => Counters::POST_TYPE === $args['post_type'] ) )->andReturn( array( 4 ) );
		Functions\expect( 'wp_delete_post' )->once()->with( 4, true );
		$this->options[ Counter_Settings::OPTION ] = array( 'odstep' => 8 );

		( new Module() )->uninstall();

		$this->assertArrayNotHasKey( Counter_Settings::OPTION, $this->options );
	}

	public function test_settings_tab_describes_counter_look(): void {
		$module = new Module();

		$this->assertInstanceOf( Has_Settings::class, $module );
		$this->assertSame(
			array(
				'title'  => 'Liczniki promocji',
				'option' => Counter_Settings::OPTION,
				'fields' => Counter_Settings::fields(),
			),
			$module->settings_tab()
		);
	}
}
