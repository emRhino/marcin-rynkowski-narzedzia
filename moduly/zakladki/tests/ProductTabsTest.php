<?php
// phpcs:ignoreFile -- plik testowy.

namespace Rynkowski\Narzedzia\Moduly\Zakladki\Tests;

use Brain\Monkey\Functions;
use Rynkowski\Narzedzia\Moduly\Zakladki\Product_Tabs;
use Rynkowski\Narzedzia\Moduly\Zakladki\Tab_Settings;
use Rynkowski\Narzedzia\Tests\TestCase;

final class ProductTabsTest extends TestCase {

	private const DESCRIPTION = array(
		'title'    => 'Opis',
		'priority' => 10,
		'callback' => 'woocommerce_product_description_tab',
	);

	protected function setUp(): void {
		parent::setUp();
		Functions\when( 'get_the_ID' )->justReturn( 7 );
		// Jak wc_format_content(): akapity wokół treści.
		Functions\when( 'wc_format_content' )->alias( static fn( string $raw ): string => '<p>' . $raw . '</p>' );
		Functions\when( 'wp_kses_post' )->alias( static fn( string $html ): string => preg_replace( '#<script.*?</script>#s', '', $html ) );
	}

	public function test_labels_in_fixed_order(): void {
		$this->assertSame(
			array(
				'pielegnacja'    => 'Pielęgnacja',
				'cechy'          => 'Cechy produktu',
				'bezpieczenstwo' => 'Bezpieczeństwo produktu',
			),
			Product_Tabs::labels()
		);
		$this->assertSame( '_rynkowski_zakladki_cechy', Product_Tabs::meta_key( 'cechy' ) );
	}

	public function test_adds_tabs_with_content_after_description(): void {
		$this->meta[7] = array(
			'_rynkowski_zakladki_pielegnacja'    => 'Myć ręcznie.',
			'_rynkowski_zakladki_cechy'          => 'Kamionka.',
			'_rynkowski_zakladki_bezpieczenstwo' => 'Bezołowiowe.',
		);

		$tabs = Product_Tabs::add_tabs( array( 'description' => self::DESCRIPTION ) );

		$this->assertSame( array( 'description', 'rynkowski_pielegnacja', 'rynkowski_cechy', 'rynkowski_bezpieczenstwo' ), array_keys( $tabs ) );
		$this->assertSame(
			array(
				'title'    => 'Pielęgnacja',
				'priority' => 11,
				'callback' => array( Product_Tabs::class, 'render' ),
			),
			$tabs['rynkowski_pielegnacja']
		);
		// Stała kolejność: przed Informacjami dodatkowymi (20) i Opiniami (30).
		$this->assertSame( 12, $tabs['rynkowski_cechy']['priority'] );
		$this->assertSame( 13, $tabs['rynkowski_bezpieczenstwo']['priority'] );
		$this->assertSame( 'Bezpieczeństwo produktu', $tabs['rynkowski_bezpieczenstwo']['title'] );
	}

	public function test_skips_empty_tabs_and_keeps_order(): void {
		$this->meta[7] = array(
			'_rynkowski_zakladki_pielegnacja'    => " \n ",
			'_rynkowski_zakladki_bezpieczenstwo' => 'Bezołowiowe.',
		);

		$tabs = Product_Tabs::add_tabs( array() );

		$this->assertSame( array( 'rynkowski_bezpieczenstwo' ), array_keys( $tabs ) );
		$this->assertSame( 13, $tabs['rynkowski_bezpieczenstwo']['priority'] );
	}

	public function test_skips_disabled_tabs_with_content(): void {
		$this->meta[7] = array(
			'_rynkowski_zakladki_pielegnacja'    => 'Myć ręcznie.',
			'_rynkowski_zakladki_cechy'          => 'Kamionka.',
			'_rynkowski_zakladki_bezpieczenstwo' => 'Bezołowiowe.',
		);
		$this->options[ Tab_Settings::OPTION ] = array( 'pielegnacja' => 'no', 'cechy' => 'no' );

		$tabs = Product_Tabs::add_tabs( array() );

		$this->assertSame( array( 'rynkowski_bezpieczenstwo' ), array_keys( $tabs ) );
		// Priorytet bez zmian, niezależnie od wyłączonych.
		$this->assertSame( 13, $tabs['rynkowski_bezpieczenstwo']['priority'] );
	}

	public function test_render_ignores_disabled_tab(): void {
		$this->meta[7] = array( '_rynkowski_zakladki_cechy' => 'Kamionka' );
		$this->options[ Tab_Settings::OPTION ] = array( 'cechy' => 'no' );

		$this->assertSame( '', $this->capture( static fn() => Product_Tabs::render( 'rynkowski_cechy' ) ) );
	}

	public function test_no_product_changes_nothing(): void {
		Functions\when( 'get_the_ID' )->justReturn( false );

		$this->assertSame( array( 'description' => self::DESCRIPTION ), Product_Tabs::add_tabs( array( 'description' => self::DESCRIPTION ) ) );
	}

	public function test_non_string_meta_counts_as_empty(): void {
		$this->meta[7] = array( '_rynkowski_zakladki_cechy' => array( 'x' ) );

		$this->assertSame( '', Product_Tabs::content( 7, 'cechy' ) );
		$this->assertSame( array(), Product_Tabs::add_tabs( array() ) );
	}

	public function test_render_formats_content_and_strips_scripts(): void {
		$this->meta[7] = array( '_rynkowski_zakladki_cechy' => 'Kamionka<script>alert(1)</script>' );

		$html = $this->capture( static fn() => Product_Tabs::render( 'rynkowski_cechy' ) );

		$this->assertSame( '<p>Kamionka</p>', $html );
	}

	public function test_render_ignores_unknown_keys(): void {
		$this->meta[7] = array( '_rynkowski_zakladki_cechy' => 'Kamionka' );
		Functions\expect( 'wc_format_content' )->never();

		foreach ( array( '', 'description', 'cechy', 'rynkowski_', 'rynkowski_inne', 'xrynkowski_cechy' ) as $key ) {
			$this->assertSame( '', $this->capture( static fn() => Product_Tabs::render( $key ) ) );
		}
	}
}
