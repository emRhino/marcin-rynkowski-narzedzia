<?php
// phpcs:ignoreFile -- plik testowy.

namespace Rynkowski\Narzedzia\Tests;

use Automattic\WooCommerce\Utilities\FeaturesUtil;
use Brain\Monkey\Actions;
use Brain\Monkey\Functions;
use Rynkowski\Narzedzia\Plugin;
use Rynkowski\Narzedzia\Registry;
use Rynkowski\Narzedzia\Settings_Page;
use Rynkowski\Narzedzia\Updater;

final class PluginTest extends TestCase {

	private const MAIN_FILE = '/var/www/wp-content/plugins/marcin-rynkowski-narzedzia/marcin-rynkowski-narzedzia.php';

	private function boot( string $file = self::MAIN_FILE ): void {
		Plugin::boot( $file );
	}

	public function test_boot_remembers_file_and_hooks_core(): void {
		Actions\expectAdded( 'before_woocommerce_init' )->once()->with( array( Plugin::class, 'declare_hpos' ) );
		Actions\expectAdded( 'plugins_loaded' )->once()->with( array( Updater::class, 'boot' ), 0 );
		Actions\expectAdded( 'plugins_loaded' )->once()->with( array( Plugin::class, 'init' ) );

		Plugin::boot( self::MAIN_FILE );

		$this->assertSame( self::MAIN_FILE, Plugin::file() );
		$this->assertSame( '/var/www/wp-content/plugins/marcin-rynkowski-narzedzia/', Plugin::dir() );
	}

	public function test_declare_hpos_compatibility(): void {
		$this->boot();
		FeaturesUtil::$calls = array();

		Plugin::declare_hpos();

		$this->assertSame( array( array( 'custom_order_tables', self::MAIN_FILE, true ) ), FeaturesUtil::$calls );
	}

	public function test_init_discovers_real_modules_and_hooks_modules_page(): void {
		$this->boot( dirname( __DIR__, 2 ) . '/marcin-rynkowski-narzedzia.php' );
		Functions\expect( 'load_plugin_textdomain' )->once()->with( 'marcin-rynkowski-narzedzia', false, 'marcin-rynkowski-narzedzia/languages' );
		Functions\when( 'plugin_basename' )->justReturn( 'marcin-rynkowski-narzedzia/marcin-rynkowski-narzedzia.php' );
		Actions\expectAdded( 'admin_menu' )->once()->with( \Mockery::on( fn( $cb ) => $cb[0] instanceof Settings_Page ), 9 );
		Actions\expectAdded( 'admin_notices' )->never();

		Plugin::init();
	}

	public function test_init_shows_notice_for_enabled_module_without_dependencies(): void {
		$this->boot( dirname( __DIR__, 2 ) . '/marcin-rynkowski-narzedzia.php' );
		Functions\when( 'load_plugin_textdomain' )->justReturn( true );
		Functions\when( 'plugin_basename' )->justReturn( 'x/y.php' );
		Functions\when( 'did_action' )->justReturn( 1 );
		Functions\when( 'get_template' )->justReturn( 'astra' );
		Functions\when( 'current_user_can' )->justReturn( true );
		$this->options[ Registry::OPTION_ENABLED ] = array( 'wstazki' => true );
		$notice = null;
		Actions\expectAdded( 'admin_notices' )->once()->whenHappen(
			function ( $callback ) use ( &$notice ) {
				$notice = $callback;
			}
		);

		Plugin::init();

		$this->assertIsCallable( $notice );
		$this->assertSame(
			'<div class="notice notice-error"><p>Moduł „Wstążki” nie działa, bo brakuje aktywnych: Blocksy.</p></div>',
			$this->capture( $notice )
		);
	}

	public function test_missing_notice_hidden_from_users_without_capability(): void {
		Functions\when( 'current_user_can' )->justReturn( false );

		$html = $this->capture( fn() => Plugin::render_missing_notice( new Registry( '/brak' ), array( 'x' => array( 'Blocksy' ) ) ) );

		$this->assertSame( '', $html );
	}

	public function test_missing_notice_falls_back_to_slug_and_escapes(): void {
		Functions\when( 'current_user_can' )->justReturn( true );

		$html = $this->capture( fn() => Plugin::render_missing_notice( new Registry( '/brak' ), array( '<i>x</i>' => array( 'A', 'B' ) ) ) );

		$this->assertSame( '<div class="notice notice-error"><p>Moduł „&lt;i&gt;x&lt;/i&gt;” nie działa, bo brakuje aktywnych: A, B.</p></div>', $html );
	}
}
