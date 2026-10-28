<?php
/**
 * Poprawki wyglądu: zdjęcie produktu na całą szerokość ekranu na telefonie.
 *
 * @package Rynkowski\Narzedzia
 */

namespace Rynkowski\Narzedzia\Moduly\Wyglad;

use Rynkowski\Narzedzia\Plugin;

defined( 'ABSPATH' ) || exit;

/**
 * Główne zdjęcie galerii Blocksy (pojedyncze albo karuzela) od krawędzi do krawędzi ekranu.
 */
final class Product_Image {

	/**
	 * Podpina style.
	 */
	public static function register(): void {
		add_action( 'wp_enqueue_scripts', array( self::class, 'enqueue' ) );
	}

	/**
	 * Style na stronie produktu, gdy opcja jest włączona.
	 */
	public static function enqueue(): void {
		if ( 'yes' !== Look_Settings::get( 'zdjecie_pelna_szerokosc' ) || ! is_product() ) {
			return;
		}

		wp_register_style( 'rynkowski-zdjecie-produktu', false, array(), Plugin::VERSION );
		wp_enqueue_style( 'rynkowski-zdjecie-produktu' );
		wp_add_inline_style( 'rynkowski-zdjecie-produktu', self::css() );
	}

	/**
	 * Wyjście zdjęcia poza margines kontenera: szerokość ekranu i ujemny margines o połowę różnicy.
	 */
	private static function css(): string {
		$gallery = '.single-product .woocommerce-product-gallery';
		// Pojedyncze zdjęcie (figure) albo widok karuzeli; miniatury (.flexy-pills) zostają w marginesach.
		$image = $gallery . ' .ct-product-gallery-container>.ct-media-container,'
			. $gallery . ' .ct-product-gallery-container>.flexy-container>.flexy';

		return '@media (max-width:' . Breadcrumbs::MOBILE_MAX . '){'
			// Blocksy przycina galerię; karuzela przycina slajdy sama (.flexy-view „boxed”).
			. $gallery . '{overflow:visible;}'
			. $image . '{width:100vw;max-width:100vw;margin-inline:calc(50% - 50vw);}'
			. $gallery . ' .ct-product-gallery-container>.ct-media-container,'
			. $gallery . ' .ct-product-gallery-container>.flexy-container>.flexy .ct-media-container{border-radius:0;}'
			. '}';
	}
}
