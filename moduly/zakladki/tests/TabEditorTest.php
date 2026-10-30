<?php
// phpcs:ignoreFile -- plik testowy.

namespace Rynkowski\Narzedzia\Moduly\Zakladki\Tests;

use Brain\Monkey\Functions;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use Rynkowski\Narzedzia\Moduly\Zakladki\Tab_Editor;
use Rynkowski\Narzedzia\Moduly\Zakladki\Tab_Settings;
use Rynkowski\Narzedzia\Tests\TestCase;

final class TabEditorTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		Functions\when( 'wp_kses_post' )->alias( static fn( string $html ): string => preg_replace( '#<script.*?</script>#s', '', $html ) );
		Functions\when( 'delete_post_meta' )->alias(
			function ( int $id, string $key ): bool {
				unset( $this->meta[ $id ][ $key ] );
				return true;
			}
		);
	}

	private function post( string $type = 'product' ): \WP_Post {
		return new \WP_Post( 7, $type );
	}

	private function valid_request(): void {
		$_POST[ Tab_Editor::NONCE ] = 'nonce';
		Functions\when( 'wp_verify_nonce' )->alias( static fn( $nonce, $action ): bool => 'nonce' === $nonce && Tab_Editor::NONCE === $action );
		Functions\when( 'current_user_can' )->alias( static fn( string $cap, int $id ): bool => 'edit_post' === $cap && 7 === $id );
	}

	public function test_adds_separate_box_per_tab_after_short_description(): void {
		$tip = '<span class="woocommerce-help-tip" tabindex="0" aria-label="Treść zakładki na karcie produktu, zaraz po Opisie. Pusta zakładka nie pokazuje się w sklepie." data-tip="Treść zakładki na karcie produktu, zaraz po Opisie. Pusta zakładka nie pokazuje się w sklepie."></span>';
		$boxes = array();
		Functions\when( 'add_meta_box' )->alias(
			static function () use ( &$boxes ): void {
				$boxes[] = func_get_args();
			}
		);

		Tab_Editor::add_meta_boxes();

		$render = array( Tab_Editor::class, 'render' );
		$this->assertSame(
			array(
				// Kontekst i priorytet jak krótki opis WooCommerce (postexcerpt), więc pola są pod nim.
				array( 'rynkowski-zakladki-pielegnacja', 'Pielęgnacja ' . $tip, $render, 'product', 'normal', 'default', array( 'key' => 'pielegnacja' ) ),
				array( 'rynkowski-zakladki-cechy', 'Cechy produktu ' . $tip, $render, 'product', 'normal', 'default', array( 'key' => 'cechy' ) ),
				array( 'rynkowski-zakladki-bezpieczenstwo', 'Bezpieczeństwo produktu ' . $tip, $render, 'product', 'normal', 'default', array( 'key' => 'bezpieczenstwo' ) ),
			),
			$boxes
		);
	}

	public function test_boxes_only_for_enabled_tabs(): void {
		$this->options[ Tab_Settings::OPTION ] = array( 'pielegnacja' => 'no', 'cechy' => 'no' );
		Functions\when( 'wc_help_tip' )->justReturn( '?' );
		Functions\expect( 'add_meta_box' )->once()->with( 'rynkowski-zakladki-bezpieczenstwo', 'Bezpieczeństwo produktu ?', array( Tab_Editor::class, 'render' ), 'product', 'normal', 'default', array( 'key' => 'bezpieczenstwo' ) );

		Tab_Editor::add_meta_boxes();
	}

	public function test_nonce_moves_to_first_enabled_tab(): void {
		$this->options[ Tab_Settings::OPTION ] = array( 'pielegnacja' => 'no' );
		Functions\when( 'wp_editor' )->justReturn( null );
		Functions\expect( 'wp_nonce_field' )->once()->with( Tab_Editor::NONCE, Tab_Editor::NONCE );

		$this->capture( fn() => Tab_Editor::render( $this->post(), array( 'args' => array( 'key' => 'cechy' ) ) ) );
		$this->capture( fn() => Tab_Editor::render( $this->post(), array( 'args' => array( 'key' => 'bezpieczenstwo' ) ) ) );
		// Wyłączona zakładka nie rysuje nic.
		$this->assertSame( '', $this->capture( fn() => Tab_Editor::render( $this->post(), array( 'args' => array( 'key' => 'pielegnacja' ) ) ) ) );
	}

	public function test_save_keeps_content_of_disabled_tabs(): void {
		$this->valid_request();
		$this->options[ Tab_Settings::OPTION ] = array( 'pielegnacja' => 'no' );
		$this->meta[7] = array( '_rynkowski_zakladki_pielegnacja' => 'Zostaje', '_rynkowski_zakladki_cechy' => 'Stare' );
		// Pola wyłączonej zakładki nie ma w formularzu; podrobione też ignorujemy.
		$_POST[ Tab_Editor::FIELD ] = array( 'pielegnacja' => 'Podrobione', 'cechy' => 'Nowe' );

		Tab_Editor::save( 7, $this->post() );

		$this->assertSame( array( '_rynkowski_zakladki_pielegnacja' => 'Zostaje', '_rynkowski_zakladki_cechy' => 'Nowe' ), $this->meta[7] );
	}

	public function test_first_box_renders_nonce_and_editor(): void {
		Functions\expect( 'wp_nonce_field' )->once()->with( Tab_Editor::NONCE, Tab_Editor::NONCE );
		$editors = array();
		Functions\when( 'wp_editor' )->alias(
			static function ( string $content, string $id, array $settings ) use ( &$editors ): void {
				$editors[] = array( $content, $id, $settings );
			}
		);
		$this->meta[7] = array( '_rynkowski_zakladki_pielegnacja' => '<p>Myć ręcznie</p>' );

		$html = $this->capture( fn() => Tab_Editor::render( $this->post(), array( 'args' => array( 'key' => 'pielegnacja' ) ) ) );

		$this->assertSame( array( array( '<p>Myć ręcznie</p>', 'rynkowski_zakladki_pielegnacja', array( 'textarea_name' => 'rynkowski_zakladki[pielegnacja]', 'textarea_rows' => 8 ) ) ), $editors );
		$this->assertSame( '<label class="screen-reader-text" for="rynkowski_zakladki_pielegnacja">Pielęgnacja</label>', $html );
	}

	public function test_other_boxes_render_editor_without_nonce(): void {
		Functions\expect( 'wp_nonce_field' )->never();
		$editors = array();
		Functions\when( 'wp_editor' )->alias(
			static function ( string $content, string $id, array $settings ) use ( &$editors ): void {
				$editors[] = array( $content, $id, $settings );
			}
		);

		$html = $this->capture( fn() => Tab_Editor::render( $this->post(), array( 'args' => array( 'key' => 'bezpieczenstwo' ) ) ) );

		$this->assertSame( array( array( '', 'rynkowski_zakladki_bezpieczenstwo', array( 'textarea_name' => 'rynkowski_zakladki[bezpieczenstwo]', 'textarea_rows' => 8 ) ) ), $editors );
		$this->assertStringContainsString( 'Bezpieczeństwo produktu', $html );
	}

	public function test_render_ignores_unknown_box(): void {
		Functions\expect( 'wp_nonce_field' )->never();
		Functions\expect( 'wp_editor' )->never();

		foreach ( array( array(), array( 'args' => null ), array( 'args' => array( 'key' => 1 ) ), array( 'args' => array( 'key' => 'inna' ) ) ) as $box ) {
			$this->assertSame( '', $this->capture( fn() => Tab_Editor::render( $this->post(), $box ) ) );
		}
	}

	public function test_save_stores_sanitized_content_and_deletes_empty(): void {
		$this->valid_request();
		$this->meta[7] = array( '_rynkowski_zakladki_bezpieczenstwo' => 'Stara treść' );
		$_POST[ Tab_Editor::FIELD ] = array(
			'pielegnacja'    => ' <p>Myć ręcznie</p><script>alert(1)</script> ',
			'cechy'          => array( 'nie tekst' ),
			'bezpieczenstwo' => '  ',
			'obca'           => 'x',
		);

		Tab_Editor::save( 7, $this->post() );

		$this->assertSame( array( '_rynkowski_zakladki_pielegnacja' => '<p>Myć ręcznie</p>' ), $this->meta[7] );
	}

	public function test_save_without_fields_clears_all(): void {
		$this->valid_request();
		$this->meta[7] = array( '_rynkowski_zakladki_cechy' => 'x' );
		$_POST[ Tab_Editor::FIELD ] = 'nie tablica';

		Tab_Editor::save( 7, $this->post() );

		$this->assertSame( array(), $this->meta[7] );
	}

	public function test_save_rejects_missing_or_bad_nonce(): void {
		$_POST[ Tab_Editor::FIELD ] = array( 'cechy' => 'x' );
		Tab_Editor::save( 7, $this->post() );

		$_POST[ Tab_Editor::NONCE ] = 'zly';
		Functions\when( 'wp_verify_nonce' )->justReturn( false );
		Tab_Editor::save( 7, $this->post() );

		$this->assertSame( array(), $this->meta );
	}

	public function test_save_rejects_user_without_rights_and_other_post_types(): void {
		$this->valid_request();
		$_POST[ Tab_Editor::FIELD ] = array( 'cechy' => 'x' );

		Tab_Editor::save( 7, $this->post( 'post' ) );
		Functions\when( 'current_user_can' )->justReturn( false );
		Tab_Editor::save( 7, $this->post() );

		$this->assertSame( array(), $this->meta );
	}

	#[RunInSeparateProcess]
	#[PreserveGlobalState( false )]
	public function test_save_skips_autosave(): void {
		define( 'DOING_AUTOSAVE', true );
		$this->valid_request();
		$_POST[ Tab_Editor::FIELD ] = array( 'cechy' => 'x' );

		Tab_Editor::save( 7, $this->post() );

		$this->assertSame( array(), $this->meta );
	}
}
