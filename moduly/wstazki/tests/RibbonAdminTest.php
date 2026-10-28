<?php
// phpcs:ignoreFile -- plik testowy.

namespace Rynkowski\Narzedzia\Moduly\Wstazki\Tests;

use Brain\Monkey\Actions;
use Brain\Monkey\Filters;
use Brain\Monkey\Functions;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use Rynkowski\Narzedzia\Moduly\Wstazki\Ribbon_Admin;
use Rynkowski\Narzedzia\Moduly\Wstazki\Ribbons;
use Rynkowski\Narzedzia\Tests\TestCase;

final class RibbonAdminTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		Functions\when( 'get_the_title' )->justReturn( 'Wstążka' );
		Functions\when( 'get_terms' )->justReturn( array() );
	}

	private function post( int $id = 7, string $type = Ribbons::POST_TYPE ): \WP_Post {
		return new \WP_Post( $id, $type );
	}

	/**
	 * Przygotowuje poprawne żądanie zapisu.
	 */
	private function valid_request(): void {
		$_POST[ Ribbon_Admin::NONCE ] = 'nonce';
		Functions\when( 'wp_verify_nonce' )->alias( static fn( $nonce, $action ): bool => 'nonce' === $nonce && Ribbon_Admin::NONCE === $action );
		Functions\when( 'current_user_can' )->alias( static fn( string $cap, int $id ): bool => 'edit_post' === $cap && 7 === $id );
	}

	public function test_register_hooks_editor_list_and_assets(): void {
		Actions\expectAdded( 'add_meta_boxes_' . Ribbons::POST_TYPE )->once();
		Actions\expectAdded( 'save_post_' . Ribbons::POST_TYPE )->once()->with( array( Ribbon_Admin::class, 'save' ), 10, 2 );
		Actions\expectAdded( 'admin_enqueue_scripts' )->once();
		Filters\expectAdded( 'manage_' . Ribbons::POST_TYPE . '_posts_columns' )->once();
		Actions\expectAdded( 'manage_' . Ribbons::POST_TYPE . '_posts_custom_column' )->once();
		Filters\expectAdded( 'enter_title_here' )->once();

		Ribbon_Admin::register();
	}

	public function test_adds_look_and_assignment_boxes(): void {
		Functions\expect( 'add_meta_box' )->once()->with( 'rynkowski-wstazka-wyglad', 'Wygląd', array( Ribbon_Admin::class, 'render_look' ), Ribbons::POST_TYPE, 'normal', 'high' );
		Functions\expect( 'add_meta_box' )->once()->with( 'rynkowski-wstazka-przypisanie', 'Przypisanie', array( Ribbon_Admin::class, 'render_assignment' ), Ribbons::POST_TYPE, 'normal', 'default' );

		Ribbon_Admin::add_meta_boxes();
	}

	public function test_render_look_escapes_values_and_marks_active(): void {
		Functions\expect( 'wp_nonce_field' )->once()->with( Ribbon_Admin::NONCE, Ribbon_Admin::NONCE );
		$this->meta[7] = array(
			'_rynkowski_wstazka_text'       => '"><script>alert(1)</script>',
			'_rynkowski_wstazka_text_color' => '#ffffff',
			'_rynkowski_wstazka_active'     => 'yes',
		);

		$html = $this->capture( fn() => Ribbon_Admin::render_look( $this->post() ) );

		$this->assertStringContainsString( 'value="&quot;&gt;&lt;script&gt;alert(1)&lt;/script&gt;"', $html );
		$this->assertStringNotContainsString( '<script>', $html );
		$this->assertStringContainsString( 'value="#ffffff"', $html );
		$this->assertMatchesRegularExpression( '/name="rynkowski_active" value="yes"\s+checked=\'checked\'>/', $html );
		// Układ ustawień WooCommerce: opisy jako dymki „?”, etykieta prowadzi do pola.
		$this->assertStringContainsString( '<label for="rynkowski-text-color">Kolor tekstu</label><span class="woocommerce-help-tip"', $html );
		$this->assertStringContainsString( 'data-tip="Puste pole = kolor z ustawień odznak w Blocksy."', $html );
		$this->assertStringContainsString( '<label for="rynkowski-active">Aktywna</label>', $html );
		$this->assertStringContainsString( 'id="rynkowski-active" name="rynkowski_active"', $html );
		$this->assertStringNotContainsString( '<p class="description">', $html );
	}

	public function test_render_assignment_lists_saved_products_and_skips_deleted(): void {
		$this->meta[7] = array( '_rynkowski_wstazka_products' => array( 5, 6 ) );
		Functions\when( 'wc_get_product' )->alias( static fn( int $id ) => 5 === $id ? new \WC_Product( 5, 'Torba <b>X</b>' ) : false );
		Functions\when( 'wp_strip_all_tags' )->alias( 'strip_tags' );

		$html = $this->capture( fn() => Ribbon_Admin::render_assignment( $this->post() ) );

		$this->assertStringContainsString( '<option value="5" selected>Torba X</option>', $html );
		$this->assertStringNotContainsString( 'value="6"', $html );
		$this->assertStringContainsString( 'name="rynkowski_categories[]"', $html );
		$this->assertStringContainsString( 'name="rynkowski_tags[]"', $html );
	}

	public function test_save_ignores_request_without_nonce(): void {
		Functions\expect( 'wp_verify_nonce' )->never();

		Ribbon_Admin::save( 7, $this->post() );

		$this->assertSame( array(), $this->meta );
	}

	public function test_save_ignores_bad_nonce(): void {
		$_POST[ Ribbon_Admin::NONCE ] = 'podrobiony';
		Functions\when( 'wp_verify_nonce' )->justReturn( false );

		Ribbon_Admin::save( 7, $this->post() );

		$this->assertSame( array(), $this->meta );
	}

	#[RunInSeparateProcess]
	#[PreserveGlobalState( false )]
	public function test_save_ignores_autosave(): void {
		$this->valid_request();
		define( 'DOING_AUTOSAVE', true );

		Ribbon_Admin::save( 7, $this->post() );

		$this->assertSame( array(), $this->meta );
	}

	public function test_save_requires_edit_capability(): void {
		$this->valid_request();
		$_POST['rynkowski_text'] = 'x';

		Ribbon_Admin::save( 8, $this->post( 8 ) );

		$this->assertSame( array(), $this->meta );
	}

	public function test_save_ignores_other_post_types(): void {
		$this->valid_request();

		Ribbon_Admin::save( 7, $this->post( 7, 'product' ) );

		$this->assertSame( array(), $this->meta );
	}

	public function test_save_sanitizes_and_validates_everything(): void {
		$this->valid_request();
		$_POST['rynkowski_text']       = '<script>alert(1)</script>Nowość';
		$_POST['rynkowski_text_color'] = 'red;}x{';
		$_POST['rynkowski_bg_color']   = '#00ff00';
		$_POST['rynkowski_active']     = 'yes';
		$_POST['rynkowski_products']   = array( '5', '6', '5' );
		$_POST['rynkowski_categories'] = array( '10', '11' );
		$_POST['rynkowski_tags']       = array( '20' );
		Functions\when( 'get_post_type' )->alias( static fn( int $id ): string => 5 === $id ? 'product' : 'post' );
		Functions\when( 'get_term' )->alias(
			static fn( int $id, string $taxonomy ) => ( 10 === $id && 'product_cat' === $taxonomy ) || ( 20 === $id && 'product_tag' === $taxonomy ) ? new \WP_Term( $id ) : null
		);

		Ribbon_Admin::save( 7, $this->post() );

		$this->assertSame(
			array(
				'_rynkowski_wstazka_text'       => 'alert(1)Nowość',
				'_rynkowski_wstazka_text_color' => '',
				'_rynkowski_wstazka_bg_color'   => '#00ff00',
				'_rynkowski_wstazka_active'     => 'yes',
				'_rynkowski_wstazka_products'   => array( 5 ),
				'_rynkowski_wstazka_categories' => array( 10 ),
				'_rynkowski_wstazka_tags'       => array( 20 ),
			),
			$this->meta[7]
		);
	}

	public function test_save_with_empty_form_clears_fields(): void {
		$this->valid_request();

		Ribbon_Admin::save( 7, $this->post() );

		$this->assertSame( '', $this->meta[7]['_rynkowski_wstazka_text'] );
		$this->assertSame( '', $this->meta[7]['_rynkowski_wstazka_bg_color'] );
		$this->assertSame( 'no', $this->meta[7]['_rynkowski_wstazka_active'] );
		$this->assertSame( array(), $this->meta[7]['_rynkowski_wstazka_products'] );
	}

	public function test_enqueue_only_on_ribbon_screens(): void {
		Functions\expect( 'wp_enqueue_style' )->never();
		Functions\when( 'get_current_screen' )->justReturn( null );
		Ribbon_Admin::enqueue();

		Functions\when( 'get_current_screen' )->justReturn( new \WP_Screen( 'product', 'post', 'product' ) );
		Ribbon_Admin::enqueue();
	}

	public function test_enqueue_loads_select_color_picker_and_preview_styles(): void {
		Functions\when( 'get_current_screen' )->justReturn( new \WP_Screen( Ribbons::POST_TYPE, 'post', Ribbons::POST_TYPE ) );
		Functions\expect( 'wp_enqueue_style' )->once()->with( 'woocommerce_admin_styles' );
		Functions\expect( 'wp_enqueue_style' )->once()->with( 'wp-color-picker' );
		Functions\expect( 'wp_enqueue_script' )->once()->with( 'wc-enhanced-select' );
		Functions\expect( 'wp_enqueue_script' )->once()->with( 'wp-color-picker' );
		Functions\expect( 'wp_add_inline_script' )->once()->with( 'wp-color-picker', \Mockery::pattern( '/rynkowski-color/' ) );
		Functions\expect( 'wp_add_inline_style' )->once()->with( 'wp-color-picker', Ribbon_Admin::preview_css() );

		Ribbon_Admin::enqueue();
	}

	public function test_columns_keep_checkbox(): void {
		$this->assertSame(
			array( 'cb', 'title', 'rynkowski_preview', 'rynkowski_active', 'rynkowski_assigned' ),
			array_keys( Ribbon_Admin::columns( array( 'cb' => '<input>' ) ) )
		);
		$this->assertSame( '', Ribbon_Admin::columns( array() )['cb'] );
	}

	public function test_render_columns(): void {
		$this->meta[7] = array(
			'_rynkowski_wstazka_text'       => 'Hit',
			'_rynkowski_wstazka_active'     => 'yes',
			'_rynkowski_wstazka_products'   => array( 1, 2 ),
			'_rynkowski_wstazka_categories' => array( 3 ),
		);

		$this->assertStringContainsString( '>Hit</span>', $this->capture( fn() => Ribbon_Admin::render_column( 'rynkowski_preview', 7 ) ) );
		$this->assertSame( 'Tak', $this->capture( fn() => Ribbon_Admin::render_column( 'rynkowski_active', 7 ) ) );
		$this->assertSame( '—', $this->capture( fn() => Ribbon_Admin::render_column( 'rynkowski_active', 8 ) ) );
		$this->assertSame( 'produkty: 2, kategorie: 1, tagi: 0', $this->capture( fn() => Ribbon_Admin::render_column( 'rynkowski_assigned', 7 ) ) );
		$this->assertSame( '', $this->capture( fn() => Ribbon_Admin::render_column( 'inna', 7 ) ) );
	}

	public function test_preview_escapes_text_and_uses_colors(): void {
		$this->assertSame(
			'<span class="rynkowski-wstazka-podglad" style="color:#fff;background-color:#000;">&lt;b&gt;</span>',
			Ribbon_Admin::preview( array( 'text' => '<b>', 'text_color' => '#fff', 'bg_color' => '#000' ) )
		);
		$this->assertSame(
			'<span class="rynkowski-wstazka-podglad" style="">A</span>',
			Ribbon_Admin::preview( array( 'text' => 'A', 'text_color' => '', 'bg_color' => '' ) )
		);
	}

	public function test_title_placeholder_only_for_ribbons(): void {
		$this->assertStringContainsString( 'Nazwa wewnętrzna', Ribbon_Admin::title_placeholder( 'Dodaj tytuł', $this->post() ) );
		$this->assertSame( 'Dodaj tytuł', Ribbon_Admin::title_placeholder( 'Dodaj tytuł', $this->post( 1, 'post' ) ) );
	}
}
