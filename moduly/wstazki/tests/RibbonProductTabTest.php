<?php
// phpcs:ignoreFile -- plik testowy.

namespace Rynkowski\Narzedzia\Moduly\Wstazki\Tests;

use Brain\Monkey\Actions;
use Brain\Monkey\Filters;
use Brain\Monkey\Functions;
use Rynkowski\Narzedzia\Moduly\Wstazki\Ribbon_Admin;
use Rynkowski\Narzedzia\Moduly\Wstazki\Ribbon_Product_Tab;
use Rynkowski\Narzedzia\Plugin;
use Rynkowski\Narzedzia\Tests\TestCase;

final class RibbonProductTabTest extends TestCase {

	protected function tearDown(): void {
		unset( $GLOBALS['post'] );
		parent::tearDown();
	}

	public function test_register_hooks_tab_panel_and_styles(): void {
		Filters\expectAdded( 'woocommerce_product_data_tabs' )->once()->with( array( Ribbon_Product_Tab::class, 'add_tab' ) );
		Actions\expectAdded( 'woocommerce_product_data_panels' )->once()->with( array( Ribbon_Product_Tab::class, 'render_panel' ) );
		Actions\expectAdded( 'admin_enqueue_scripts' )->once()->with( array( Ribbon_Product_Tab::class, 'enqueue' ) );

		Ribbon_Product_Tab::register();
	}

	public function test_add_tab_keeps_existing_tabs(): void {
		$tabs = Ribbon_Product_Tab::add_tab( array( 'general' => array( 'label' => 'Ogólne' ) ) );

		$this->assertSame( array( 'general', 'rynkowski_wstazki' ), array_keys( $tabs ) );
		$this->assertSame( 'rynkowski_wstazki_panel', $tabs['rynkowski_wstazki']['target'] );
		$this->assertSame( 'Wstążki', $tabs['rynkowski_wstazki']['label'] );
	}

	public function test_panel_without_post_shows_empty_message(): void {
		$html = $this->capture( fn() => Ribbon_Product_Tab::render_panel() );

		$this->assertStringContainsString( 'id="rynkowski_wstazki_panel"', $html );
		$this->assertStringContainsString( 'Ten produkt nie ma przypisanych wstążek.', $html );
	}

	public function test_panel_lists_ribbons_with_reasons_links_and_state(): void {
		$GLOBALS['post'] = new \WP_Post( 50, 'product' );
		Functions\when( 'get_posts' )->justReturn( array( 1, 2 ) );
		Functions\when( 'get_the_title' )->alias( static fn( int $id ): string => 1 === $id ? '<b>Black</b>' : 'Lato' );
		Functions\when( 'wc_get_product_term_ids' )->justReturn( array() );
		Functions\when( 'get_edit_post_link' )->alias( static fn( int $id ): string => 'https://sklep.test/wp-admin/post.php?post=' . $id );
		$this->meta[1] = array( '_rynkowski_wstazka_text' => 'BF', '_rynkowski_wstazka_active' => 'yes', '_rynkowski_wstazka_products' => array( 50 ) );
		$this->meta[2] = array( '_rynkowski_wstazka_text' => 'L', '_rynkowski_wstazka_active' => 'no', '_rynkowski_wstazka_products' => array( 50 ) );

		$html = $this->capture( fn() => Ribbon_Product_Tab::render_panel() );

		$this->assertStringContainsString( '>&lt;b&gt;Black&lt;/b&gt;</a>', $html );
		$this->assertStringContainsString( 'href="https://sklep.test/wp-admin/post.php?post=1"', $html );
		$this->assertSame( 2, substr_count( $html, '<td>produkt</td>' ) );
		$this->assertStringContainsString( '<td>Tak</td>', $html );
		$this->assertStringContainsString( '<td>Nie</td>', $html );
		$this->assertStringNotContainsString( 'nie ma przypisanych', $html );
	}

	public function test_enqueue_only_on_product_screen(): void {
		Functions\expect( 'wp_register_style' )->never();
		Functions\when( 'get_current_screen' )->justReturn( null );
		Ribbon_Product_Tab::enqueue();

		Functions\when( 'get_current_screen' )->justReturn( new \WP_Screen( 'edit-product' ) );
		Ribbon_Product_Tab::enqueue();
	}

	public function test_enqueue_adds_preview_styles_on_product_screen(): void {
		Functions\when( 'get_current_screen' )->justReturn( new \WP_Screen( 'product' ) );
		Functions\expect( 'wp_register_style' )->once()->with( 'rynkowski-wstazki-admin', false, array(), Plugin::VERSION );
		Functions\expect( 'wp_enqueue_style' )->once()->with( 'rynkowski-wstazki-admin' );
		Functions\expect( 'wp_add_inline_style' )->once()->with( 'rynkowski-wstazki-admin', Ribbon_Admin::preview_css() );

		Ribbon_Product_Tab::enqueue();
	}
}
