<?php
// phpcs:ignoreFile -- plik testowy.

namespace Rynkowski\Narzedzia\Moduly\Wstazki\Tests;

use Brain\Monkey\Actions;
use Brain\Monkey\Filters;
use Brain\Monkey\Functions;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use Rynkowski\Narzedzia\Moduly\Wstazki\Ribbon_Frontend;
use Rynkowski\Narzedzia\Moduly\Wstazki\Ribbon_Settings;
use Rynkowski\Narzedzia\Plugin;
use Rynkowski\Narzedzia\Tests\TestCase;

final class RibbonFrontendTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		// Pamięć aktywnych wstążek żyje przez żądanie; każdy test to nowe żądanie.
		( new \ReflectionProperty( Ribbon_Frontend::class, 'active' ) )->setValue( null, null );
		Functions\when( 'get_the_title' )->justReturn( 'W' );
		Functions\when( 'wc_get_product_term_ids' )->alias(
			static fn( int $id, string $taxonomy ): array => 'product_cat' === $taxonomy ? array( 10 ) : array( 20 )
		);
	}

	protected function tearDown(): void {
		unset( $GLOBALS['product'] );
		parent::tearDown();
	}

	/**
	 * @param array<int, array<string, mixed>> $ribbons ID => pola bez prefiksu.
	 */
	private function ribbons( array $ribbons ): void {
		Functions\when( 'get_posts' )->justReturn( array_keys( $ribbons ) );
		foreach ( $ribbons as $id => $fields ) {
			foreach ( $fields as $key => $value ) {
				$this->meta[ $id ][ '_rynkowski_wstazka_' . $key ] = $value;
			}
		}
	}

	public function test_register_hooks_blocksy_filters_late_and_styles(): void {
		Filters\expectAdded( 'blocksy:woocommerce:product-card:badges' )->once()->with( array( Ribbon_Frontend::class, 'add_badges' ), 99 );
		Filters\expectAdded( 'blocksy:woocommerce:single:after-sale-badge' )->once()->with( array( Ribbon_Frontend::class, 'add_badges' ), 99 );
		Actions\expectAdded( 'wp_enqueue_scripts' )->once()->with( array( Ribbon_Frontend::class, 'enqueue' ) );

		Ribbon_Frontend::register();
	}

	public function test_leaves_unexpected_input_untouched(): void {
		$GLOBALS['product'] = new \WC_Product( 1 );
		$this->assertSame( 'nie tablica', Ribbon_Frontend::add_badges( 'nie tablica' ) );

		unset( $GLOBALS['product'] );
		$this->assertSame( array( '<span>SALE</span>' ), Ribbon_Frontend::add_badges( array( '<span>SALE</span>' ) ) );
	}

	public function test_no_active_ribbons_keeps_theme_badges(): void {
		$GLOBALS['product'] = new \WC_Product( 5 );
		$this->ribbons(
			array(
				1 => array( 'text' => 'Wyłączona', 'active' => 'no', 'products' => array( 5 ) ),
				2 => array( 'text' => '', 'active' => 'yes', 'products' => array( 5 ) ),
			)
		);

		$this->assertSame( array( '<span class="onsale">SALE</span>' ), Ribbon_Frontend::add_badges( array( '<span class="onsale">SALE</span>', ' ' ) ) );
	}

	public function test_single_ribbon_is_not_wrapped(): void {
		$GLOBALS['product'] = new \WC_Product( 5 );
		Functions\when( 'blocksy_get_theme_mod' )->justReturn( 'type-3' );
		$this->ribbons( array( 1 => array( 'text' => 'Hit', 'active' => 'yes', 'tags' => array( 20 ) ) ) );

		$this->assertSame(
			array( '<span class="ct-woo-badge-rynkowski" data-shape="type-3">Hit</span>' ),
			Ribbon_Frontend::add_badges( array( '' ) )
		);
	}

	public function test_several_badges_are_stacked_and_escaped(): void {
		$GLOBALS['product'] = new \WC_Product( 5 );
		Functions\when( 'blocksy_get_theme_mod' )->justReturn( array( 'nie tekst' ) );
		$this->ribbons(
			array(
				1 => array( 'text' => '<script>alert(1)</script>', 'active' => 'yes', 'products' => array( 5 ), 'text_color' => '#fff', 'bg_color' => 'red;}x{' ),
				2 => array( 'text' => 'Kategoria', 'active' => 'yes', 'categories' => array( 10 ), 'bg_color' => '#000' ),
				3 => array( 'text' => 'Inny produkt', 'active' => 'yes', 'products' => array( 6 ) ),
			)
		);

		$this->assertSame(
			array(
				'<div class="rynkowski-wstazki-stos"><span class="onsale">SALE</span>'
				. '<span class="ct-woo-badge-rynkowski" data-shape="type-2" style="--badge-text-color:#fff;">&lt;script&gt;alert(1)&lt;/script&gt;</span>'
				. '<span class="ct-woo-badge-rynkowski" data-shape="type-2" style="--badge-background-color:#000;">Kategoria</span></div>',
			),
			Ribbon_Frontend::add_badges( array( '<span class="onsale">SALE</span>' ) )
		);
	}

	public function test_already_stacked_badges_are_not_wrapped_twice(): void {
		$GLOBALS['product'] = new \WC_Product( 5 );
		$this->ribbons( array() );
		$stacked = array( '<div class="rynkowski-wstazki-stos">a</div>', '<span>b</span>' );

		$this->assertSame( $stacked, Ribbon_Frontend::add_badges( $stacked ) );
	}

	public function test_active_ribbons_are_read_once_per_request(): void {
		$GLOBALS['product'] = new \WC_Product( 5 );
		Functions\when( 'blocksy_get_theme_mod' )->justReturn( 'type-2' );
		Functions\expect( 'get_posts' )->once()->andReturn( array( 1 ) );
		$this->meta[1] = array( '_rynkowski_wstazka_text' => 'A', '_rynkowski_wstazka_active' => 'yes', '_rynkowski_wstazka_products' => array( 5 ) );

		Ribbon_Frontend::add_badges( array() );
		Ribbon_Frontend::add_badges( array() );
	}

	#[RunInSeparateProcess]
	#[PreserveGlobalState( false )]
	public function test_without_blocksy_function_uses_default_shape(): void {
		$GLOBALS['product'] = new \WC_Product( 5 );
		$this->ribbons( array( 1 => array( 'text' => 'A', 'active' => 'yes', 'products' => array( 5 ) ) ) );

		$this->assertSame(
			array( '<span class="ct-woo-badge-rynkowski" data-shape="type-2">A</span>' ),
			Ribbon_Frontend::add_badges( array() )
		);
	}

	public function test_enqueue_adds_stack_styles_with_default_gap(): void {
		Functions\expect( 'wp_register_style' )->once()->with( 'rynkowski-wstazki', false, array(), Plugin::VERSION );
		Functions\expect( 'wp_enqueue_style' )->once()->with( 'rynkowski-wstazki' );
		Functions\expect( 'wp_add_inline_style' )->once()->with( 'rynkowski-wstazki', \Mockery::pattern( '/^\.rynkowski-wstazki-stos\{.*gap:5px;--has-container:relative;/' ) );

		Ribbon_Frontend::enqueue();
	}

	public function test_stack_gap_comes_from_settings(): void {
		Functions\when( 'wp_register_style' )->justReturn( true );
		Functions\when( 'wp_enqueue_style' )->justReturn( null );
		$this->options[ Ribbon_Settings::OPTION ] = array( 'odstep' => '12' );
		Functions\expect( 'wp_add_inline_style' )->once()->with( 'rynkowski-wstazki', \Mockery::pattern( '/gap:12px;/' ) );

		Ribbon_Frontend::enqueue();
	}

	public function test_stack_gap_rejects_css_injection_from_database(): void {
		Functions\when( 'wp_register_style' )->justReturn( true );
		Functions\when( 'wp_enqueue_style' )->justReturn( null );
		$this->options[ Ribbon_Settings::OPTION ] = array( 'odstep' => '5px;}body{display:none' );
		Functions\expect( 'wp_add_inline_style' )->once()->with(
			'rynkowski-wstazki',
			\Mockery::on( static fn( string $css ): bool => str_contains( $css, 'gap:5px;' ) && ! str_contains( $css, 'body{' ) )
		);

		Ribbon_Frontend::enqueue();
	}

	public function test_fixed_shape_from_settings_overrides_blocksy(): void {
		$GLOBALS['product'] = new \WC_Product( 5 );
		Functions\expect( 'blocksy_get_theme_mod' )->never();
		$this->options[ Ribbon_Settings::OPTION ] = array( 'ksztalt' => 'type-1' );
		$this->ribbons( array( 1 => array( 'text' => 'A', 'active' => 'yes', 'products' => array( 5 ) ) ) );

		$this->assertSame(
			array( '<span class="ct-woo-badge-rynkowski" data-shape="type-1">A</span>' ),
			Ribbon_Frontend::add_badges( array() )
		);
	}
}
