<?php
// phpcs:ignoreFile -- plik testowy.

namespace Rynkowski\Narzedzia\Moduly\Zakladki\Tests;

use Brain\Monkey\Functions;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use Rynkowski\Narzedzia\Moduly\Zakladki\Tab_Editor;
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

	public function test_adds_box_under_description(): void {
		Functions\expect( 'add_meta_box' )->once()->with( 'rynkowski-zakladki', 'Zakładki produktu', array( Tab_Editor::class, 'render' ), 'product', 'normal', 'high' );

		Tab_Editor::add_meta_box();
	}

	public function test_render_shows_editor_per_tab_with_saved_content(): void {
		Functions\expect( 'wp_nonce_field' )->once()->with( Tab_Editor::NONCE, Tab_Editor::NONCE );
		$this->meta[7] = array( '_rynkowski_zakladki_cechy' => '<p>Kamionka</p>' );
		$editors       = array();
		Functions\when( 'wp_editor' )->alias(
			static function ( string $content, string $id, array $settings ) use ( &$editors ): void {
				$editors[] = array( $content, $id, $settings );
			}
		);

		$html = $this->capture( fn() => Tab_Editor::render( $this->post() ) );

		$this->assertSame(
			array(
				array( '', 'rynkowski_zakladki_pielegnacja', array( 'textarea_name' => 'rynkowski_zakladki[pielegnacja]', 'textarea_rows' => 8 ) ),
				array( '<p>Kamionka</p>', 'rynkowski_zakladki_cechy', array( 'textarea_name' => 'rynkowski_zakladki[cechy]', 'textarea_rows' => 8 ) ),
				array( '', 'rynkowski_zakladki_bezpieczenstwo', array( 'textarea_name' => 'rynkowski_zakladki[bezpieczenstwo]', 'textarea_rows' => 8 ) ),
			),
			$editors
		);
		$this->assertStringContainsString( '<label for="rynkowski_zakladki_bezpieczenstwo"><strong>Bezpieczeństwo produktu</strong></label>', $html );
		$this->assertStringContainsString( 'Pusta zakładka nie pokazuje się w sklepie.', $html );
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
