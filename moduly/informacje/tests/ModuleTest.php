<?php
// phpcs:ignoreFile -- plik testowy.

namespace Rynkowski\Narzedzia\Moduly\Informacje\Tests;

use Brain\Monkey\Actions;
use Brain\Monkey\Functions;
use Rynkowski\Narzedzia\Has_Settings;
use Rynkowski\Narzedzia\Moduly\Informacje\Info_Frontend;
use Rynkowski\Narzedzia\Moduly\Informacje\Info_Product_Box;
use Rynkowski\Narzedzia\Moduly\Informacje\Info_Row_Admin;
use Rynkowski\Narzedzia\Moduly\Informacje\Info_Rows;
use Rynkowski\Narzedzia\Moduly\Informacje\Info_Settings;
use Rynkowski\Narzedzia\Moduly\Informacje\Module;
use Rynkowski\Narzedzia\Tests\TestCase;

final class ModuleTest extends TestCase {

	public function test_manifest_returns_module(): void {
		$this->assertInstanceOf( Module::class, require dirname( __DIR__ ) . '/module.php' );
	}

	public function test_describes_itself(): void {
		$module = new Module();

		$this->assertSame( 'informacje', $module->slug() );
		$this->assertSame( basename( dirname( __DIR__ ) ), $module->slug() );
		$this->assertSame( 'Informacje o zakupie', $module->name() );
		$this->assertNotSame( '', $module->description() );
		$this->assertSame( array( 'woocommerce' ), $module->requires() );
		$this->assertSame( 1, $module->data_version() );
		$this->assertSame( array(), $module->migrations() );
	}

	public function test_register_on_front_skips_admin(): void {
		Functions\when( 'is_admin' )->justReturn( false );
		Actions\expectAdded( 'init' )->once()->with( array( Info_Rows::class, 'register_post_type' ) );
		Actions\expectAdded( 'woocommerce_single_product_summary' )->once()->with( array( Info_Frontend::class, 'render' ), 35 );
		Actions\expectAdded( 'add_meta_boxes_product' )->never();

		( new Module() )->register();
	}

	public function test_register_in_admin_adds_editors(): void {
		Functions\when( 'is_admin' )->justReturn( true );
		Actions\expectAdded( 'save_post_' . Info_Rows::POST_TYPE )->once()->with( array( Info_Row_Admin::class, 'save' ), 10, 2 );
		Actions\expectAdded( 'add_meta_boxes_product' )->once()->with( array( Info_Product_Box::class, 'add_meta_box' ) );
		Actions\expectAdded( 'save_post_product' )->once()->with( array( Info_Product_Box::class, 'save' ), 10, 2 );

		( new Module() )->register();
	}

	public function test_settings_tab_and_uninstall(): void {
		$module = new Module();

		$this->assertInstanceOf( Has_Settings::class, $module );
		$this->assertSame(
			array( 'title' => 'Informacje o zakupie', 'option' => Info_Settings::OPTION, 'fields' => Info_Settings::fields() ),
			$module->settings_tab()
		);

		$this->options[ Info_Settings::OPTION ] = array( 'sekcja' => 'no' );
		Functions\expect( 'get_posts' )->once()->andReturn( array( 3, 4 ) );
		Functions\expect( 'wp_delete_post' )->once()->with( 3, true );
		Functions\expect( 'wp_delete_post' )->once()->with( 4, true );
		Functions\expect( 'delete_post_meta_by_key' )->once()->with( Info_Rows::META_PRODUCT_TEXTS );

		$module->uninstall();

		$this->assertArrayNotHasKey( Info_Settings::OPTION, $this->options );
	}
}
