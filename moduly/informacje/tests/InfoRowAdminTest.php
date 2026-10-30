<?php
// phpcs:ignoreFile -- plik testowy.

namespace Rynkowski\Narzedzia\Moduly\Informacje\Tests;

require_once __DIR__ . '/InfoTestCase.php';

use Brain\Monkey\Actions;
use Brain\Monkey\Filters;
use Brain\Monkey\Functions;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use Rynkowski\Narzedzia\Moduly\Informacje\Info_Row_Admin;
use Rynkowski\Narzedzia\Moduly\Informacje\Info_Rows;
use Rynkowski\Narzedzia\Plugin;

final class InfoRowAdminTest extends InfoTestCase {

	/**
	 * Wywołania wp_editor i wp_add_inline_script.
	 *
	 * @var array<int, array<int, mixed>>
	 */
	private array $editors = array();

	/** @var array<int, array<int, mixed>> */
	private array $inline = array();

	protected function setUp(): void {
		parent::setUp();
		$this->editors = array();
		$this->inline  = array();
		Functions\when( 'wp_editor' )->alias( fn( ...$args ) => $this->editors[] = $args );
		Functions\when( 'wp_add_inline_script' )->alias( fn( ...$args ) => $this->inline[] = $args );
		Functions\when( 'wp_json_encode' )->alias( static fn( $value ) => json_encode( $value, JSON_UNESCAPED_UNICODE ) );
		Functions\when( 'wp_get_attachment_image' )->alias( static fn( int $id, $size ): string => '<img data-id="' . $id . '" data-size="' . implode( 'x', $size ) . '">' );
	}

	private function valid_request(): void {
		$_POST[ Info_Row_Admin::NONCE ] = 'nonce';
		Functions\when( 'wp_verify_nonce' )->alias( static fn( $nonce, $action ): bool => 'nonce' === $nonce && Info_Row_Admin::NONCE === $action );
		Functions\when( 'current_user_can' )->alias( static fn( string $cap, int $id ): bool => 'edit_post' === $cap && 7 === $id );
		Functions\when( 'wp_attachment_is_image' )->alias( static fn( int $id ): bool => 44 === $id );
	}

	private function post( string $type = Info_Rows::POST_TYPE ): \WP_Post {
		return new \WP_Post( 7, $type );
	}

	public function test_register_hooks(): void {
		Filters\expectAdded( 'get_user_option_screen_layout_' . Info_Rows::POST_TYPE )->once();
		Actions\expectAdded( 'add_meta_boxes_' . Info_Rows::POST_TYPE )->once()->with( array( Info_Row_Admin::class, 'add_meta_boxes' ) );
		Actions\expectAdded( 'save_post_' . Info_Rows::POST_TYPE )->once()->with( array( Info_Row_Admin::class, 'save' ), 10, 2 );
		Filters\expectAdded( 'manage_' . Info_Rows::POST_TYPE . '_posts_columns' )->once()->with( array( Info_Row_Admin::class, 'columns' ) );
		Actions\expectAdded( 'manage_' . Info_Rows::POST_TYPE . '_posts_custom_column' )->once()->with( array( Info_Row_Admin::class, 'render_column' ), 10, 2 );
		Actions\expectAdded( 'admin_enqueue_scripts' )->atLeast()->once();

		Info_Row_Admin::register();
	}

	public function test_adds_box(): void {
		Functions\expect( 'add_meta_box' )->once()->with( 'rynkowski-informacja', 'Wiersz', array( Info_Row_Admin::class, 'render' ), Info_Rows::POST_TYPE, 'normal', 'high' );

		Info_Row_Admin::add_meta_boxes();
	}

	public function test_render_shows_icon_text_editor_and_active(): void {
		Functions\expect( 'wp_nonce_field' )->once()->with( Info_Row_Admin::NONCE, Info_Row_Admin::NONCE );
		$this->row( 7, 'Dostawa <strong>gratis</strong>', 0, 44, 'no' );

		$html = $this->capture( fn() => Info_Row_Admin::render( $this->post() ) );

		$this->assertStringContainsString( 'name="rynkowski_ikona" value="44"', $html );
		$this->assertStringContainsString( '<img data-id="44" data-size="48x48">', $html );
		$this->assertStringContainsString( '<label for="rynkowski_informacja_tekst">Tekst domyślny</label><span class="woocommerce-help-tip"', $html );
		$this->assertDoesNotMatchRegularExpression( "/name=\"rynkowski_aktywny\" value=\"yes\"\s+checked/", $html );
		$this->assertSame( 'Dostawa <strong>gratis</strong>', $this->editors[0][0] );
		$this->assertSame( 'rynkowski_informacja_tekst', $this->editors[0][1] );
		$this->assertSame( 'rynkowski_tekst', $this->editors[0][2]['textarea_name'] );
	}

	public function test_render_new_row_without_icon_is_active(): void {
		Functions\when( 'wp_nonce_field' )->justReturn( '' );

		$html = $this->capture( fn() => Info_Row_Admin::render( $this->post() ) );

		$this->assertStringContainsString( 'name="rynkowski_ikona" value=""', $html );
		$this->assertStringNotContainsString( '<img', $html );
		$this->assertMatchesRegularExpression( "/name=\"rynkowski_aktywny\" value=\"yes\"\s+checked='checked'>/", $html );
	}

	public function test_editor_is_text_only_with_own_buttons_for_this_instance(): void {
		Info_Row_Admin::editor( 'Tekst', 'rynkowski_informacje_5', 'rynkowski_informacje[5]' );

		$this->assertSame(
			array(
				array(
					'Tekst',
					'rynkowski_informacje_5',
					array(
						'textarea_name' => 'rynkowski_informacje[5]',
						'textarea_rows' => 3,
						'media_buttons' => false,
						'tinymce'       => false,
						'quicktags'     => array( 'buttons' => 'strong,link' ),
					),
				),
			),
			$this->editors
		);
		$this->assertSame(
			array(
				array(
					'quicktags',
					'QTags.addButton("rynkowski_wyroznij_rynkowski_informacje_5","wyróżnij","<mark>","</mark>","","Wyróżnij kolorem z ustawień",200,"rynkowski_informacje_5");'
					. 'QTags.addButton("rynkowski_dopisek_rynkowski_informacje_5","dopisek","<small>","</small>","","Mniejszy dopisek w drugiej linii",201,"rynkowski_informacje_5");',
				),
			),
			$this->inline
		);
	}

	public function test_save_stores_clean_values(): void {
		$this->valid_request();
		$_POST['rynkowski_ikona']   = '44';
		$_POST['rynkowski_tekst']   = ' <strong>30 dni</strong> na zwrot<script>alert(1)</script> ';
		$_POST['rynkowski_aktywny'] = 'yes';

		Info_Row_Admin::save( 7, $this->post() );

		$this->assertSame( 44, $this->meta[7][ Info_Rows::META_ICON ] );
		// Skrypt znika, zostaje jego treść jako zwykły tekst (jak w wp_kses).
		$this->assertSame( '<strong>30 dni</strong> na zwrotalert(1)', $this->meta[7][ Info_Rows::META_TEXT ] );
		$this->assertSame( 'yes', $this->meta[7][ Info_Rows::META_ACTIVE ] );
	}

	public function test_save_rejects_non_image_icon_and_handles_missing_fields(): void {
		$this->valid_request();
		$_POST['rynkowski_ikona'] = '99';
		$_POST['rynkowski_tekst'] = array( 'x' );

		Info_Row_Admin::save( 7, $this->post() );

		$this->assertSame( 0, $this->meta[7][ Info_Rows::META_ICON ] );
		$this->assertSame( '', $this->meta[7][ Info_Rows::META_TEXT ] );
		$this->assertSame( 'no', $this->meta[7][ Info_Rows::META_ACTIVE ] );

		unset( $_POST['rynkowski_ikona'] );
		Info_Row_Admin::save( 7, $this->post() );
		$this->assertSame( 0, $this->meta[7][ Info_Rows::META_ICON ] );
	}

	public function test_save_rejects_bad_nonce_rights_and_type(): void {
		Info_Row_Admin::save( 7, $this->post() );

		$_POST[ Info_Row_Admin::NONCE ] = 'zly';
		Functions\when( 'wp_verify_nonce' )->justReturn( false );
		Info_Row_Admin::save( 7, $this->post() );

		$this->valid_request();
		Info_Row_Admin::save( 7, $this->post( 'post' ) );
		Functions\when( 'current_user_can' )->justReturn( false );
		Info_Row_Admin::save( 7, $this->post() );

		$this->assertSame( array(), $this->meta );
	}

	#[RunInSeparateProcess]
	#[PreserveGlobalState( false )]
	public function test_save_skips_autosave(): void {
		define( 'DOING_AUTOSAVE', true );
		$this->valid_request();

		Info_Row_Admin::save( 7, $this->post() );

		$this->assertSame( array(), $this->meta );
	}

	public function test_columns(): void {
		$this->assertSame(
			array( 'cb', 'rynkowski_ikona', 'title', 'rynkowski_tekst', 'rynkowski_kolejnosc', 'rynkowski_stan' ),
			array_keys( Info_Row_Admin::columns( array( 'cb' => '<input>', 'date' => 'Data' ) ) )
		);
		$this->assertSame( '', Info_Row_Admin::columns( array() )['cb'] );
	}

	public function test_render_columns_escape_and_format(): void {
		$this->row( 7, 'Ponad <mark>100</mark> klientek<script>alert(1)</script>', 3, 44 );
		$this->row( 8, '', 0, 0, 'no' );

		$col = fn( string $c, int $id ): string => $this->capture( fn() => Info_Row_Admin::render_column( $c, $id ) );

		$this->assertSame( '<img data-id="44" data-size="24x24">', $col( 'rynkowski_ikona', 7 ) );
		$this->assertSame( '—', $col( 'rynkowski_ikona', 8 ) );
		$this->assertSame( 'Ponad <mark>100</mark> klientekalert(1)', $col( 'rynkowski_tekst', 7 ) );
		$this->assertSame( '3', $col( 'rynkowski_kolejnosc', 7 ) );
		$this->assertSame( 'Aktywny', $col( 'rynkowski_stan', 7 ) );
		$this->assertSame( 'Wyłączony', $col( 'rynkowski_stan', 8 ) );
		$this->assertSame( '', $col( 'inna', 7 ) );
	}

	public function test_enqueue_media_only_on_row_edit_screen(): void {
		Functions\when( 'get_current_screen' )->justReturn( new \WP_Screen( Info_Rows::POST_TYPE, 'post', Info_Rows::POST_TYPE ) );
		Functions\when( 'plugins_url' )->alias( static fn( string $path ): string => 'https://sklep/wp-content/plugins/x/moduly/informacje/' . $path );
		Functions\expect( 'wp_enqueue_media' )->once();
		Functions\expect( 'wp_enqueue_script' )->once()->with( 'rynkowski-informacje-admin', 'https://sklep/wp-content/plugins/x/moduly/informacje/assets/informacje-admin.js', array( 'jquery' ), Plugin::VERSION, true );
		Functions\expect( 'wp_localize_script' )->once()->with( 'rynkowski-informacje-admin', 'rynkowskiInformacje', array( 'wybierz' => 'Wybierz ikonę', 'uzyj' => 'Użyj tej ikony' ) );

		Info_Row_Admin::enqueue();
	}

	public function test_enqueue_skips_other_screens(): void {
		Functions\expect( 'wp_enqueue_media' )->never();

		Functions\when( 'get_current_screen' )->justReturn( null );
		Info_Row_Admin::enqueue();
		Functions\when( 'get_current_screen' )->justReturn( new \WP_Screen( 'edit-' . Info_Rows::POST_TYPE, 'edit', Info_Rows::POST_TYPE ) );
		Info_Row_Admin::enqueue();
		Functions\when( 'get_current_screen' )->justReturn( new \WP_Screen( 'product', 'post', 'product' ) );
		Info_Row_Admin::enqueue();

		// Wywołanie jak z WordPressa: akcja podaje uchwyt ekranu.
		call_user_func_array( array( Info_Row_Admin::class, 'enqueue' ), array( 'post.php' ) );
		$this->addToAssertionCount( 1 );
	}
}
