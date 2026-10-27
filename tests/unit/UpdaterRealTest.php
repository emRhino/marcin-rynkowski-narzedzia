<?php
// phpcs:ignoreFile -- plik testowy.

namespace Rynkowski\Narzedzia\Tests;

use Brain\Monkey\Filters;
use Brain\Monkey\Functions;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use Rynkowski\Narzedzia\Plugin;
use Rynkowski\Narzedzia\Updater;

/**
 * Prawdziwy Plugin Update Checker, wywołany tak jak przez WordPress.
 *
 * Ten test złapałby błąd z v0.4.0–0.5.0: do_action( 'plugins_loaded' ) przekazuje callbackowi '',
 * a register() przyjmował tylko fabrykę albo null. Testy z podstawioną fabryką tego nie widziały.
 */
final class UpdaterRealTest extends TestCase {

	private const MAIN_FILE = __DIR__ . '/../../marcin-rynkowski-narzedzia.php';

	#[RunInSeparateProcess]
	#[PreserveGlobalState( false )]
	public function test_plugins_loaded_callback_builds_real_update_checker(): void {
		// PUC rozpoznaje wtyczkę po tym, że jej plik leży w katalogu wtyczek.
		define( 'WP_PLUGIN_DIR', dirname( realpath( self::MAIN_FILE ), 2 ) );
		define( 'WPMU_PLUGIN_DIR', '/nie/ma/mu-plugins' );
		define( 'WP_DEBUG', false );
		Functions\when( 'wp_parse_url' )->alias( 'parse_url' );
		Functions\when( 'plugin_basename' )->justReturn( 'marcin-rynkowski-narzedzia/marcin-rynkowski-narzedzia.php' );
		Functions\when( 'wp_next_scheduled' )->justReturn( null );
		Functions\when( 'wp_schedule_event' )->justReturn( null );
		Functions\when( 'register_deactivation_hook' )->justReturn( null );
		Plugin::boot( realpath( self::MAIN_FILE ) );

		// PUC wstrzykuje aktualizację przez ten filtr — znak, że sprawdzacz naprawdę powstał.
		Filters\expectAdded( 'site_transient_update_plugins' )->atLeast()->once();

		// Dokładnie tak woła WordPress: do_action() bez argumentów daje callbackowi ''.
		call_user_func_array( array( Updater::class, 'boot' ), array( '' ) );
	}
}
