<?php
// phpcs:ignoreFile -- plik testowy.

namespace Rynkowski\Narzedzia\Moduly\Kafle\Tests;

use Brain\Monkey\Actions;
use Brain\Monkey\Functions;
use Rynkowski\Narzedzia\Moduly\Kafle\Module;
use Rynkowski\Narzedzia\Moduly\Kafle\Tiles;
use Rynkowski\Narzedzia\Tests\TestCase;

final class ModuleTest extends TestCase {

	public function test_manifest_returns_module(): void {
		$module = require dirname( __DIR__ ) . '/module.php';

		$this->assertInstanceOf( Module::class, $module );
	}

	public function test_describes_itself(): void {
		$module = new Module();

		$this->assertSame( 'kafle', $module->slug() );
		$this->assertSame( 'Kafle promocyjne', $module->name() );
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
		Actions\expectAdded( 'save_post_' . Tiles::POST_TYPE )->once();
		Actions\expectAdded( 'woocommerce_shop_loop' )->once();

		( new Module() )->register();
	}

	public function test_uninstall_deletes_tiles(): void {
		Functions\expect( 'get_posts' )->once()->with( \Mockery::on( static fn( array $args ): bool => Tiles::POST_TYPE === $args['post_type'] ) )->andReturn( array( 4 ) );
		Functions\expect( 'wp_delete_post' )->once()->with( 4, true );

		( new Module() )->uninstall();
	}
}
