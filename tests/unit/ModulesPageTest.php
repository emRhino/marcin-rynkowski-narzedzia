<?php
// phpcs:ignoreFile -- plik testowy.

namespace Rynkowski\Narzedzia\Tests;

use Brain\Monkey\Actions;
use Brain\Monkey\Functions;
use Rynkowski\Narzedzia\Modules_Page;
use Rynkowski\Narzedzia\Plugin;
use Rynkowski\Narzedzia\Registry;

final class ModulesPageTest extends TestCase {

	private Registry $registry;
	private Modules_Page $page;

	protected function setUp(): void {
		parent::setUp();
		Functions\when( 'did_action' )->justReturn( 1 );
		Functions\when( 'get_template' )->justReturn( 'astra' );
		Functions\when( 'admin_url' )->alias( static fn( string $path = '' ): string => 'https://sklep.test/wp-admin/' . $path );

		$this->registry = new Registry( __DIR__ . '/../fixtures/moduly' );
		$this->registry->discover();
		$this->page = new Modules_Page( $this->registry );
	}

	public function test_register_hooks_menu_and_save(): void {
		Actions\expectAdded( 'admin_menu' )->once()->with( array( $this->page, 'add_page' ), 99 );
		Actions\expectAdded( 'admin_post_' . Modules_Page::ACTION )->once()->with( array( $this->page, 'save' ) );

		$this->page->register();
	}

	public function test_add_page_lives_under_woocommerce_for_shop_managers(): void {
		Functions\expect( 'add_submenu_page' )
			->once()
			->with( 'woocommerce', \Mockery::type( 'string' ), 'Narzędzia', 'manage_woocommerce', Modules_Page::SLUG, array( $this->page, 'render' ) );

		$this->page->add_page();
	}

	public function test_render_requires_capability(): void {
		Functions\when( 'current_user_can' )->justReturn( false );

		$this->assertSame( '', $this->capture( fn() => $this->page->render() ) );
	}

	public function test_render_lists_modules_state_and_missing_dependencies(): void {
		Functions\when( 'current_user_can' )->justReturn( true );
		Functions\expect( 'wp_nonce_field' )->once()->with( Modules_Page::ACTION );
		Functions\when( 'submit_button' )->justEcho( '<button>Zapisz</button>' );
		$this->registry->set_enabled( array( 'beta' ) );
		$this->registry->boot();
		$this->options[ Plugin::OPTION_DELETE_DATA ] = 'yes';
		$_GET['zapisano'] = '1';

		$html = $this->capture( fn() => $this->page->render() );

		$this->assertStringContainsString( 'Zapisano ustawienia.', $html );
		$this->assertStringContainsString( 'action="https://sklep.test/wp-admin/admin-post.php"', $html );
		$this->assertMatchesRegularExpression( '/value="alfa"\s*>/', $html );
		$this->assertMatchesRegularExpression( '/value="beta"\s+checked=\'checked\'>/', $html );
		$this->assertStringContainsString( 'Nie działa, brakuje: Blocksy.', $html );
		$this->assertMatchesRegularExpression( '/<td>\s*Blocksy\s*<\/td>/', $html );
		$this->assertMatchesRegularExpression( '/name="usun_dane" value="yes"\s+checked=\'checked\'>/', $html );
	}

	public function test_render_without_message_and_with_unknown_dependency_key(): void {
		Functions\when( 'current_user_can' )->justReturn( true );
		Functions\when( 'wp_nonce_field' )->justReturn( '' );
		Functions\when( 'submit_button' )->justReturn( null );
		$registry = new Registry( '/brak' );
		$registry_modules = new \ReflectionProperty( Registry::class, 'modules' );
		$registry_modules->setValue( $registry, array( 'x' => new Fake_Module( 'x', array( 'nieznana' ) ) ) );

		$html = $this->capture( fn() => ( new Modules_Page( $registry ) )->render() );

		$this->assertStringNotContainsString( 'Zapisano ustawienia.', $html );
		$this->assertStringContainsString( 'nieznana', $html );
		$this->assertStringNotContainsString( 'Nie działa', $html );
	}

	public function test_save_rejects_users_without_capability(): void {
		Functions\when( 'current_user_can' )->justReturn( false );
		// Prawdziwe wp_die() kończy żądanie; tu rzuca wyjątek, żeby to sprawdzić.
		Functions\expect( 'wp_die' )->once()->andThrow( new \RuntimeException( 'wp_die' ) );
		Functions\expect( 'check_admin_referer' )->never();
		$_POST['moduly'] = array( 'alfa' );

		try {
			$this->page->save();
			$this->fail( 'save() powinno zakończyć się na wp_die().' );
		} catch ( \RuntimeException $e ) {
			$this->assertSame( 'wp_die', $e->getMessage() );
		}

		$this->assertArrayNotHasKey( Registry::OPTION_ENABLED, $this->options );
	}

	public function test_save_checks_nonce_stores_choice_and_redirects(): void {
		Functions\when( 'current_user_can' )->justReturn( true );
		Functions\expect( 'check_admin_referer' )->once()->with( Modules_Page::ACTION );
		Functions\when( 'add_query_arg' )->alias( static fn( array $args, string $url ): string => $url . '?' . http_build_query( $args ) );
		Functions\expect( 'wp_safe_redirect' )->once()->with( 'https://sklep.test/wp-admin/admin.php?page=rynkowski-narzedzia&zapisano=1' );
		$_POST['moduly']    = array( 'ALFA', 'nieznany', '<b>beta</b>' );
		$_POST['usun_dane'] = 'yes';

		$this->page->save();

		$this->assertSame( array( 'alfa' => true ), $this->options[ Registry::OPTION_ENABLED ] );
		$this->assertSame( 'yes', $this->options[ Plugin::OPTION_DELETE_DATA ] );
	}

	public function test_save_with_nothing_checked_disables_all(): void {
		Functions\when( 'current_user_can' )->justReturn( true );
		Functions\when( 'check_admin_referer' )->justReturn( 1 );
		Functions\when( 'add_query_arg' )->justReturn( 'x' );
		Functions\when( 'wp_safe_redirect' )->justReturn( true );
		$this->options[ Registry::OPTION_ENABLED ] = array( 'alfa' => true );

		$this->page->save();

		$this->assertSame( array(), $this->options[ Registry::OPTION_ENABLED ] );
		$this->assertSame( 'no', $this->options[ Plugin::OPTION_DELETE_DATA ] );
	}
}
