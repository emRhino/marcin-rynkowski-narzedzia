<?php
// phpcs:ignoreFile -- plik testowy.

namespace Rynkowski\Narzedzia\Tests;

use Brain\Monkey\Functions;
use Rynkowski\Narzedzia\Registry;

final class RegistryTest extends TestCase {

	private const FIXTURES = __DIR__ . '/../fixtures/moduly';

	protected function setUp(): void {
		parent::setUp();
		Fake_Module::$log = array();
		Functions\when( 'did_action' )->justReturn( 1 );
		Functions\when( 'get_template' )->justReturn( 'astra' );
	}

	private function registry(): Registry {
		$registry = new Registry( self::FIXTURES );
		$registry->discover();
		return $registry;
	}

	public function test_discover_loads_only_real_modules_sorted_by_slug(): void {
		$this->assertSame( array( 'alfa', 'beta' ), array_keys( $this->registry()->all() ) );
	}

	public function test_discover_handles_missing_folder(): void {
		$registry = new Registry( '/nie/ma/takiego/folderu' );
		$registry->discover();

		$this->assertSame( array(), $registry->all() );
	}

	public function test_modules_are_disabled_by_default(): void {
		$this->assertFalse( $this->registry()->is_enabled( 'alfa' ) );
	}

	public function test_broken_option_means_disabled(): void {
		$this->options[ Registry::OPTION_ENABLED ] = 'zepsute';
		$this->assertFalse( $this->registry()->is_enabled( 'alfa' ) );
	}

	public function test_set_enabled_ignores_unknown_slugs(): void {
		$registry = $this->registry();
		$registry->set_enabled( array( 'alfa', 'nieznany' ) );

		$this->assertSame( array( 'alfa' => true ), $this->options[ Registry::OPTION_ENABLED ] );
		$this->assertTrue( $registry->is_enabled( 'alfa' ) );
		$this->assertFalse( $registry->is_enabled( 'beta' ) );
	}

	public function test_boot_registers_enabled_modules_with_dependencies(): void {
		$registry = $this->registry();
		$registry->set_enabled( array( 'alfa', 'beta' ) );
		$registry->boot();

		$this->assertSame( array( 'register:alfa' ), Fake_Module::$log );
		$this->assertSame( array( 'beta' => array( 'Blocksy' ) ), $registry->missing() );
		// Aktywne są tylko moduły, które naprawdę wystartowały.
		$this->assertSame( array( 'alfa' ), array_keys( $registry->active() ) );
	}

	public function test_boot_skips_disabled_modules(): void {
		$registry = $this->registry();
		$registry->boot();

		$this->assertSame( array(), Fake_Module::$log );
		$this->assertSame( array(), $registry->missing() );
		$this->assertSame( array(), $registry->active() );
	}

	public function test_first_run_sets_latest_version_without_migrations(): void {
		$ran      = false;
		$module   = new Fake_Module( 'gamma', array(), 3, array( 2 => function () use ( &$ran ) { $ran = true; } ) );
		$registry = new Registry( self::FIXTURES );

		$registry->migrate( $module );

		$this->assertFalse( $ran );
		$this->assertSame( array( 'gamma' => 3 ), $this->options[ Registry::OPTION_VERSIONS ] );
	}

	public function test_migrations_run_in_order_from_stored_version(): void {
		$order = array();
		$module = new Fake_Module(
			'gamma',
			array(),
			4,
			array(
				2 => function () use ( &$order ) { $order[] = 2; },
				4 => function () use ( &$order ) { $order[] = 4; },
				3 => function () use ( &$order ) { $order[] = 3; },
			)
		);
		$this->options[ Registry::OPTION_VERSIONS ] = array( 'gamma' => 2 );

		( new Registry( self::FIXTURES ) )->migrate( $module );

		$this->assertSame( array( 3, 4 ), $order );
		$this->assertSame( array( 'gamma' => 4 ), $this->options[ Registry::OPTION_VERSIONS ] );
	}

	public function test_missing_migration_step_still_raises_version(): void {
		$this->options[ Registry::OPTION_VERSIONS ] = array( 'gamma' => 1 );

		( new Registry( self::FIXTURES ) )->migrate( new Fake_Module( 'gamma', array(), 2 ) );

		$this->assertSame( array( 'gamma' => 2 ), $this->options[ Registry::OPTION_VERSIONS ] );
	}

	public function test_broken_versions_option_is_treated_as_empty(): void {
		$this->options[ Registry::OPTION_VERSIONS ] = 'zepsute';

		( new Registry( self::FIXTURES ) )->migrate( new Fake_Module( 'gamma', array(), 2 ) );

		$this->assertSame( array( 'gamma' => 2 ), $this->options[ Registry::OPTION_VERSIONS ] );
	}

	public function test_failed_migration_keeps_last_finished_version(): void {
		$module = new Fake_Module(
			'gamma',
			array(),
			3,
			array(
				2 => function () {},
				3 => function () { throw new \RuntimeException( 'błąd migracji' ); },
			)
		);
		$this->options[ Registry::OPTION_VERSIONS ] = array( 'gamma' => 1 );

		try {
			( new Registry( self::FIXTURES ) )->migrate( $module );
			$this->fail( 'Migracja powinna rzucić wyjątek.' );
		} catch ( \RuntimeException $e ) {
			$this->assertSame( array( 'gamma' => 2 ), $this->options[ Registry::OPTION_VERSIONS ] );
		}
	}

	public function test_uninstall_all_cleans_modules_and_options(): void {
		$registry = $this->registry();
		$registry->set_enabled( array( 'alfa' ) );
		$this->options[ Registry::OPTION_VERSIONS ] = array( 'alfa' => 1 );

		$registry->uninstall_all();

		$this->assertSame( array( 'uninstall:alfa', 'uninstall:beta' ), Fake_Module::$log );
		$this->assertArrayNotHasKey( Registry::OPTION_ENABLED, $this->options );
		$this->assertArrayNotHasKey( Registry::OPTION_VERSIONS, $this->options );
	}
}
