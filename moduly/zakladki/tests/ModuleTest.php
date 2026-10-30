<?php
// phpcs:ignoreFile -- plik testowy.

namespace Rynkowski\Narzedzia\Moduly\Zakladki\Tests;

use Brain\Monkey\Actions;
use Brain\Monkey\Filters;
use Brain\Monkey\Functions;
use Rynkowski\Narzedzia\Has_Settings;
use Rynkowski\Narzedzia\Moduly\Zakladki\Module;
use Rynkowski\Narzedzia\Moduly\Zakladki\Product_Tabs;
use Rynkowski\Narzedzia\Moduly\Zakladki\Tab_Editor;
use Rynkowski\Narzedzia\Moduly\Zakladki\Tab_Settings;
use Rynkowski\Narzedzia\Tests\TestCase;

final class ModuleTest extends TestCase {

	public function test_manifest_returns_module(): void {
		$this->assertInstanceOf( Module::class, require dirname( __DIR__ ) . '/module.php' );
	}

	public function test_describes_itself(): void {
		$module = new Module();

		$this->assertSame( 'zakladki', $module->slug() );
		$this->assertSame( basename( dirname( __DIR__ ) ), $module->slug() );
		$this->assertSame( 'Zakładki produktu', $module->name() );
		$this->assertNotSame( '', $module->description() );
		$this->assertSame( array( 'woocommerce' ), $module->requires() );
		$this->assertSame( 1, $module->data_version() );
		$this->assertSame( array(), $module->migrations() );
	}

	public function test_register_hooks_editor_and_tabs(): void {
		Actions\expectAdded( 'add_meta_boxes_product' )->once()->with( array( Tab_Editor::class, 'add_meta_boxes' ) );
		Actions\expectAdded( 'save_post_product' )->once()->with( array( Tab_Editor::class, 'save' ), 10, 2 );
		Filters\expectAdded( 'woocommerce_product_tabs' )->once()->with( array( Product_Tabs::class, 'add_tabs' ) );

		( new Module() )->register();
	}

	public function test_settings_tab(): void {
		$module = new Module();

		$this->assertInstanceOf( Has_Settings::class, $module );
		$this->assertSame(
			array( 'title' => 'Zakładki produktu', 'option' => Tab_Settings::OPTION, 'fields' => Tab_Settings::fields() ),
			$module->settings_tab()
		);
	}

	public function test_uninstall_deletes_content_of_all_tabs_and_settings(): void {
		// Także treści wyłączonych zakładek.
		$this->options[ Tab_Settings::OPTION ] = array( 'cechy' => 'no' );
		Functions\expect( 'delete_post_meta_by_key' )->once()->with( '_rynkowski_zakladki_pielegnacja' );
		Functions\expect( 'delete_post_meta_by_key' )->once()->with( '_rynkowski_zakladki_cechy' );
		Functions\expect( 'delete_post_meta_by_key' )->once()->with( '_rynkowski_zakladki_bezpieczenstwo' );

		( new Module() )->uninstall();

		$this->assertArrayNotHasKey( Tab_Settings::OPTION, $this->options );
	}
}
