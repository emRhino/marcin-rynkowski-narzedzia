<?php
// phpcs:ignoreFile -- plik testowy.

namespace Rynkowski\Narzedzia\Moduly\Kafle\Tests;

use Brain\Monkey\Actions;
use Brain\Monkey\Filters;
use Brain\Monkey\Functions;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use Rynkowski\Narzedzia\Moduly\Kafle\Tile_Admin;
use Rynkowski\Narzedzia\Moduly\Kafle\Tiles;
use Rynkowski\Narzedzia\Plugin;
use Rynkowski\Narzedzia\Tests\TestCase;

final class TileAdminTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		Functions\when( 'get_the_title' )->alias( static fn( int $id ): string => 'plik-' . $id . '.mp4' );
		Functions\when( 'get_terms' )->justReturn( array() );
	}

	private function post( int $id = 7, string $type = Tiles::POST_TYPE ): \WP_Post {
		return new \WP_Post( $id, $type );
	}

	private function valid_request(): void {
		$_POST[ Tile_Admin::NONCE ] = 'nonce';
		Functions\when( 'wp_verify_nonce' )->alias( static fn( $nonce, $action ): bool => 'nonce' === $nonce && Tile_Admin::NONCE === $action );
		Functions\when( 'current_user_can' )->alias( static fn( string $cap, int $id ): bool => 'edit_post' === $cap && 7 === $id );
	}

	public function test_register_hooks_editor_list_and_assets(): void {
		Actions\expectAdded( 'add_meta_boxes_' . Tiles::POST_TYPE )->once();
		Actions\expectAdded( 'save_post_' . Tiles::POST_TYPE )->once()->with( array( Tile_Admin::class, 'save' ), 10, 2 );
		Actions\expectAdded( 'admin_enqueue_scripts' )->once();
		Filters\expectAdded( 'manage_' . Tiles::POST_TYPE . '_posts_columns' )->once();
		Actions\expectAdded( 'manage_' . Tiles::POST_TYPE . '_posts_custom_column' )->once();

		Tile_Admin::register();
	}

	public function test_adds_content_and_display_boxes(): void {
		Functions\expect( 'add_meta_box' )->once()->with( 'rynkowski-kafel-tresc', 'Treść kafla', array( Tile_Admin::class, 'render_content' ), Tiles::POST_TYPE, 'normal', 'high' );
		Functions\expect( 'add_meta_box' )->once()->with( 'rynkowski-kafel-wyswietlanie', 'Gdzie i kiedy', array( Tile_Admin::class, 'render_display' ), Tiles::POST_TYPE, 'normal', 'default' );

		Tile_Admin::add_meta_boxes();
	}

	public function test_render_content_shows_fields_media_and_escapes(): void {
		Functions\expect( 'wp_nonce_field' )->once()->with( Tile_Admin::NONCE, Tile_Admin::NONCE );
		Functions\when( 'wp_attachment_is_image' )->alias( static fn( int $id ): bool => 30 === $id );
		Functions\when( 'wp_get_attachment_image' )->alias( static fn( int $id, string $size ): string => '<img src="' . $id . '-' . $size . '.jpg">' );
		$this->meta[7] = array(
			'_rynkowski_kafel_type'        => 'text',
			'_rynkowski_kafel_link'        => 'https://sklep.test/"><script>',
			'_rynkowski_kafel_image_id'    => 30,
			'_rynkowski_kafel_video_id'    => 31,
			'_rynkowski_kafel_description' => '</textarea><script>alert(1)</script>',
			'_rynkowski_kafel_bg_color'    => '#123456',
		);

		$html = $this->capture( fn() => Tile_Admin::render_content( $this->post() ) );

		$this->assertMatchesRegularExpression( '/value="text" class="rynkowski-kafel-typ"\s+checked=\'checked\'>/', $html );
		$this->assertStringContainsString( 'value="https://sklep.test/&quot;&gt;&lt;script&gt;"', $html );
		$this->assertStringContainsString( '<img src="30-thumbnail.jpg">', $html );
		$this->assertStringContainsString( '<code>plik-31.mp4</code>', $html );
		$this->assertStringContainsString( '&lt;/textarea&gt;&lt;script&gt;alert(1)&lt;/script&gt;</textarea>', $html );
		$this->assertStringNotContainsString( '<script>', $html );
		$this->assertStringContainsString( 'data-library="video/mp4"', $html );
		$this->assertStringContainsString( 'value="#123456"', $html );
		// Pusty obrazek zastępczy nie ma podglądu.
		$this->assertMatchesRegularExpression( '/name="rynkowski_poster_id" value=""/', $html );
	}

	public function test_render_display_shows_rules(): void {
		$this->meta[7] = array(
			'_rynkowski_kafel_active'          => 'yes',
			'_rynkowski_kafel_on_shop'         => 'yes',
			'_rynkowski_kafel_position'        => 4,
			'_rynkowski_kafel_first_page_only' => 'no',
		);

		$html = $this->capture( fn() => Tile_Admin::render_display( $this->post() ) );

		$this->assertMatchesRegularExpression( '/name="rynkowski_active" value="yes"\s+checked=\'checked\'>/', $html );
		$this->assertMatchesRegularExpression( '/name="rynkowski_on_shop" value="yes"\s+checked=\'checked\'>/', $html );
		$this->assertMatchesRegularExpression( '/name="rynkowski_first_page_only" value="yes"\s+>/', $html );
		$this->assertStringContainsString( 'value="4"', $html );
		$this->assertStringContainsString( 'name="rynkowski_categories[]"', $html );
	}

	public function test_save_ignores_missing_or_bad_nonce(): void {
		Tile_Admin::save( 7, $this->post() );

		$_POST[ Tile_Admin::NONCE ] = 'zly';
		Functions\when( 'wp_verify_nonce' )->justReturn( false );
		Tile_Admin::save( 7, $this->post() );

		$this->assertSame( array(), $this->meta );
	}

	#[RunInSeparateProcess]
	#[PreserveGlobalState( false )]
	public function test_save_ignores_autosave(): void {
		$this->valid_request();
		define( 'DOING_AUTOSAVE', true );

		Tile_Admin::save( 7, $this->post() );

		$this->assertSame( array(), $this->meta );
	}

	public function test_save_requires_capability_and_own_post_type(): void {
		$this->valid_request();

		Tile_Admin::save( 8, $this->post( 8 ) );
		Tile_Admin::save( 7, $this->post( 7, 'product' ) );

		$this->assertSame( array(), $this->meta );
	}

	public function test_save_sanitizes_and_validates_everything(): void {
		$this->valid_request();
		Functions\when( 'esc_url_raw' )->alias(
			static fn( string $url, array $protocols ): string => array( 'http', 'https' ) === $protocols && str_starts_with( $url, 'https://' ) ? $url : ''
		);
		Functions\when( 'get_term' )->alias( static fn( int $id, string $taxonomy ) => 10 === $id && 'product_cat' === $taxonomy ? new \WP_Term( 10 ) : null );
		Functions\when( 'get_post_type' )->alias( static fn( int $id ): string => in_array( $id, array( 30, 31, 32 ), true ) ? 'attachment' : 'post' );
		Functions\when( 'wp_attachment_is_image' )->alias( static fn( int $id ): bool => 30 === $id );
		Functions\when( 'get_post_mime_type' )->alias( static fn( int $id ): string => 31 === $id ? 'video/mp4' : 'video/webm' );
		$_POST = array_merge(
			$_POST,
			array(
				'rynkowski_type'            => 'video',
				'rynkowski_link'            => 'https://sklep.test/promo',
				'rynkowski_active'          => 'yes',
				'rynkowski_first_page_only' => 'yes',
				'rynkowski_position'        => '0',
				'rynkowski_categories'      => array( '10', '11' ),
				'rynkowski_image_id'        => '30',
				'rynkowski_poster_id'       => '31',
				'rynkowski_video_id'        => '31',
				'rynkowski_heading'         => '<b>Promo</b>',
				'rynkowski_button'          => 'Kup',
				'rynkowski_description'     => "<i>Linia</i>\nDruga",
				'rynkowski_bg_color'        => '#ff0000',
				'rynkowski_text_color'      => 'javascript:x',
			)
		);

		Tile_Admin::save( 7, $this->post() );

		$this->assertSame(
			array(
				'_rynkowski_kafel_type'            => 'video',
				'_rynkowski_kafel_link'            => 'https://sklep.test/promo',
				'_rynkowski_kafel_active'          => 'yes',
				'_rynkowski_kafel_on_shop'         => 'no',
				'_rynkowski_kafel_first_page_only' => 'yes',
				'_rynkowski_kafel_position'        => 1,
				'_rynkowski_kafel_categories'      => array( 10 ),
				'_rynkowski_kafel_tags'            => array(),
				'_rynkowski_kafel_image_id'        => 30,
				'_rynkowski_kafel_poster_id'       => 0,
				'_rynkowski_kafel_video_id'        => 31,
				'_rynkowski_kafel_heading'         => 'Promo',
				'_rynkowski_kafel_button'          => 'Kup',
				'_rynkowski_kafel_description'     => "Linia\nDruga",
				'_rynkowski_kafel_bg_color'        => '#ff0000',
				'_rynkowski_kafel_text_color'      => '',
			),
			$this->meta[7]
		);
	}

	public function test_save_rejects_unknown_type_dangerous_link_and_foreign_attachments(): void {
		$this->valid_request();
		Functions\when( 'esc_url_raw' )->justReturn( '' );
		Functions\when( 'get_post_type' )->justReturn( 'post' );
		$_POST['rynkowski_type']     = 'php';
		$_POST['rynkowski_link']     = 'javascript:alert(1)';
		$_POST['rynkowski_image_id'] = '99';
		$_POST['rynkowski_position'] = '5';

		Tile_Admin::save( 7, $this->post() );

		$this->assertSame( 'image', $this->meta[7]['_rynkowski_kafel_type'] );
		$this->assertSame( '', $this->meta[7]['_rynkowski_kafel_link'] );
		$this->assertSame( 0, $this->meta[7]['_rynkowski_kafel_image_id'] );
		$this->assertSame( 5, $this->meta[7]['_rynkowski_kafel_position'] );
	}

	public function test_save_with_empty_form_uses_defaults(): void {
		$this->valid_request();

		Tile_Admin::save( 7, $this->post() );

		$this->assertSame( 'image', $this->meta[7]['_rynkowski_kafel_type'] );
		$this->assertSame( '', $this->meta[7]['_rynkowski_kafel_link'] );
		$this->assertSame( 1, $this->meta[7]['_rynkowski_kafel_position'] );
		$this->assertSame( 'no', $this->meta[7]['_rynkowski_kafel_first_page_only'] );
		$this->assertSame( '', $this->meta[7]['_rynkowski_kafel_description'] );
		$this->assertSame( '', $this->meta[7]['_rynkowski_kafel_bg_color'] );
	}

	public function test_enqueue_only_on_tile_screens(): void {
		Functions\expect( 'wp_enqueue_style' )->never();
		Functions\when( 'get_current_screen' )->justReturn( null );
		Tile_Admin::enqueue();

		Functions\when( 'get_current_screen' )->justReturn( new \WP_Screen( 'product', 'post', 'product' ) );
		Tile_Admin::enqueue();
	}

	public function test_enqueue_on_list_skips_media_library(): void {
		Functions\when( 'get_current_screen' )->justReturn( new \WP_Screen( 'edit-' . Tiles::POST_TYPE, 'edit', Tiles::POST_TYPE ) );
		Functions\expect( 'wp_enqueue_style' )->twice();
		Functions\expect( 'wp_enqueue_script' )->once()->with( 'wc-enhanced-select' );
		Functions\expect( 'wp_enqueue_media' )->never();

		Tile_Admin::enqueue();
	}

	public function test_enqueue_on_editor_loads_media_and_module_script(): void {
		Functions\when( 'get_current_screen' )->justReturn( new \WP_Screen( Tiles::POST_TYPE, 'post', Tiles::POST_TYPE ) );
		Functions\when( 'wp_enqueue_style' )->justReturn( null );
		Functions\expect( 'wp_enqueue_media' )->once();
		Functions\when( 'plugins_url' )->alias( static fn( string $path, string $file ): string => 'https://sklep.test/' . basename( dirname( $file ) ) . '/' . $path );
		Functions\expect( 'wp_enqueue_script' )->once()->with( 'wc-enhanced-select' );
		Functions\expect( 'wp_enqueue_script' )->once()->with( 'rynkowski-kafle-admin', 'https://sklep.test/kafle/assets/kafle-admin.js', array( 'jquery', 'wp-color-picker' ), Plugin::VERSION, true );
		Functions\expect( 'wp_localize_script' )->once()->with( 'rynkowski-kafle-admin', 'rynkowskiKafle', \Mockery::type( 'array' ) );

		Tile_Admin::enqueue();
	}

	public function test_columns_keep_checkbox(): void {
		$this->assertSame(
			array( 'cb', 'title', 'rynkowski_type', 'rynkowski_where', 'rynkowski_position', 'rynkowski_active' ),
			array_keys( Tile_Admin::columns( array( 'cb' => 'x' ) ) )
		);
		$this->assertSame( '', Tile_Admin::columns( array() )['cb'] );
	}

	public function test_render_columns(): void {
		$this->meta[7] = array(
			'_rynkowski_kafel_type'            => 'video',
			'_rynkowski_kafel_on_shop'         => 'yes',
			'_rynkowski_kafel_categories'      => array( 1, 2 ),
			'_rynkowski_kafel_tags'            => array( 3 ),
			'_rynkowski_kafel_position'        => 4,
			'_rynkowski_kafel_active'          => 'yes',
		);
		$this->meta[8] = array(
			'_rynkowski_kafel_type'            => 'text',
			'_rynkowski_kafel_first_page_only' => 'no',
		);

		$this->assertSame( 'Wideo', $this->capture( fn() => Tile_Admin::render_column( 'rynkowski_type', 7 ) ) );
		$this->assertSame( 'Tekst', $this->capture( fn() => Tile_Admin::render_column( 'rynkowski_type', 8 ) ) );
		$this->assertSame( 'Grafika', $this->capture( fn() => Tile_Admin::render_column( 'rynkowski_type', 9 ) ) );
		$this->assertSame( 'sklep, kategorie: 2, tagi: 1', $this->capture( fn() => Tile_Admin::render_column( 'rynkowski_where', 7 ) ) );
		$this->assertSame( '—', $this->capture( fn() => Tile_Admin::render_column( 'rynkowski_where', 8 ) ) );
		$this->assertSame( '4 <span class="description">(1. strona)</span>', $this->capture( fn() => Tile_Admin::render_column( 'rynkowski_position', 7 ) ) );
		$this->assertSame( '1', $this->capture( fn() => Tile_Admin::render_column( 'rynkowski_position', 8 ) ) );
		$this->assertSame( 'Tak', $this->capture( fn() => Tile_Admin::render_column( 'rynkowski_active', 7 ) ) );
		$this->assertSame( '—', $this->capture( fn() => Tile_Admin::render_column( 'rynkowski_active', 8 ) ) );
		$this->assertSame( '', $this->capture( fn() => Tile_Admin::render_column( 'inna', 7 ) ) );
	}
}
