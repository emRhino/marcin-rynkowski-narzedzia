<?php
// phpcs:ignoreFile -- plik testowy.

namespace Rynkowski\Narzedzia\Moduly\Wstazki\Tests;

use Brain\Monkey\Actions;
use Brain\Monkey\Filters;
use Brain\Monkey\Functions;
use Rynkowski\Narzedzia\Has_Settings;
use Rynkowski\Narzedzia\Moduly\Wstazki\Module;
use Rynkowski\Narzedzia\Moduly\Wstazki\Ribbon_Settings;
use Rynkowski\Narzedzia\Moduly\Wstazki\Ribbons;
use Rynkowski\Narzedzia\Tests\TestCase;

final class ModuleTest extends TestCase {

	public function test_manifest_returns_module(): void {
		$module = require dirname( __DIR__ ) . '/module.php';

		$this->assertInstanceOf( Module::class, $module );
	}

	public function test_describes_itself(): void {
		$module = new Module();

		$this->assertSame( 'wstazki', $module->slug() );
		$this->assertSame( 'Wstążki', $module->name() );
		$this->assertNotSame( '', $module->description() );
		$this->assertSame( array( 'woocommerce', 'blocksy' ), $module->requires() );
		$this->assertSame( 1, $module->data_version() );
		$this->assertSame( array(), $module->migrations() );
	}

	public function test_slug_matches_folder(): void {
		$this->assertSame( basename( dirname( __DIR__ ) ), ( new Module() )->slug() );
	}

	public function test_register_hooks_all_parts(): void {
		Actions\expectAdded( 'init' )->once();
		Actions\expectAdded( 'save_post_' . Ribbons::POST_TYPE )->once();
		Filters\expectAdded( 'woocommerce_product_data_tabs' )->once();
		Filters\expectAdded( 'blocksy:woocommerce:product-card:badges' )->once();

		( new Module() )->register();
	}

	public function test_uninstall_deletes_ribbons_and_settings(): void {
		Functions\expect( 'get_posts' )->once()->with( \Mockery::on( static fn( array $args ): bool => Ribbons::POST_TYPE === $args['post_type'] ) )->andReturn( array( 4 ) );
		Functions\expect( 'wp_delete_post' )->once()->with( 4, true );
		$this->options[ Ribbon_Settings::OPTION ] = array( 'odstep' => 8 );

		( new Module() )->uninstall();

		$this->assertArrayNotHasKey( Ribbon_Settings::OPTION, $this->options );
	}

	public function test_settings_tab_describes_ribbon_settings(): void {
		$module = new Module();

		$this->assertInstanceOf( Has_Settings::class, $module );
		$this->assertSame(
			array(
				'title'  => 'Wstążki',
				'option' => Ribbon_Settings::OPTION,
				'fields' => Ribbon_Settings::fields(),
			),
			$module->settings_tab()
		);
	}
}
