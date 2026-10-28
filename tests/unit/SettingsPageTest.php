<?php
// phpcs:ignoreFile -- plik testowy.

namespace Rynkowski\Narzedzia\Tests;

use Brain\Monkey\Actions;
use Brain\Monkey\Filters;
use Brain\Monkey\Functions;
use Rynkowski\Narzedzia\Plugin;
use Rynkowski\Narzedzia\Registry;
use Rynkowski\Narzedzia\Settings_Page;

final class SettingsPageTest extends TestCase {

	private const OPTION = 'rynkowski_test_ustawiony';

	private Registry $registry;
	private Settings_Page $page;

	protected function setUp(): void {
		parent::setUp();
		Functions\when( 'did_action' )->justReturn( 1 );
		Functions\when( 'get_template' )->justReturn( 'astra' );
		Functions\when( 'admin_url' )->alias( static fn( string $path = '' ): string => 'https://sklep.test/wp-admin/' . $path );
		Functions\when( 'add_query_arg' )->alias(
			static function ( $key, $value, $url = null ): string {
				if ( is_array( $key ) ) {
					return $value . '?' . http_build_query( $key );
				}
				return $url . ( str_contains( $url, '?' ) ? '&' : '?' ) . $key . '=' . $value;
			}
		);

		$this->registry = new Registry( __DIR__ . '/../fixtures/moduly' );
		$this->registry->discover();
		$this->page = new Settings_Page( $this->registry );
	}

	/**
	 * Udaje, że moduł z ustawieniami wystartował w tym żądaniu.
	 */
	private function activate_settings_module(): void {
		$module = new Fake_Settings_Module();
		( new \ReflectionProperty( Registry::class, 'modules' ) )->setValue( $this->registry, $this->registry->all() + array( 'ustawiony' => $module ) );
		( new \ReflectionProperty( Registry::class, 'active' ) )->setValue( $this->registry, array( 'alfa' => new Fake_Module( 'alfa' ), 'ustawiony' => $module ) );
	}

	private function allow_render(): void {
		Functions\when( 'current_user_can' )->justReturn( true );
		Functions\when( 'wp_nonce_field' )->justReturn( '' );
	}

	public function test_register_hooks_menu_save_screens_assets_and_color_field(): void {
		Actions\expectAdded( 'admin_menu' )->once()->with( array( $this->page, 'add_page' ), 9 );
		Actions\expectAdded( 'admin_post_' . Settings_Page::ACTION )->once()->with( array( $this->page, 'save' ) );
		Filters\expectAdded( 'woocommerce_screen_ids' )->once()->with( array( $this->page, 'screen_ids' ) );
		Actions\expectAdded( 'admin_enqueue_scripts' )->once()->with( array( $this->page, 'enqueue' ) );
		Actions\expectAdded( 'woocommerce_admin_field_rynkowski_color' )->once()->with( array( $this->page, 'render_color_field' ) );

		$this->page->register();
	}

	public function test_menu_sits_below_blocksy_with_settings_as_first_subpage(): void {
		Functions\expect( 'add_menu_page' )
			->once()
			->with( 'Narzędzia', 'Narzędzia', 'manage_woocommerce', Settings_Page::SLUG, array( $this->page, 'render' ), 'dashicons-admin-tools', 3 );
		Functions\expect( 'add_submenu_page' )
			->once()
			->with( Settings_Page::SLUG, 'Ustawienia', 'Ustawienia', 'manage_woocommerce', Settings_Page::SLUG, array( $this->page, 'render' ) );

		$this->page->add_page();
	}

	public function test_tabs_are_modules_plus_active_modules_with_settings(): void {
		$this->assertSame( array( 'moduly' => 'Moduły' ), $this->page->tabs() );

		$this->activate_settings_module();

		$this->assertSame( array( 'moduly' => 'Moduły', 'ustawiony' => 'Zakładka <b>ustawiony</b>' ), $this->page->tabs() );
	}

	public function test_current_tab_is_whitelisted(): void {
		$this->activate_settings_module();

		$this->assertSame( 'moduly', $this->page->current_tab() );

		$_GET['zakladka'] = 'ustawiony';
		$this->assertSame( 'ustawiony', $this->page->current_tab() );

		$_GET['zakladka'] = 'alfa';
		$this->assertSame( 'moduly', $this->page->current_tab() );

		$_GET['zakladka'] = '../<script>';
		$this->assertSame( 'moduly', $this->page->current_tab() );
	}

	public function test_render_requires_capability(): void {
		Functions\when( 'current_user_can' )->justReturn( false );

		$this->assertSame( '', $this->capture( fn() => $this->page->render() ) );
	}

	public function test_render_modules_tab_lists_modules_state_and_missing_dependencies(): void {
		Functions\when( 'current_user_can' )->justReturn( true );
		Functions\expect( 'wp_nonce_field' )->once()->with( Settings_Page::ACTION . '_moduly' );
		$this->registry->set_enabled( array( 'beta' ) );
		$this->registry->boot();
		$this->options[ Plugin::OPTION_DELETE_DATA ] = 'yes';
		$_GET['zapisano'] = '1';

		$html = $this->capture( fn() => $this->page->render() );

		$this->assertStringContainsString( '<div class="wrap woocommerce">', $html );
		// Kodowanie „&” robi prawdziwe esc_url(); zaślepka Brain Monkey zostawia adres bez zmian.
		$this->assertMatchesRegularExpression( '/href="https:\/\/sklep\.test\/wp-admin\/admin\.php\?page=rynkowski-narzedzia(&|&amp;|&#038;)zakladka=moduly" class="nav-tab nav-tab-active" aria-current="page">Moduły<\/a>/', $html );
		$this->assertStringContainsString( 'Zapisano ustawienia.', $html );
		$this->assertStringContainsString( 'action="https://sklep.test/wp-admin/admin-post.php"', $html );
		$this->assertStringContainsString( 'name="zakladka" value="moduly"', $html );
		$this->assertMatchesRegularExpression( '/value="alfa"\s*>/', $html );
		$this->assertMatchesRegularExpression( '/value="beta"\s+checked=\'checked\'>/', $html );
		$this->assertStringContainsString( 'Nie działa, brakuje: Blocksy.', $html );
		$this->assertMatchesRegularExpression( '/<td>\s*Blocksy\s*<\/td>/', $html );
		$this->assertMatchesRegularExpression( '/name="usun_dane" value="yes"\s+checked=\'checked\'>/', $html );
		$this->assertStringContainsString( 'class="button-primary woocommerce-save-button">Zapisz zmiany</button>', $html );
	}

	public function test_render_modules_tab_with_unknown_dependency_and_no_message(): void {
		$this->allow_render();
		( new \ReflectionProperty( Registry::class, 'modules' ) )->setValue( $this->registry, array( 'x' => new Fake_Module( 'x', array( 'nieznana' ) ) ) );

		$html = $this->capture( fn() => $this->page->render() );

		$this->assertStringNotContainsString( 'Zapisano ustawienia.', $html );
		$this->assertStringContainsString( 'nieznana', $html );
		$this->assertStringNotContainsString( 'Nie działa', $html );
	}

	public function test_render_module_tab_uses_woocommerce_fields_and_escapes_tab_title(): void {
		$this->allow_render();
		$this->activate_settings_module();
		$_GET['zakladka'] = 'ustawiony';
		$received = null;
		Functions\expect( 'woocommerce_admin_fields' )->once()->andReturnUsing(
			function ( array $fields ) use ( &$received ) {
				$received = $fields;
				echo '[pola WooCommerce]';
			}
		);

		$html = $this->capture( fn() => $this->page->render() );

		$this->assertStringContainsString( '[pola WooCommerce]', $html );
		$this->assertStringContainsString( 'name="zakladka" value="ustawiony"', $html );
		$this->assertStringContainsString( 'nav-tab nav-tab-active" aria-current="page">Zakładka &lt;b&gt;ustawiony&lt;/b&gt;</a>', $html );
		$this->assertStringContainsString( 'class="nav-tab">Moduły</a>', $html );
		$this->assertSame( 'title', $received[0]['type'] );
		$this->assertSame( 'sectionend', end( $received )['type'] );
	}

	public function test_wc_fields_map_types_values_and_attributes(): void {
		$this->options[ self::OPTION ] = array( 'liczba' => '7', 'kolor' => '#00ff00', 'wybor' => 'zly' );
		$tab = ( new Fake_Settings_Module() )->settings_tab();

		$fields = $this->page->wc_fields( $tab );

		$this->assertSame( array( 'type' => 'title', 'title' => 'Zakładka <b>ustawiony</b>', 'id' => self::OPTION ), $fields[0] );
		$this->assertSame(
			array(
				'id'                => self::OPTION . '[liczba]',
				'type'              => 'number',
				'title'             => 'Liczba',
				'desc'              => 'Opis liczby',
				'default'           => 5,
				'value'             => 7,
				'custom_attributes' => array( 'min' => 0, 'max' => 10, 'step' => 1 ),
				'suffix'            => 'px',
				'css'               => 'width:80px;',
			),
			$fields[1]
		);
		$this->assertSame( array( 'step' => 1 ), $fields[2]['custom_attributes'] );
		$this->assertSame( '', $fields[2]['suffix'] );
		$this->assertSame( '', $fields[2]['desc'] );
		$this->assertSame( 'rynkowski_color', $fields[3]['type'] );
		$this->assertSame( '#00ff00', $fields[3]['value'] );
		$this->assertSame( array( 'a' => 'A', 'b' => 'B' ), $fields[4]['options'] );
		$this->assertSame( 'a', $fields[4]['value'] );
		$this->assertSame( 'checkbox', $fields[5]['type'] );
		$this->assertSame( 'yes', $fields[5]['value'] );
		$this->assertSame( array( 'type' => 'sectionend', 'id' => self::OPTION ), $fields[6] );
	}

	public function test_wc_fields_select_without_options(): void {
		$tab = array(
			'title'  => 'T',
			'option' => 'o',
			'fields' => array( array( 'id' => 's', 'type' => 'select', 'title' => 'S', 'default' => 'x' ) ),
		);

		$this->assertSame( array(), $this->page->wc_fields( $tab )[1]['options'] );
	}

	public function test_color_field_is_escaped_and_uses_wp_color_picker(): void {
		$html = $this->capture(
			fn() => $this->page->render_color_field(
				array( 'id' => 'o[k"]', 'value' => '"><script>', 'title' => '<b>Kolor</b>', 'desc' => 'Opis <i>x</i>' )
			)
		);

		$this->assertStringContainsString( '<label for="o[k&quot;]">&lt;b&gt;Kolor&lt;/b&gt;</label>', $html );
		$this->assertStringContainsString( 'name="o[k&quot;]" value="&quot;&gt;&lt;script&gt;" class="rynkowski-color"', $html );
		$this->assertStringContainsString( '<p class="description">Opis &lt;i&gt;x&lt;/i&gt;</p>', $html );
		$this->assertStringNotContainsString( '<script>', $html );
	}

	public function test_color_field_without_description(): void {
		$html = $this->capture( fn() => $this->page->render_color_field( array( 'id' => 'o[k]', 'value' => '', 'title' => 'K' ) ) );

		$this->assertStringNotContainsString( 'description', $html );
	}

	public function test_save_rejects_users_without_capability(): void {
		Functions\when( 'current_user_can' )->justReturn( false );
		// Prawdziwe wp_die() kończy żądanie; tu rzuca wyjątek, żeby to sprawdzić.
		Functions\expect( 'wp_die' )->once()->andThrow( new \RuntimeException( 'wp_die' ) );
		Functions\expect( 'check_admin_referer' )->never();
		$_POST = array( 'zakladka' => 'moduly', 'moduly' => array( 'alfa' ) );

		try {
			$this->page->save();
			$this->fail( 'save() powinno zakończyć się na wp_die().' );
		} catch ( \RuntimeException $e ) {
			$this->assertSame( 'wp_die', $e->getMessage() );
		}
		$this->assertArrayNotHasKey( Registry::OPTION_ENABLED, $this->options );
	}

	public function test_save_checks_nonce_of_the_posted_tab(): void {
		Functions\when( 'current_user_can' )->justReturn( true );
		Functions\expect( 'check_admin_referer' )->once()->with( Settings_Page::ACTION . '_' )->andThrow( new \RuntimeException( 'nonce' ) );

		$this->expectExceptionMessage( 'nonce' );
		$this->page->save();
	}

	public function test_save_modules_tab_stores_choice_and_redirects(): void {
		Functions\when( 'current_user_can' )->justReturn( true );
		Functions\expect( 'check_admin_referer' )->once()->with( Settings_Page::ACTION . '_moduly' );
		Functions\expect( 'wp_safe_redirect' )->once()->with( 'https://sklep.test/wp-admin/admin.php?page=rynkowski-narzedzia&zakladka=moduly&zapisano=1' );
		$_POST = array(
			'zakladka'  => 'moduly',
			'moduly'    => array( 'ALFA', 'nieznany', '<b>beta</b>' ),
			'usun_dane' => 'yes',
		);

		$this->page->save();

		$this->assertSame( array( 'alfa' => true ), $this->options[ Registry::OPTION_ENABLED ] );
		$this->assertSame( 'yes', $this->options[ Plugin::OPTION_DELETE_DATA ] );
	}

	public function test_save_modules_tab_with_nothing_checked(): void {
		Functions\when( 'current_user_can' )->justReturn( true );
		Functions\when( 'check_admin_referer' )->justReturn( 1 );
		Functions\when( 'wp_safe_redirect' )->justReturn( true );
		$this->options[ Registry::OPTION_ENABLED ] = array( 'alfa' => true );
		$_POST = array( 'zakladka' => 'moduly' );

		$this->page->save();

		$this->assertSame( array(), $this->options[ Registry::OPTION_ENABLED ] );
		$this->assertSame( 'no', $this->options[ Plugin::OPTION_DELETE_DATA ] );
	}

	public function test_save_module_tab_validates_every_field(): void {
		Functions\when( 'current_user_can' )->justReturn( true );
		Functions\expect( 'check_admin_referer' )->once()->with( Settings_Page::ACTION . '_ustawiony' );
		Functions\expect( 'wp_safe_redirect' )->once()->with( 'https://sklep.test/wp-admin/admin.php?page=rynkowski-narzedzia&zakladka=ustawiony&zapisano=1' );
		$this->activate_settings_module();
		$_POST = array(
			'zakladka'   => 'ustawiony',
			self::OPTION => array(
				'liczba' => '999',
				'kolor'  => 'red;}body{display:none',
				'wybor'  => 'b',
				'obce'   => '<script>',
			),
		);

		$this->page->save();

		$this->assertSame(
			array( 'liczba' => 5, 'bez_zakresu' => 1, 'kolor' => '', 'wybor' => 'b', 'flaga' => 'no' ),
			$this->options[ self::OPTION ]
		);
		$this->assertArrayNotHasKey( Registry::OPTION_ENABLED, $this->options );
	}

	public function test_save_module_tab_ignores_non_array_payload(): void {
		Functions\when( 'current_user_can' )->justReturn( true );
		Functions\when( 'check_admin_referer' )->justReturn( 1 );
		Functions\when( 'wp_safe_redirect' )->justReturn( true );
		$this->activate_settings_module();
		$_POST = array( 'zakladka' => 'ustawiony', self::OPTION => 'tekst zamiast tablicy' );

		$this->page->save();

		$this->assertSame( 5, $this->options[ self::OPTION ]['liczba'] );
	}

	public function test_save_unknown_tab_changes_nothing_and_returns_to_modules(): void {
		Functions\when( 'current_user_can' )->justReturn( true );
		Functions\expect( 'check_admin_referer' )->once()->with( Settings_Page::ACTION . '_alfa' );
		Functions\expect( 'wp_safe_redirect' )->once()->with( 'https://sklep.test/wp-admin/admin.php?page=rynkowski-narzedzia&zakladka=moduly&zapisano=1' );
		$_POST = array( 'zakladka' => 'alfa', 'moduly' => array( 'alfa' ) );

		$this->page->save();

		$this->assertSame( array(), $this->options );
	}

	public function test_screen_ids_adds_settings_screen_for_woocommerce_assets(): void {
		$this->assertSame( array( 'product', Settings_Page::SCREEN_ID ), $this->page->screen_ids( array( 'product' ) ) );
		$this->assertSame( 'zepsute', $this->page->screen_ids( 'zepsute' ) );
	}

	public function test_enqueue_color_picker_and_styles_only_on_settings_screen(): void {
		Functions\expect( 'wp_enqueue_style' )->once()->with( 'wp-color-picker' );
		Functions\expect( 'wp_enqueue_script' )->once()->with( 'wp-color-picker' );
		Functions\expect( 'wp_add_inline_script' )->once()->with( 'wp-color-picker', \Mockery::pattern( '/\.rynkowski-color/' ) );
		Functions\expect( 'wp_register_style' )->once()->with( 'rynkowski-ustawienia', false, array(), Plugin::VERSION );
		Functions\expect( 'wp_enqueue_style' )->once()->with( 'rynkowski-ustawienia' );
		Functions\expect( 'wp_add_inline_style' )->once()->with( 'rynkowski-ustawienia', \Mockery::pattern( '/^\.toplevel_page_rynkowski-narzedzia \.forminp-number input\{vertical-align:middle;/' ) );

		$this->page->enqueue( 'edit.php' );
		$this->page->enqueue( Settings_Page::SCREEN_ID );
	}
}
