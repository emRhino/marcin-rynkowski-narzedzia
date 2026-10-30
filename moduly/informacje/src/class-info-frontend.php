<?php
/**
 * Informacje o zakupie: sekcja na karcie produktu.
 *
 * @package Rynkowski\Narzedzia
 */

namespace Rynkowski\Narzedzia\Moduly\Informacje;

use Rynkowski\Narzedzia\Plugin;

defined( 'ABSPATH' ) || exit;

/**
 * Lista wierszy (ikona + podpis) pod przyciskiem „Dodaj do koszyka”.
 */
final class Info_Frontend {

	// WooCommerce: przycisk 30, meta 40. Blocksy przenosi priorytety 30–40 za warstwę „Add to Cart”.
	const PRIORITY = 35;

	/**
	 * Podpina sekcję i style.
	 */
	public static function register(): void {
		add_action( 'woocommerce_single_product_summary', array( self::class, 'render' ), self::PRIORITY );
		add_action( 'wp_enqueue_scripts', array( self::class, 'enqueue' ) );
	}

	/**
	 * Czy sekcja jest włączona w ustawieniach.
	 */
	private static function enabled(): bool {
		return 'yes' === Info_Settings::get( 'sekcja' );
	}

	/**
	 * Wypisuje sekcję bieżącego produktu.
	 */
	public static function render(): void {
		if ( ! self::enabled() ) {
			return;
		}

		$rows = Info_Rows::for_product( (int) get_the_ID() );
		if ( ! $rows ) {
			return;
		}

		$size = (int) Info_Settings::get( 'rozmiar_ikony' );
		echo '<ul class="rynkowski-informacje">';
		foreach ( $rows as $row ) {
			$url = $row['icon_id'] ? wp_get_attachment_image_url( $row['icon_id'], 'thumbnail' ) : false;

			echo '<li class="rynkowski-informacje__wiersz"><span class="rynkowski-informacje__ikona">';
			if ( $url ) {
				// Ikona ozdobna: znaczenie niesie podpis obok.
				printf( '<img src="%s" alt="" width="%d" height="%d" loading="lazy" decoding="async">', esc_url( $url ), (int) $size, (int) $size );
			}
			echo '</span><span class="rynkowski-informacje__tekst">' . wp_kses( nl2br( $row['text'], false ), Info_Rows::allowed_html() ) . '</span></li>';
		}
		echo '</ul>';
	}

	/**
	 * Style sekcji z ustawień, tylko na stronie produktu.
	 */
	public static function enqueue(): void {
		if ( ! is_product() || ! self::enabled() ) {
			return;
		}

		wp_register_style( 'rynkowski-informacje', false, array(), Plugin::VERSION );
		wp_enqueue_style( 'rynkowski-informacje' );
		wp_add_inline_style( 'rynkowski-informacje', self::css() );
	}

	/**
	 * CSS sekcji; wartości z ustawień są zwalidowane (liczby w zakresie, kolory hex).
	 */
	public static function css(): string {
		$border = (string) Info_Settings::get( 'kolor_ramki' );
		$mark   = (string) Info_Settings::get( 'kolor_wyroznienia' );
		$size   = (int) Info_Settings::get( 'rozmiar_ikony' );
		$pad    = (int) Info_Settings::get( 'padding' );
		$gap    = (int) Info_Settings::get( 'odstep' );
		$border = '' !== $border ? $border : 'var(--theme-border-color, #e0e0e0)';
		$mark   = '' !== $mark ? $mark : 'var(--theme-palette-color-1, currentColor)';
		$s      = '.rynkowski-informacje';

		return $s . '{list-style:none;margin:0 0 ' . $gap . 'px;padding:0;border:1px solid ' . $border . ';}'
			. $s . '__wiersz{display:flex;align-items:center;gap:' . max( 12, $pad ) . 'px;margin:0;padding:' . $pad . 'px;border-top:1px solid ' . $border . ';}'
			. $s . '__wiersz:first-child{border-top:0;}'
			. $s . '__ikona{flex:0 0 ' . $size . 'px;width:' . $size . 'px;height:' . $size . 'px;display:flex;align-items:center;justify-content:center;}'
			. $s . '__ikona img{width:100%;height:100%;object-fit:contain;}'
			. $s . '__tekst small{display:block;font-size:.85em;opacity:.75;}'
			. $s . '__tekst mark{background:none;color:' . $mark . ';}'
			. $s . '__tekst a{color:inherit;text-decoration:underline;}';
	}
}
