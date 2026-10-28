<?php
// phpcs:ignoreFile -- plik testowy.

namespace Rynkowski\Narzedzia\Moduly\Liczniki\Tests;

use Brain\Monkey\Actions;
use Brain\Monkey\Functions;
use Rynkowski\Narzedzia\Moduly\Liczniki\Counter_Frontend;
use Rynkowski\Narzedzia\Moduly\Liczniki\Counter_Settings;
use Rynkowski\Narzedzia\Plugin;
use Rynkowski\Narzedzia\Tests\TestCase;

final class CounterFrontendTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		// Liczniki żyją przez żądanie; każdy test to nowe żądanie.
		( new \ReflectionProperty( Counter_Frontend::class, 'counters' ) )->setValue( null, null );
		Functions\when( 'get_the_title' )->justReturn( 'L' );
		Functions\when( 'wc_get_product_term_ids' )->justReturn( array() );
		Functions\when( 'wp_date' )->justReturn( '30.11.2026 23:59' );
		// Bez ogólnej zaślepki wp_enqueue_script: przejęłaby wywołania przed oczekiwaniami w testach.
		Functions\when( 'get_option' )->alias(
			fn( string $name, $default = false ) => array( 'date_format' => 'j.m.Y', 'time_format' => 'H:i' )[ $name ] ?? ( $this->options[ $name ] ?? $default )
		);
	}

	protected function tearDown(): void {
		unset( $GLOBALS['product'] );
		parent::tearDown();
	}

	/**
	 * @param array<int, array<string, mixed>> $counters ID => pola bez prefiksu.
	 */
	private function counters( array $counters ): void {
		Functions\when( 'get_posts' )->justReturn( array_keys( $counters ) );
		foreach ( $counters as $id => $fields ) {
			$fields += array( 'active' => 'yes', 'products' => array( 5 ) );
			foreach ( $fields as $key => $value ) {
				$this->meta[ $id ][ '_rynkowski_licznik_' . $key ] = $value;
			}
		}
	}

	private function render(): string {
		return $this->capture( static fn() => Counter_Frontend::render() );
	}

	public function test_register_hooks_right_after_price_and_assets(): void {
		Actions\expectAdded( 'woocommerce_single_product_summary' )->once()->with( array( Counter_Frontend::class, 'render' ), 11 );
		Actions\expectAdded( 'wp_enqueue_scripts' )->once()->with( array( Counter_Frontend::class, 'enqueue' ) );

		Counter_Frontend::register();
	}

	public function test_render_called_like_wordpress_without_product(): void {
		// Akcja bez argumentów: WordPress podaje callbackowi ''.
		$this->assertSame( '', $this->capture( static fn() => call_user_func_array( array( Counter_Frontend::class, 'render' ), array( '' ) ) ) );
	}

	public function test_no_running_counter_renders_nothing(): void {
		$GLOBALS['product'] = new \WC_Product( 5 );
		Functions\expect( 'wp_enqueue_script' )->never();
		$this->counters(
			array(
				1 => array( 'end' => time() - 10 ),
				2 => array( 'end' => time() + 3600, 'active' => 'no' ),
				3 => array( 'end' => time() + 3600, 'products' => array( 6 ) ),
			)
		);

		$this->assertSame( '', $this->render() );
	}

	public function test_renders_earliest_counter_with_values_text_and_accessible_end(): void {
		$GLOBALS['product'] = new \WC_Product( 5 );
		$end = time() + 2 * DAY_IN_SECONDS + 3 * HOUR_IN_SECONDS + 4 * MINUTE_IN_SECONDS + 30;
		$this->counters(
			array(
				1 => array( 'end' => $end + 999, 'text' => 'Później' ),
				2 => array( 'end' => $end, 'text' => '-12% Z KODEM: FLASH <script>alert(1)</script>' ),
			)
		);
		Functions\expect( 'wp_enqueue_script' )->once()->with( 'rynkowski-liczniki' );

		$html = $this->render();

		$this->assertStringStartsWith( '<div class="rynkowski-licznik" data-koniec="' . $end . '">', $html );
		$this->assertStringContainsString( '<p class="rynkowski-licznik__tekst">-12% Z KODEM: FLASH &lt;script&gt;alert(1)&lt;/script&gt;</p>', $html );
		$this->assertStringNotContainsString( 'Później', $html );
		$this->assertStringContainsString( '<span class="screen-reader-text">Promocja kończy się 30.11.2026 23:59.</span>', $html );
		$this->assertStringContainsString( '<div class="rynkowski-licznik__pola" aria-hidden="true">', $html );
		$this->assertStringContainsString( 'data-jednostka="dni">02</span><span class="rynkowski-licznik__nazwa">Dni</span>', $html );
		$this->assertStringContainsString( 'data-jednostka="godz">03</span>', $html );
		$this->assertStringContainsString( 'data-jednostka="min">04</span>', $html );
		// Sekundy mogą zmaleć o 1, jeśli test trafi na granicę sekundy.
		$this->assertMatchesRegularExpression( '/data-jednostka="sek">(30|29)<\/span>/', $html );
		$this->assertStringNotContainsString( '<script>', $html );
	}

	public function test_counter_without_text_has_only_timer(): void {
		$GLOBALS['product'] = new \WC_Product( 5 );
		$this->counters( array( 1 => array( 'end' => time() + 100 ) ) );
		Functions\expect( 'wp_enqueue_script' )->once()->with( 'rynkowski-liczniki' );

		$html = $this->render();

		$this->assertStringNotContainsString( 'rynkowski-licznik__tekst', $html );
		$this->assertStringContainsString( 'data-jednostka="dni">00</span>', $html );
	}

	public function test_counters_are_read_once_per_request(): void {
		$GLOBALS['product'] = new \WC_Product( 5 );
		Functions\expect( 'get_posts' )->once()->andReturn( array() );

		$this->render();
		$this->render();
	}

	public function test_enqueue_only_on_product_page(): void {
		Functions\when( 'is_product' )->justReturn( false );
		Functions\expect( 'wp_register_style' )->never();

		Counter_Frontend::enqueue();
	}

	public function test_enqueue_styles_from_defaults_and_script(): void {
		Functions\when( 'is_product' )->justReturn( true );
		Functions\when( 'plugins_url' )->alias( static fn( string $path, string $file ): string => 'https://sklep.test/' . basename( dirname( $file ) ) . '/' . $path );
		Functions\expect( 'wp_register_style' )->once()->with( 'rynkowski-liczniki', false, array(), Plugin::VERSION );
		Functions\expect( 'wp_enqueue_style' )->once()->with( 'rynkowski-liczniki' );
		Functions\expect( 'wp_add_inline_style' )->once()->with(
			'rynkowski-liczniki',
			\Mockery::on(
				static fn( string $css ): bool => str_contains( $css, '.rynkowski-licznik__tekst{margin:0 0 8px;color:inherit;font-weight:600;font-size:16px;}' )
					&& str_contains( $css, 'grid-template-columns:repeat(4,minmax(0,1fr));gap:12px;' )
					&& str_contains( $css, 'padding:12px;border:1px solid var(--theme-border-color,#e0e0e0);background:transparent;' )
					&& str_contains( $css, 'font-size:28px;font-weight:600;line-height:1.1;color:var(--theme-palette-color-1,currentColor);' )
			)
		);
		Functions\expect( 'wp_register_script' )->once()->with( 'rynkowski-liczniki', 'https://sklep.test/liczniki/assets/liczniki.js', array(), Plugin::VERSION, true );

		Counter_Frontend::enqueue();
	}

	public function test_styles_follow_settings_and_ignore_css_injection(): void {
		Functions\when( 'is_product' )->justReturn( true );
		Functions\when( 'plugins_url' )->justReturn( 'x' );
		Functions\when( 'wp_register_style' )->justReturn( true );
		Functions\when( 'wp_enqueue_style' )->justReturn( null );
		Functions\when( 'wp_register_script' )->justReturn( true );
		$this->options[ Counter_Settings::OPTION ] = array(
			'kolor_tekstu'   => '#111111',
			'grubosc_tekstu' => '700',
			'rozmiar_tekstu' => '20',
			'kolor_cyfr'     => '#b42318',
			'grubosc_cyfr'   => '400',
			'rozmiar_cyfr'   => '40',
			'kolor_ramki'    => '#c9a96e',
			'kolor_tla'      => 'red;}body{display:none',
			'padding'        => '8;}body{',
			'odstep'         => '20',
		);
		Functions\expect( 'wp_add_inline_style' )->once()->with(
			'rynkowski-liczniki',
			\Mockery::on(
				static fn( string $css ): bool => str_contains( $css, 'color:#111111;font-weight:700;font-size:20px;' )
					&& str_contains( $css, 'gap:20px;' )
					&& str_contains( $css, 'padding:12px;border:1px solid #c9a96e;background:transparent;' )
					&& str_contains( $css, 'font-size:40px;font-weight:400;line-height:1.1;color:#b42318;' )
					&& ! str_contains( $css, 'body{' )
			)
		);

		Counter_Frontend::enqueue();
	}
}
