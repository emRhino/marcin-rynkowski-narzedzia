<?php
// phpcs:ignoreFile -- plik testowy.

namespace Rynkowski\Narzedzia\Moduly\Informacje\Tests;

require_once __DIR__ . '/InfoTestCase.php';

use Brain\Monkey\Functions;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use Rynkowski\Narzedzia\Moduly\Informacje\Info_Product_Box;
use Rynkowski\Narzedzia\Moduly\Informacje\Info_Rows;

final class InfoProductBoxTest extends InfoTestCase {

	/** @var array<int, array<int, mixed>> */
	private array $editors = array();

	protected function setUp(): void {
		parent::setUp();
		$this->editors = array();
		Functions\when( 'wp_editor' )->alias( fn( ...$args ) => $this->editors[] = $args );
		Functions\when( 'wp_add_inline_script' )->justReturn( true );
		Functions\when( 'wp_json_encode' )->alias( static fn( $value ) => json_encode( $value ) );
	}

	private function valid_request(): void {
		$_POST[ Info_Product_Box::NONCE ] = 'nonce';
		Functions\when( 'wp_verify_nonce' )->alias( static fn( $nonce, $action ): bool => 'nonce' === $nonce && Info_Product_Box::NONCE === $action );
		Functions\when( 'current_user_can' )->alias( static fn( string $cap, int $id ): bool => 'edit_post' === $cap && 7 === $id );
	}

	private function post( string $type = 'product' ): \WP_Post {
		return new \WP_Post( 7, $type );
	}

	public function test_adds_box(): void {
		Functions\expect( 'add_meta_box' )->once()->with( 'rynkowski-informacje', 'Informacje o zakupie', array( Info_Product_Box::class, 'render' ), 'product', 'normal', 'default' );

		Info_Product_Box::add_meta_box();
	}

	public function test_render_without_rows_points_to_list(): void {
		Functions\expect( 'wp_nonce_field' )->never();

		$html = $this->capture( fn() => Info_Product_Box::render( $this->post() ) );

		$this->assertStringContainsString( 'Dodaj je w Narzędzia → Informacje o zakupie.', $html );
		$this->assertSame( array(), $this->editors );
	}

	public function test_render_field_per_active_row_with_default_below(): void {
		Functions\expect( 'wp_nonce_field' )->once()->with( Info_Product_Box::NONCE, Info_Product_Box::NONCE );
		$this->row( 11, 'Wysyłka <strong>24h</strong><script>alert(1)</script>' );
		$this->row( 12, '', 1 );
		$this->row( 13, 'Wyłączony', 2, 0, 'no' );
		$this->meta[7] = array( Info_Rows::META_PRODUCT_TEXTS => array( 12 => 'Własny' ) );

		$html = $this->capture( fn() => Info_Product_Box::render( $this->post() ) );

		$this->assertSame( array( '', 'rynkowski_informacje_11' ), array_slice( $this->editors[0], 0, 2 ) );
		$this->assertSame( 'rynkowski_informacje[11]', $this->editors[0][2]['textarea_name'] );
		$this->assertSame( array( 'Własny', 'rynkowski_informacje_12' ), array_slice( $this->editors[1], 0, 2 ) );
		$this->assertCount( 2, $this->editors );
		$this->assertStringContainsString( '<label for="rynkowski_informacje_11">Wiersz 11</label>', $html );
		$this->assertStringContainsString( 'Wysyłka <strong>24h</strong>alert(1)', $html );
		$this->assertStringNotContainsString( '<script>', $html );
		$this->assertStringContainsString( '(pusty, wiersz się nie pokaże)', $html );
		$this->assertStringNotContainsString( 'Wiersz 13', $html );
	}

	public function test_save_sets_and_clears_texts_of_active_rows_only(): void {
		$this->valid_request();
		$this->row( 11, 'A' );
		$this->row( 12, 'B', 1 );
		$this->row( 13, 'C', 2, 0, 'no' );
		$this->meta[7] = array( Info_Rows::META_PRODUCT_TEXTS => array( 12 => 'Stary', 13 => 'Zostaje' ) );
		$_POST[ Info_Product_Box::FIELD ] = array(
			11 => ' <mark>Nowy</mark><img src=x onerror=alert(1)> ',
			12 => '',
			13 => 'Podrobiony',
			99 => 'Obcy wiersz',
		);

		Info_Product_Box::save( 7, $this->post() );

		$this->assertSame( array( 13 => 'Zostaje', 11 => '<mark>Nowy</mark>' ), $this->meta[7][ Info_Rows::META_PRODUCT_TEXTS ] );
	}

	public function test_save_deletes_meta_when_nothing_left(): void {
		$this->valid_request();
		$this->row( 11, 'A' );
		$this->meta[7] = array( Info_Rows::META_PRODUCT_TEXTS => array( 11 => 'Stary' ) );
		$_POST[ Info_Product_Box::FIELD ] = array( 11 => array( 'nie tekst' ) );

		Info_Product_Box::save( 7, $this->post() );
		$this->assertArrayNotHasKey( Info_Rows::META_PRODUCT_TEXTS, $this->meta[7] );

		$_POST[ Info_Product_Box::FIELD ] = 'nie tablica';
		Info_Product_Box::save( 7, $this->post() );
		$this->assertArrayNotHasKey( Info_Rows::META_PRODUCT_TEXTS, $this->meta[7] );
	}

	public function test_save_rejects_bad_nonce_rights_and_type(): void {
		$this->row( 11, 'A' );
		$_POST[ Info_Product_Box::FIELD ] = array( 11 => 'x' );

		Info_Product_Box::save( 7, $this->post() );
		$_POST[ Info_Product_Box::NONCE ] = 'zly';
		Functions\when( 'wp_verify_nonce' )->justReturn( false );
		Info_Product_Box::save( 7, $this->post() );

		$this->valid_request();
		Info_Product_Box::save( 7, $this->post( 'post' ) );
		Functions\when( 'current_user_can' )->justReturn( false );
		Info_Product_Box::save( 7, $this->post() );

		$this->assertArrayNotHasKey( 7, $this->meta );
	}

	#[RunInSeparateProcess]
	#[PreserveGlobalState( false )]
	public function test_save_skips_autosave(): void {
		define( 'DOING_AUTOSAVE', true );
		$this->valid_request();
		$this->row( 11, 'A' );
		$_POST[ Info_Product_Box::FIELD ] = array( 11 => 'x' );

		Info_Product_Box::save( 7, $this->post() );

		$this->assertArrayNotHasKey( 7, $this->meta );
	}
}
