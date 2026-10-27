<?php
// phpcs:ignoreFile -- plik testowy.

namespace Rynkowski\Narzedzia\Tests;

use Rynkowski\Narzedzia\Plugin;
use Rynkowski\Narzedzia\Registry;
use Rynkowski\Narzedzia\Uninstaller;

final class UninstallerTest extends TestCase {

	private const FIXTURES = __DIR__ . '/../fixtures/moduly';

	protected function setUp(): void {
		parent::setUp();
		Fake_Module::$log = array();
	}

	public function test_keeps_data_without_owner_consent(): void {
		$this->options[ Registry::OPTION_ENABLED ] = array( 'alfa' => true );

		Uninstaller::run( self::FIXTURES );

		$this->assertSame( array(), Fake_Module::$log );
		$this->assertArrayHasKey( Registry::OPTION_ENABLED, $this->options );
	}

	public function test_keeps_data_when_consent_is_no(): void {
		$this->options[ Plugin::OPTION_DELETE_DATA ] = 'no';

		Uninstaller::run( self::FIXTURES );

		$this->assertSame( array(), Fake_Module::$log );
	}

	public function test_removes_all_module_data_and_settings_with_consent(): void {
		$this->options[ Plugin::OPTION_DELETE_DATA ] = 'yes';
		$this->options[ Registry::OPTION_ENABLED ]   = array( 'alfa' => true );

		Uninstaller::run( self::FIXTURES );

		$this->assertSame( array( 'uninstall:alfa', 'uninstall:beta' ), Fake_Module::$log );
		$this->assertSame( array(), $this->options );
	}
}
