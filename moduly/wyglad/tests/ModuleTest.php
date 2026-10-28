<?php
// phpcs:ignoreFile -- plik testowy.

namespace Rynkowski\Narzedzia\Moduly\Wyglad\Tests;

use Brain\Monkey\Actions;
use Rynkowski\Narzedzia\Has_Settings;
use Rynkowski\Narzedzia\Moduly\Wyglad\Breadcrumbs;
use Rynkowski\Narzedzia\Moduly\Wyglad\Look_Settings;
use Rynkowski\Narzedzia\Moduly\Wyglad\Module;
use Rynkowski\Narzedzia\Moduly\Wyglad\Product_Image;
use Rynkowski\Narzedzia\Tests\TestCase;

final class ModuleTest extends TestCase {

	public function test_manifest_returns_module(): void {
		$this->assertInstanceOf( Module::class, require dirname( __DIR__ ) . '/module.php' );
	}

	public function test_describes_itself(): void {
		$module = new Module();

		$this->assertSame( 'wyglad', $module->slug() );
		$this->assertSame( basename( dirname( __DIR__ ) ), $module->slug() );
		$this->assertSame( 'Poprawki wyglądu', $module->name() );
		$this->assertNotSame( '', $module->description() );
		$this->assertSame( array( 'blocksy' ), $module->requires() );
		$this->assertSame( 1, $module->data_version() );
		$this->assertSame( array(), $module->migrations() );
	}

	public function test_register_hooks_breadcrumbs_and_product_image(): void {
		Actions\expectAdded( 'wp_enqueue_scripts' )->once()->with( array( Breadcrumbs::class, 'enqueue' ) );
		Actions\expectAdded( 'wp_enqueue_scripts' )->once()->with( array( Product_Image::class, 'enqueue' ) );

		( new Module() )->register();
	}

	public function test_settings_tab_and_uninstall(): void {
		$module = new Module();

		$this->assertInstanceOf( Has_Settings::class, $module );
		$this->assertSame(
			array( 'title' => 'Poprawki wyglądu', 'option' => Look_Settings::OPTION, 'fields' => Look_Settings::fields() ),
			$module->settings_tab()
		);

		$this->options[ Look_Settings::OPTION ] = array( 'breadcrumb_w_linii' => 'no' );
		$module->uninstall();
		$this->assertArrayNotHasKey( Look_Settings::OPTION, $this->options );
	}
}
