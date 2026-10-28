<?php
/**
 * Poprawki wyglądu: breadcrumb w jednej linii na telefonie.
 *
 * @package Rynkowski\Narzedzia
 */

namespace Rynkowski\Narzedzia\Moduly\Wyglad;

use Rynkowski\Narzedzia\Plugin;

defined( 'ABSPATH' ) || exit;

/**
 * Breadcrumb Blocksy (`.ct-breadcrumbs`, także z Yoast i Rank Math) w jednej przewijanej linii na telefonie.
 */
final class Breadcrumbs {

	// Próg „mobile” z Blocksy (jego media queries: max-width 689.98px).
	const MOBILE_MAX = '689.98px';

	/**
	 * Podpina style i skrypt.
	 */
	public static function register(): void {
		add_action( 'wp_enqueue_scripts', array( self::class, 'enqueue' ) );
	}

	/**
	 * Style i przewinięcie do końca, gdy opcja jest włączona.
	 */
	public static function enqueue(): void {
		if ( 'yes' !== Look_Settings::get( 'breadcrumb_w_linii' ) ) {
			return;
		}

		wp_register_style( 'rynkowski-okruszki', false, array(), Plugin::VERSION );
		wp_enqueue_style( 'rynkowski-okruszki' );
		wp_add_inline_style( 'rynkowski-okruszki', self::css() );

		wp_enqueue_script( 'rynkowski-okruszki', plugins_url( 'assets/okruszki.js', dirname( __DIR__ ) . '/module.php' ), array(), Plugin::VERSION, true );
	}

	/**
	 * Jedna linia z przewijaniem w bok, bez paska przewijania (przewija się palcem).
	 */
	private static function css(): string {
		return '@media (max-width:' . self::MOBILE_MAX . '){'
			. '.ct-breadcrumbs{white-space:nowrap;overflow-x:auto;overflow-y:hidden;-webkit-overflow-scrolling:touch;scrollbar-width:none;}'
			. '.ct-breadcrumbs::-webkit-scrollbar{display:none;}'
			. '}';
	}
}
