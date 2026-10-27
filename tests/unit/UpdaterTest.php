<?php
// phpcs:ignoreFile -- plik testowy.

namespace Rynkowski\Narzedzia\Tests;

use Brain\Monkey\Functions;
use Rynkowski\Narzedzia\Plugin;
use Rynkowski\Narzedzia\Updater;

final class UpdaterTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		Plugin::boot( '/plugins/marcin-rynkowski-narzedzia/marcin-rynkowski-narzedzia.php' );
	}

	public function test_builds_checker_for_repo_and_uses_release_zip(): void {
		$api = new class() {
			public ?string $pattern = null;

			public function enableReleaseAssets( string $pattern ): void {
				$this->pattern = $pattern;
			}
		};
		$args = array();

		Updater::register(
			function ( ...$received ) use ( &$args, $api ) {
				$args = $received;
				return new class( $api ) {
					public function __construct( private object $api ) {}

					public function getVcsApi(): object {
						return $this->api;
					}
				};
			}
		);

		$this->assertSame(
			array( Updater::REPO, '/plugins/marcin-rynkowski-narzedzia/marcin-rynkowski-narzedzia.php', 'marcin-rynkowski-narzedzia' ),
			$args
		);
		$this->assertSame( '/marcin-rynkowski-narzedzia\.zip$/', $api->pattern );
		$this->assertSame( 1, preg_match( $api->pattern, 'https://github.com/x/releases/download/v1/marcin-rynkowski-narzedzia.zip' ) );
		$this->assertSame( 0, preg_match( $api->pattern, 'https://github.com/x/archive/v1.zip' ) );
	}

	public function test_skips_release_assets_when_api_does_not_support_them(): void {
		$api = new \stdClass();

		Updater::register(
			fn() => new class( $api ) {
				public function __construct( private object $api ) {}

				public function getVcsApi(): object {
					return $this->api;
				}
			}
		);

		$this->assertSame( array(), get_object_vars( $api ) );
	}
}
