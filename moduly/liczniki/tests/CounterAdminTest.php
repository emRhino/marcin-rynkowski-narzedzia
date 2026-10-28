<?php
// phpcs:ignoreFile -- plik testowy.

namespace Rynkowski\Narzedzia\Moduly\Liczniki\Tests;

use Brain\Monkey\Actions;
use Brain\Monkey\Filters;
use Brain\Monkey\Functions;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use Rynkowski\Narzedzia\Moduly\Liczniki\Counter_Admin;
use Rynkowski\Narzedzia\Moduly\Liczniki\Counters;
use Rynkowski\Narzedzia\Tests\TestCase;

final class CounterAdminTest extends TestCase {

	// 2026-11-30 23:59 czasu polskiego (CET, UTC+1) = 22:59 UTC.
	private const END_LOCAL = '2026-11-30T23:59';
	private const END_UTC   = 1796079540;

	protected function setUp(): void {
		parent::setUp();
		Functions\when( 'get_the_title' )->justReturn( 'Licznik' );
		Functions\when( 'get_terms' )->justReturn( array() );
		Functions\when( 'wp_timezone' )->alias( static fn() => new \DateTimeZone( 'Europe/Warsaw' ) );
		Functions\when( 'wp_date' )->alias(
			static fn( string $format, int $timestamp ): string => ( new \DateTimeImmutable( '@' . $timestamp ) )->setTimezone( new \DateTimeZone( 'Europe/Warsaw' ) )->format( $format )
		);
	}

	private function post( int $id = 7, string $type = Counters::POST_TYPE ): \WP_Post {
		return new \WP_Post( $id, $type );
	}

	private function valid_request(): void {
		$_POST[ Counter_Admin::NONCE ] = 'nonce';
		Functions\when( 'wp_verify_nonce' )->alias( static fn( $nonce, $action ): bool => 'nonce' === $nonce && Counter_Admin::NONCE === $action );
		Functions\when( 'current_user_can' )->alias( static fn( string $cap, int $id ): bool => 'edit_post' === $cap && 7 === $id );
	}

	public function test_register_hooks_editor_list_and_woocommerce_look(): void {
		Filters\expectAdded( 'get_user_option_screen_layout_' . Counters::POST_TYPE )->once();
		Actions\expectAdded( 'add_meta_boxes_' . Counters::POST_TYPE )->once();
		Actions\expectAdded( 'save_post_' . Counters::POST_TYPE )->once()->with( array( Counter_Admin::class, 'save' ), 10, 2 );
		Filters\expectAdded( 'manage_' . Counters::POST_TYPE . '_posts_columns' )->once();
		Actions\expectAdded( 'manage_' . Counters::POST_TYPE . '_posts_custom_column' )->once();

		Counter_Admin::register();
	}

	public function test_adds_promo_and_assignment_boxes(): void {
		Functions\expect( 'add_meta_box' )->once()->with( 'rynkowski-licznik-promocja', 'Promocja', array( Counter_Admin::class, 'render_promo' ), Counters::POST_TYPE, 'normal', 'high' );
		Functions\expect( 'add_meta_box' )->once()->with( 'rynkowski-licznik-przypisanie', 'Przypisanie', array( Counter_Admin::class, 'render_assignment' ), Counters::POST_TYPE, 'normal', 'default' );

		Counter_Admin::add_meta_boxes();
	}

	public function test_render_promo_shows_local_end_time_and_escapes_text(): void {
		Functions\expect( 'wp_nonce_field' )->once()->with( Counter_Admin::NONCE, Counter_Admin::NONCE );
		$this->meta[7] = array(
			'_rynkowski_licznik_text'   => '"><script>alert(1)</script>',
			'_rynkowski_licznik_end'    => self::END_UTC,
			'_rynkowski_licznik_active' => 'yes',
		);

		$html = $this->capture( fn() => Counter_Admin::render_promo( $this->post() ) );

		$this->assertStringContainsString( 'value="&quot;&gt;&lt;script&gt;alert(1)&lt;/script&gt;"', $html );
		$this->assertStringNotContainsString( '<script>', $html );
		$this->assertStringContainsString( 'type="datetime-local" id="rynkowski-end" name="rynkowski_end" value="' . self::END_LOCAL . '"', $html );
		$this->assertMatchesRegularExpression( '/name="rynkowski_active" value="yes"\s+checked=\'checked\'>/', $html );
		$this->assertStringContainsString( '<label for="rynkowski-end">Koniec promocji</label><span class="woocommerce-help-tip"', $html );
	}

	public function test_render_promo_without_end_leaves_field_empty(): void {
		Functions\when( 'wp_nonce_field' )->justReturn( '' );

		$html = $this->capture( fn() => Counter_Admin::render_promo( $this->post( 8 ) ) );

		$this->assertStringContainsString( 'name="rynkowski_end" value=""', $html );
	}

	public function test_render_assignment_uses_shared_fields(): void {
		$this->meta[7] = array( '_rynkowski_licznik_products' => array( 5 ) );
		Functions\when( 'wc_get_product' )->justReturn( new \WC_Product( 5, 'Torba' ) );
		Functions\when( 'wp_strip_all_tags' )->alias( 'strip_tags' );

		$html = $this->capture( fn() => Counter_Admin::render_assignment( $this->post() ) );

		$this->assertStringContainsString( '<option value="5" selected>Torba</option>', $html );
		$this->assertStringContainsString( 'name="rynkowski_categories[]"', $html );
		$this->assertStringContainsString( 'name="rynkowski_tags[]"', $html );
	}

	public function test_save_ignores_missing_or_bad_nonce(): void {
		Counter_Admin::save( 7, $this->post() );

		$_POST[ Counter_Admin::NONCE ] = 'zly';
		Functions\when( 'wp_verify_nonce' )->justReturn( false );
		Counter_Admin::save( 7, $this->post() );

		$this->assertSame( array(), $this->meta );
	}

	#[RunInSeparateProcess]
	#[PreserveGlobalState( false )]
	public function test_save_ignores_autosave(): void {
		$this->valid_request();
		define( 'DOING_AUTOSAVE', true );

		Counter_Admin::save( 7, $this->post() );

		$this->assertSame( array(), $this->meta );
	}

	public function test_save_requires_capability_and_own_post_type(): void {
		$this->valid_request();

		Counter_Admin::save( 8, $this->post( 8 ) );
		Counter_Admin::save( 7, $this->post( 7, 'product' ) );

		$this->assertSame( array(), $this->meta );
	}

	public function test_save_sanitizes_and_validates_everything(): void {
		$this->valid_request();
		Functions\when( 'get_post_type' )->alias( static fn( int $id ): string => 5 === $id ? 'product' : 'post' );
		Functions\when( 'get_term' )->alias( static fn( int $id, string $taxonomy ) => 10 === $id && 'product_cat' === $taxonomy ? new \WP_Term( 10 ) : null );
		$_POST = array_merge(
			$_POST,
			array(
				'rynkowski_text'       => '<script>alert(1)</script>-12% Z KODEM: FLASH',
				'rynkowski_end'        => self::END_LOCAL,
				'rynkowski_active'     => 'yes',
				'rynkowski_products'   => array( '5', '6' ),
				'rynkowski_categories' => array( '10', '11' ),
				'rynkowski_tags'       => array( '99' ),
			)
		);

		Counter_Admin::save( 7, $this->post() );

		$this->assertSame(
			array(
				'_rynkowski_licznik_text'       => 'alert(1)-12% Z KODEM: FLASH',
				'_rynkowski_licznik_end'        => self::END_UTC,
				'_rynkowski_licznik_active'     => 'yes',
				'_rynkowski_licznik_products'   => array( 5 ),
				'_rynkowski_licznik_categories' => array( 10 ),
				'_rynkowski_licznik_tags'       => array(),
			),
			$this->meta[7]
		);
	}

	public function test_save_with_empty_form_clears_fields(): void {
		$this->valid_request();

		Counter_Admin::save( 7, $this->post() );

		$this->assertSame( '', $this->meta[7]['_rynkowski_licznik_text'] );
		$this->assertSame( 0, $this->meta[7]['_rynkowski_licznik_end'] );
		$this->assertSame( 'no', $this->meta[7]['_rynkowski_licznik_active'] );
	}

	public function test_parse_end_uses_shop_timezone_including_summer_time(): void {
		$this->assertSame( self::END_UTC, Counter_Admin::parse_end( self::END_LOCAL ) );
		// Lipiec: czas letni (UTC+2).
		$this->assertSame( ( new \DateTimeImmutable( '2026-07-01 10:00', new \DateTimeZone( 'UTC' ) ) )->getTimestamp(), Counter_Admin::parse_end( '2026-07-01T12:00' ) );
	}

	public function test_parse_end_rejects_invalid_values(): void {
		foreach ( array( '', 'jutro', '2026-11-30', '2026-11-30 23:59', '2026-02-31T10:00', '2026-11-30T25:00', '2026-11-30T23:59:59', '<script>' ) as $value ) {
			$this->assertSame( 0, Counter_Admin::parse_end( $value ), $value );
		}
	}

	public function test_columns_keep_checkbox(): void {
		$this->assertSame(
			array( 'cb', 'title', 'rynkowski_text', 'rynkowski_end', 'rynkowski_status', 'rynkowski_assigned' ),
			array_keys( Counter_Admin::columns( array( 'cb' => 'x' ) ) )
		);
		$this->assertSame( '', Counter_Admin::columns( array() )['cb'] );
	}

	public function test_render_columns(): void {
		Functions\when( 'get_option' )->alias(
			static fn( string $name ) => array( 'date_format' => 'j.m.Y', 'time_format' => 'H:i' )[ $name ] ?? false
		);
		$future = time() + 3600;
		$past   = time() - 3600;
		$this->meta[1] = array( '_rynkowski_licznik_text' => '<b>FLASH</b>', '_rynkowski_licznik_end' => self::END_UTC, '_rynkowski_licznik_active' => 'yes', '_rynkowski_licznik_products' => array( 1, 2 ), '_rynkowski_licznik_tags' => array( 3 ) );
		$this->meta[2] = array( '_rynkowski_licznik_end' => $future, '_rynkowski_licznik_active' => 'yes' );
		$this->meta[3] = array( '_rynkowski_licznik_end' => $past, '_rynkowski_licznik_active' => 'yes' );
		$this->meta[4] = array( '_rynkowski_licznik_end' => $future, '_rynkowski_licznik_active' => 'no' );

		$this->assertSame( '&lt;b&gt;FLASH&lt;/b&gt;', $this->capture( fn() => Counter_Admin::render_column( 'rynkowski_text', 1 ) ) );
		$this->assertSame( '30.11.2026 23:59', $this->capture( fn() => Counter_Admin::render_column( 'rynkowski_end', 1 ) ) );
		$this->assertSame( '—', $this->capture( fn() => Counter_Admin::render_column( 'rynkowski_end', 5 ) ) );
		$this->assertSame( 'Trwa', $this->capture( fn() => Counter_Admin::render_column( 'rynkowski_status', 2 ) ) );
		$this->assertSame( 'Zakończony', $this->capture( fn() => Counter_Admin::render_column( 'rynkowski_status', 3 ) ) );
		$this->assertSame( 'Wyłączony', $this->capture( fn() => Counter_Admin::render_column( 'rynkowski_status', 4 ) ) );
		$this->assertSame( 'Brak daty końca', $this->capture( fn() => Counter_Admin::render_column( 'rynkowski_status', 5 ) ) );
		$this->assertSame( 'produkty: 2, kategorie: 0, tagi: 1', $this->capture( fn() => Counter_Admin::render_column( 'rynkowski_assigned', 1 ) ) );
		$this->assertSame( '', $this->capture( fn() => Counter_Admin::render_column( 'inna', 1 ) ) );
	}
}
