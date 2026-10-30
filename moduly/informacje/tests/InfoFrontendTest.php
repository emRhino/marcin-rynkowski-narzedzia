<?php
// phpcs:ignoreFile -- plik testowy.

namespace Rynkowski\Narzedzia\Moduly\Informacje\Tests;

require_once __DIR__ . '/InfoTestCase.php';

use Brain\Monkey\Actions;
use Brain\Monkey\Functions;
use Rynkowski\Narzedzia\Moduly\Informacje\Info_Frontend;
use Rynkowski\Narzedzia\Moduly\Informacje\Info_Rows;
use Rynkowski\Narzedzia\Moduly\Informacje\Info_Settings;
use Rynkowski\Narzedzia\Plugin;

final class InfoFrontendTest extends InfoTestCase {

	protected function setUp(): void {
		parent::setUp();
		Functions\when( 'get_the_ID' )->justReturn( 7 );
		Functions\when( 'wp_get_attachment_image_url' )->alias( static fn( int $id, string $size ) => 44 === $id ? 'https://sklep/ikona-' . $size . '.png' : false );
	}

	public function test_register_hooks_after_add_to_cart(): void {
		Actions\expectAdded( 'woocommerce_single_product_summary' )->once()->with( array( Info_Frontend::class, 'render' ), 35 );
		Actions\expectAdded( 'wp_enqueue_scripts' )->once()->with( array( Info_Frontend::class, 'enqueue' ) );

		Info_Frontend::register();
	}

	public function test_render_rows_with_icon_and_safe_text(): void {
		$this->row( 1, "Dostawa <strong>od 0 zł</strong>\n<small>1-2 dni</small>", 0, 44 );
		$this->row( 2, 'Domyślny', 1, 99 );
		$this->meta[7] = array( Info_Rows::META_PRODUCT_TEXTS => array( 2 => '<a href="javascript:alert(1)" onclick="x">Zwrot</a><script>alert(1)</script>' ) );

		$html = $this->capture( static fn() => Info_Frontend::render() );

		$this->assertSame(
			'<ul class="rynkowski-informacje">'
			. '<li class="rynkowski-informacje__wiersz"><span class="rynkowski-informacje__ikona"><img src="https://sklep/ikona-thumbnail.png" alt="" width="20" height="20" loading="lazy" decoding="async"></span>'
			. '<span class="rynkowski-informacje__tekst">Dostawa <strong>od 0 zł</strong><br>' . "\n" . '<small>1-2 dni</small></span></li>'
			// Ikona bez obrazka (np. usunięta z biblioteki) zostawia puste miejsce, tekst bez skryptu i javascript:.
			. '<li class="rynkowski-informacje__wiersz"><span class="rynkowski-informacje__ikona"></span>'
			. '<span class="rynkowski-informacje__tekst"><a>Zwrot</a>alert(1)</span></li>'
			. '</ul>',
			$html
		);
	}

	public function test_render_nothing_when_section_off_or_no_rows(): void {
		$this->assertSame( '', $this->capture( static fn() => Info_Frontend::render() ) );

		$this->row( 1, 'Tekst' );
		$this->options[ Info_Settings::OPTION ] = array( 'sekcja' => 'no' );
		$this->assertSame( '', $this->capture( static fn() => Info_Frontend::render() ) );
	}

	public function test_render_called_like_wordpress(): void {
		$this->row( 1, 'Tekst' );

		$html = $this->capture( static fn() => call_user_func_array( array( Info_Frontend::class, 'render' ), array( '' ) ) );

		$this->assertStringContainsString( 'Tekst', $html );
	}

	public function test_css_defaults_follow_theme(): void {
		$css = Info_Frontend::css();

		$this->assertStringContainsString( '.rynkowski-informacje{list-style:none;margin:0 0 24px;padding:0;border:1px solid var(--theme-border-color, #e0e0e0);}', $css );
		$this->assertStringContainsString( '__wiersz{display:flex;align-items:center;gap:16px;margin:0;padding:16px;border-top:1px solid var(--theme-border-color, #e0e0e0);}', $css );
		$this->assertStringContainsString( '__ikona{flex:0 0 20px;width:20px;height:20px;', $css );
		$this->assertStringContainsString( '__tekst mark{background:none;color:var(--theme-palette-color-1, currentColor);}', $css );
	}

	public function test_css_uses_settings_and_rejects_injection(): void {
		$this->options[ Info_Settings::OPTION ] = array(
			'kolor_wyroznienia' => '#c00',
			'kolor_ramki'       => '#ddd;}body{display:none',
			'rozmiar_ikony'     => 32,
			'padding'           => 4,
			'odstep'            => 0,
		);

		$css = Info_Frontend::css();

		$this->assertStringContainsString( 'margin:0 0 0px;', $css );
		$this->assertStringContainsString( 'border:1px solid var(--theme-border-color, #e0e0e0);', $css );
		$this->assertStringNotContainsString( 'body{', $css );
		// Odstęp ikony od tekstu co najmniej 12px, nawet przy małym paddingu.
		$this->assertStringContainsString( 'gap:12px;margin:0;padding:4px;', $css );
		$this->assertStringContainsString( 'width:32px;height:32px;', $css );
		$this->assertStringContainsString( 'mark{background:none;color:#c00;}', $css );
	}

	public function test_enqueue_only_on_product_page_with_section_on(): void {
		Functions\when( 'is_product' )->justReturn( true );
		Functions\expect( 'wp_register_style' )->once()->with( 'rynkowski-informacje', false, array(), Plugin::VERSION );
		Functions\expect( 'wp_enqueue_style' )->once()->with( 'rynkowski-informacje' );
		Functions\expect( 'wp_add_inline_style' )->once()->with( 'rynkowski-informacje', Info_Frontend::css() );

		call_user_func_array( array( Info_Frontend::class, 'enqueue' ), array( '' ) );
	}

	public function test_enqueue_skips_other_pages_and_section_off(): void {
		Functions\expect( 'wp_register_style' )->never();

		Functions\when( 'is_product' )->justReturn( false );
		Info_Frontend::enqueue();

		Functions\when( 'is_product' )->justReturn( true );
		$this->options[ Info_Settings::OPTION ] = array( 'sekcja' => 'no' );
		Info_Frontend::enqueue();
	}
}
