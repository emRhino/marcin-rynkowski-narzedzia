<?php
// phpcs:ignoreFile -- plik testowy.

namespace Rynkowski\Narzedzia\Tests;

use Brain\Monkey\Actions;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use Rynkowski\Narzedzia\Plugin;

final class EntryFilesTest extends TestCase {

	private const ROOT = __DIR__ . '/../..';

	#[RunInSeparateProcess]
	#[PreserveGlobalState( false )]
	public function test_main_file_boots_plugin(): void {
		Actions\expectAdded( 'plugins_loaded' )->twice();
		Actions\expectAdded( 'before_woocommerce_init' )->once();

		require self::ROOT . '/marcin-rynkowski-narzedzia.php';

		$this->assertSame( realpath( self::ROOT . '/marcin-rynkowski-narzedzia.php' ), realpath( Plugin::file() ) );
	}

	#[RunInSeparateProcess]
	#[PreserveGlobalState( false )]
	public function test_uninstall_file_runs_uninstaller(): void {
		define( 'WP_UNINSTALL_PLUGIN', 'marcin-rynkowski-narzedzia/marcin-rynkowski-narzedzia.php' );
		$this->options[ Plugin::OPTION_DELETE_DATA ] = 'no';

		require self::ROOT . '/uninstall.php';

		$this->assertSame( 'no', $this->options[ Plugin::OPTION_DELETE_DATA ] );
	}

	public function test_main_file_header_and_class_versions_match(): void {
		$header = (string) file_get_contents( self::ROOT . '/marcin-rynkowski-narzedzia.php' );

		$this->assertMatchesRegularExpression( '/^ \* Version:\s+' . preg_quote( Plugin::VERSION, '/' ) . '$/m', $header );
	}
}
