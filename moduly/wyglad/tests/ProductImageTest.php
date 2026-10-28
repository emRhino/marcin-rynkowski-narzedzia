<?php
// phpcs:ignoreFile -- plik testowy.

namespace Rynkowski\Narzedzia\Moduly\Wyglad\Tests;

use Brain\Monkey\Actions;
use Brain\Monkey\Functions;
use Rynkowski\Narzedzia\Moduly\Wyglad\Look_Settings;
use Rynkowski\Narzedzia\Moduly\Wyglad\Product_Image;
use Rynkowski\Narzedzia\Plugin;
use Rynkowski\Narzedzia\Tests\TestCase;

final class ProductImageTest extends TestCase {

	public function test_register_hooks_front_styles(): void {
		Actions\expectAdded( 'wp_enqueue_scripts' )->once()->with( array( Product_Image::class, 'enqueue' ) );

		Product_Image::register();
	}

	public function test_full_width_main_image_on_mobile_product_page(): void {
		Functions\when( 'is_product' )->justReturn( true );
		Functions\expect( 'wp_register_style' )->once()->with( 'rynkowski-zdjecie-produktu', false, array(), Plugin::VERSION );
		Functions\expect( 'wp_enqueue_style' )->once()->with( 'rynkowski-zdjecie-produktu' );
		Functions\expect( 'wp_add_inline_style' )->once()->with(
			'rynkowski-zdjecie-produktu',
			\Mockery::on(
				static function ( string $css ): bool {
					$g = '.single-product .woocommerce-product-gallery';
					return str_starts_with( $css, '@media (max-width:689.98px){' )
						&& str_ends_with( $css, '}}' )
						// Bez tego Blocksy obcina zdjęcie do starej szerokości.
						&& str_contains( $css, $g . '{overflow:visible;}' )
						// Pojedyncze zdjęcie i widok karuzeli, ale nie miniatury.
						&& str_contains( $css, $g . ' .ct-product-gallery-container>.ct-media-container,' . $g . ' .ct-product-gallery-container>.flexy-container>.flexy{width:100vw;max-width:100vw;margin-inline:calc(50% - 50vw);}' )
						&& ! str_contains( $css, 'flexy-pills' )
						&& str_contains( $css, '{border-radius:0;}' );
				}
			)
		);

		Product_Image::enqueue();
	}

	public function test_nothing_outside_product_page(): void {
		Functions\when( 'is_product' )->justReturn( false );
		Functions\expect( 'wp_register_style' )->never();

		Product_Image::enqueue();
	}

	public function test_switched_off_loads_nothing(): void {
		Functions\when( 'is_product' )->justReturn( true );
		$this->options[ Look_Settings::OPTION ] = array( 'zdjecie_pelna_szerokosc' => 'no' );
		Functions\expect( 'wp_register_style' )->never();

		// Wywołanie jak z WordPressa: akcja bez argumentów podaje ''.
		call_user_func_array( array( Product_Image::class, 'enqueue' ), array( '' ) );
	}
}
