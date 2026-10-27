<?php
// phpcs:ignoreFile -- plik testowy.

namespace Rynkowski\Narzedzia\Moduly\Kafle\Tests;

use Brain\Monkey\Actions;
use Brain\Monkey\Functions;
use Rynkowski\Narzedzia\Moduly\Kafle\Tile_Frontend;
use Rynkowski\Narzedzia\Moduly\Kafle\Tile_Settings;
use Rynkowski\Narzedzia\Plugin;
use Rynkowski\Narzedzia\Tests\TestCase;

final class TileFrontendTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		// Stan pętli żyje przez żądanie; każdy test to nowe żądanie.
		( new \ReflectionProperty( Tile_Frontend::class, 'tiles' ) )->setValue( null, null );
		( new \ReflectionProperty( Tile_Frontend::class, 'cells' ) )->setValue( null, 0 );

		Functions\when( 'get_the_title' )->alias( static fn( int $id ): string => 'Kafel ' . $id );
		Functions\when( 'wc_get_loop_prop' )->justReturn( false );
		Functions\when( 'get_query_var' )->justReturn( 0 );
		$this->context( 'category', 10 );
	}

	/**
	 * Ustawia rodzaj bieżącej listy produktów.
	 */
	private function context( string $where, int $term_id = 0 ): void {
		Functions\when( 'is_shop' )->justReturn( 'shop' === $where );
		Functions\when( 'is_product_category' )->justReturn( 'category' === $where );
		Functions\when( 'is_product_tag' )->justReturn( 'tag' === $where );
		Functions\when( 'get_queried_object' )->justReturn( $term_id ? new \WP_Term( $term_id ) : null );
	}

	/**
	 * @param array<int, array<string, mixed>> $tiles ID => pola bez prefiksu.
	 */
	private function tiles( array $tiles ): void {
		Functions\when( 'get_posts' )->justReturn( array_keys( $tiles ) );
		foreach ( $tiles as $id => $fields ) {
			$fields += array( 'active' => 'yes', 'categories' => array( 10 ) );
			foreach ( $fields as $key => $value ) {
				$this->meta[ $id ][ '_rynkowski_kafel_' . $key ] = $value;
			}
		}
	}

	/**
	 * Symuluje pętlę z $count produktami i zwraca wynik.
	 */
	private function loop( int $count ): string {
		return $this->capture(
			static function () use ( $count ) {
				for ( $i = 1; $i <= $count; $i++ ) {
					Tile_Frontend::before_product();
					echo "<li>P{$i}</li>";
				}
			}
		);
	}

	public function test_register_hooks_loop_and_assets(): void {
		Actions\expectAdded( 'woocommerce_shop_loop' )->once()->with( array( Tile_Frontend::class, 'before_product' ), 5 );
		Actions\expectAdded( 'wp_enqueue_scripts' )->once()->with( array( Tile_Frontend::class, 'enqueue' ) );

		Tile_Frontend::register();
	}

	public function test_shortcode_loops_and_other_pages_are_untouched(): void {
		$this->tiles( array( 1 => array( 'type' => 'text', 'heading' => 'X', 'position' => 1 ) ) );

		Functions\when( 'wc_get_loop_prop' )->justReturn( true );
		$this->assertSame( '<li>P1</li>', $this->loop( 1 ) );

		Functions\when( 'wc_get_loop_prop' )->justReturn( false );
		$this->context( 'other' );
		$this->assertSame( '<li>P1</li>', $this->loop( 1 ) );
	}

	public function test_tiles_take_their_grid_positions_and_collisions_queue_up(): void {
		Functions\when( 'wp_get_attachment_image' )->justReturn( '<img alt="Promocja">' );
		Functions\when( 'wp_enqueue_script' )->justReturn( null );
		$this->tiles(
			array(
				1 => array( 'type' => 'text', 'heading' => 'A', 'position' => 2 ),
				2 => array( 'type' => 'text', 'heading' => 'B', 'position' => 2 ),
				3 => array( 'type' => 'text', 'heading' => 'C', 'position' => 1 ),
				4 => array( 'type' => 'text', 'heading' => 'Za daleko', 'position' => 20 ),
			)
		);

		$html = $this->loop( 3 );

		preg_match_all( '/<h2 class="rynkowski-kafel__naglowek">(\w+)<\/h2>|<li>(P\d)<\/li>/', $html, $m );
		$order = array_values( array_filter( array_map( static fn( $a, $b ) => $a . $b, $m[1], $m[2] ) ) );
		// Pozycja to numer komórki siatki: C=1, A=2, B też chciał 2, więc staje na 3, produkty dalej.
		$this->assertSame( array( 'C', 'A', 'B', 'P1', 'P2', 'P3' ), $order );
	}

	public function test_filters_by_place_state_page_and_content(): void {
		$this->tiles(
			array(
				1 => array( 'type' => 'text', 'heading' => 'Kategoria', 'position' => 1 ),
				2 => array( 'type' => 'text', 'heading' => 'Inna', 'position' => 1, 'categories' => array( 11 ) ),
				3 => array( 'type' => 'text', 'heading' => 'Wyłączony', 'position' => 1, 'active' => 'no' ),
				4 => array( 'type' => 'text', 'position' => 1 ),
				5 => array( 'type' => 'image', 'position' => 1 ),
				6 => array( 'type' => 'video', 'position' => 1 ),
			)
		);

		$html = $this->loop( 1 );

		$this->assertStringContainsString( '>Kategoria</h2>', $html );
		$this->assertSame( 1, substr_count( $html, 'rynkowski-kafel ' ) );
	}

	public function test_first_page_only_tiles_skip_later_pages(): void {
		Functions\when( 'get_query_var' )->justReturn( 2 );
		$this->tiles(
			array(
				1 => array( 'type' => 'text', 'heading' => 'Tylko 1', 'position' => 1 ),
				2 => array( 'type' => 'text', 'heading' => 'Wszędzie', 'position' => 1, 'first_page_only' => 'no' ),
			)
		);

		$html = $this->loop( 1 );

		$this->assertStringNotContainsString( 'Tylko 1', $html );
		$this->assertStringContainsString( 'Wszędzie', $html );
	}

	public function test_shop_and_tag_lists_use_their_own_rules(): void {
		$this->tiles(
			array(
				1 => array( 'type' => 'text', 'heading' => 'Sklep', 'position' => 1, 'on_shop' => 'yes', 'categories' => array() ),
				2 => array( 'type' => 'text', 'heading' => 'Tag', 'position' => 1, 'tags' => array( 20 ), 'categories' => array() ),
			)
		);

		$this->context( 'shop' );
		$shop = $this->loop( 1 );
		$this->assertStringContainsString( 'Sklep', $shop );
		$this->assertStringNotContainsString( 'Tag', $shop );

		( new \ReflectionProperty( Tile_Frontend::class, 'tiles' ) )->setValue( null, null );
		( new \ReflectionProperty( Tile_Frontend::class, 'cells' ) )->setValue( null, 0 );
		$this->context( 'tag', 20 );
		$tag = $this->loop( 1 );
		$this->assertStringContainsString( 'Tag', $tag );
		$this->assertStringNotContainsString( 'Sklep', $tag );
	}

	public function test_text_tile_escapes_content_and_shows_button_only_with_link(): void {
		Functions\when( 'esc_url' )->alias( static fn( string $url ): string => str_starts_with( $url, 'https://' ) ? $url : '' );
		$this->tiles(
			array(
				1 => array(
					'type'        => 'text',
					'heading'     => '<script>alert(1)</script>',
					'description' => "Linia <b>1</b>\nLinia 2",
					'button'      => 'Kup',
					'link'        => 'https://sklep.test/promo',
					'bg_color'    => '#ff0000',
					'text_color'  => 'red;}body{',
					'position'    => 1,
				),
				2 => array( 'type' => 'text', 'description' => 'Bez linku', 'button' => 'Ukryty', 'position' => 1 ),
			)
		);

		$html = $this->loop( 0 ) . $this->capture( static fn() => Tile_Frontend::before_product() );

		$this->assertStringContainsString(
			'<li class="rynkowski-kafel rynkowski-kafel--text"><a class="rynkowski-kafel__wnetrze" href="https://sklep.test/promo"><div class="rynkowski-kafel__tekst" style="--kafel-bg:#ff0000;"><h2 class="rynkowski-kafel__naglowek">&lt;script&gt;alert(1)&lt;/script&gt;</h2><p class="rynkowski-kafel__opis">Linia &lt;b&gt;1&lt;/b&gt;<br />' . "\n" . 'Linia 2</p><span class="rynkowski-kafel__przycisk ct-button">Kup</span></div></a></li>',
			$html
		);
		$this->assertStringContainsString( '<div class="rynkowski-kafel__wnetrze"><div class="rynkowski-kafel__tekst"><p class="rynkowski-kafel__opis">Bez linku</p></div></div>', $html );
		$this->assertStringNotContainsString( 'Ukryty', $html );
	}

	public function test_text_tile_with_text_color_only(): void {
		$this->tiles( array( 1 => array( 'type' => 'text', 'heading' => 'H', 'text_color' => '#fff', 'position' => 1 ) ) );

		$this->assertStringContainsString( 'style="--kafel-color:#fff;"', $this->loop( 1 ) );
	}

	public function test_image_tile_uses_alt_or_falls_back_to_tile_name(): void {
		Functions\when( 'wp_get_attachment_image' )->alias(
			static fn( int $id, string $size, bool $icon, array $attr ): string => '<img data-id="' . $id . '" data-size="' . $size . '" class="' . $attr['class'] . '" loading="' . $attr['loading'] . '">'
		);
		$this->meta[30]['_wp_attachment_image_alt'] = 'Nowa kolekcja';
		$this->tiles(
			array(
				1 => array( 'type' => 'image', 'image_id' => 30, 'link' => 'https://sklep.test/a', 'position' => 1 ),
				2 => array( 'type' => 'image', 'image_id' => 31, 'link' => 'https://sklep.test/b', 'position' => 1 ),
				3 => array( 'type' => 'image', 'image_id' => 32, 'position' => 1 ),
			)
		);

		$html = $this->loop( 1 );

		$this->assertStringContainsString( '<a class="rynkowski-kafel__wnetrze" href="https://sklep.test/a"><img data-id="30" data-size="woocommerce_single" class="rynkowski-kafel__media" loading="lazy"></a>', $html );
		$this->assertStringContainsString( 'href="https://sklep.test/b" aria-label="Kafel 2">', $html );
		$this->assertStringContainsString( '<div class="rynkowski-kafel__wnetrze"><img data-id="32"', $html );
	}

	public function test_video_tile_has_poster_pause_button_and_loads_script(): void {
		Functions\when( 'wp_get_attachment_url' )->justReturn( 'https://sklep.test/v.mp4' );
		Functions\when( 'wp_get_attachment_image_url' )->justReturn( 'https://sklep.test/p.jpg' );
		Functions\expect( 'wp_enqueue_script' )->twice()->with( 'rynkowski-kafle' );
		$this->meta[40]['_wp_attachment_image_alt'] = 'Film o torbach';
		$this->tiles(
			array(
				1 => array( 'type' => 'video', 'video_id' => 31, 'poster_id' => 40, 'link' => 'https://sklep.test/v', 'position' => 1 ),
				2 => array( 'type' => 'video', 'video_id' => 32, 'link' => 'https://sklep.test/w', 'position' => 1 ),
			)
		);

		$html = $this->loop( 1 );

		$this->assertStringContainsString(
			'<li class="rynkowski-kafel rynkowski-kafel--video"><a class="rynkowski-kafel__wnetrze" href="https://sklep.test/v"><video class="rynkowski-kafel__media" autoplay muted loop playsinline preload="metadata" poster="https://sklep.test/p.jpg"><source src="https://sklep.test/v.mp4" type="video/mp4"></video></a><button type="button" class="rynkowski-kafel__pauza" aria-pressed="false">Zatrzymaj wideo</button></li>',
			$html
		);
		$this->assertStringContainsString( 'href="https://sklep.test/w" aria-label="Kafel 2"><video class="rynkowski-kafel__media" autoplay muted loop playsinline preload="metadata"><source', $html );
	}

	public function test_enqueue_only_on_product_lists(): void {
		$this->context( 'other' );
		Functions\expect( 'wp_register_style' )->never();

		Tile_Frontend::enqueue();
	}

	public function test_enqueue_registers_styles_and_video_script(): void {
		Functions\when( 'plugins_url' )->alias( static fn( string $path, string $file ): string => 'https://sklep.test/' . basename( dirname( $file ) ) . '/' . $path );
		Functions\expect( 'wp_register_style' )->once()->with( 'rynkowski-kafle', false, array(), Plugin::VERSION );
		Functions\expect( 'wp_enqueue_style' )->once()->with( 'rynkowski-kafle' );
		// Domyślnie: 288 px wysokości i tło w kolorze motywu, jak przed ustawieniami.
		Functions\expect( 'wp_add_inline_style' )->once()->with(
			'rynkowski-kafle',
			\Mockery::on(
				static fn( string $css ): bool => str_starts_with( $css, '.rynkowski-kafel{' )
					&& str_contains( $css, 'min-height:288px;' )
					&& str_contains( $css, 'background:var(--kafel-bg,var(--theme-palette-color-1,#000));' )
			)
		);
		Functions\expect( 'wp_register_script' )->once()->with( 'rynkowski-kafle', 'https://sklep.test/kafle/assets/kafle.js', array(), Plugin::VERSION, true );
		Functions\expect( 'wp_localize_script' )->once()->with( 'rynkowski-kafle', 'rynkowskiKafle', array( 'zatrzymaj' => 'Zatrzymaj wideo', 'odtworz' => 'Odtwórz wideo' ) );

		Tile_Frontend::enqueue();
	}

	public function test_styles_use_height_and_background_from_settings(): void {
		Functions\when( 'plugins_url' )->justReturn( 'x' );
		Functions\when( 'wp_register_style' )->justReturn( true );
		Functions\when( 'wp_enqueue_style' )->justReturn( null );
		Functions\when( 'wp_register_script' )->justReturn( true );
		Functions\when( 'wp_localize_script' )->justReturn( true );
		$this->options[ Tile_Settings::OPTION ] = array( 'min_wysokosc' => '400', 'kolor_tla' => '#aabbcc' );
		Functions\expect( 'wp_add_inline_style' )->once()->with(
			'rynkowski-kafle',
			\Mockery::on( static fn( string $css ): bool => str_contains( $css, 'min-height:400px;' ) && str_contains( $css, 'background:var(--kafel-bg,#aabbcc);' ) )
		);

		Tile_Frontend::enqueue();
	}

	public function test_styles_ignore_css_injection_from_database(): void {
		Functions\when( 'plugins_url' )->justReturn( 'x' );
		Functions\when( 'wp_register_style' )->justReturn( true );
		Functions\when( 'wp_enqueue_style' )->justReturn( null );
		Functions\when( 'wp_register_script' )->justReturn( true );
		Functions\when( 'wp_localize_script' )->justReturn( true );
		$this->options[ Tile_Settings::OPTION ] = array( 'min_wysokosc' => '1px;}body{display:none', 'kolor_tla' => 'red;}body{' );
		Functions\expect( 'wp_add_inline_style' )->once()->with(
			'rynkowski-kafle',
			\Mockery::on( static fn( string $css ): bool => str_contains( $css, 'min-height:288px;' ) && ! str_contains( $css, 'body{' ) )
		);

		Tile_Frontend::enqueue();
	}

	public function test_video_without_autostart_starts_paused_with_play_button(): void {
		Functions\when( 'wp_get_attachment_url' )->justReturn( 'https://sklep.test/v.mp4' );
		Functions\when( 'wp_enqueue_script' )->justReturn( null );
		$this->options[ Tile_Settings::OPTION ] = array( 'autostart' => 'no' );
		$this->tiles( array( 1 => array( 'type' => 'video', 'video_id' => 31, 'position' => 1 ) ) );

		$html = $this->loop( 1 );

		$this->assertStringContainsString( '<video class="rynkowski-kafel__media" muted loop playsinline preload="metadata"><source', $html );
		$this->assertStringNotContainsString( 'autoplay', $html );
		$this->assertStringContainsString( '<button type="button" class="rynkowski-kafel__pauza" aria-pressed="true">Odtwórz wideo</button>', $html );
	}
}
