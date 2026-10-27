<?php
/**
 * Wstążki: wyświetlanie na zdjęciach produktów w Blocksy.
 *
 * @package Rynkowski\Narzedzia
 */

namespace Rynkowski\Narzedzia;

defined( 'ABSPATH' ) || exit;

/**
 * Dokłada wstążki do odznak Blocksy na kartach i w galerii produktu.
 */
final class Ribbon_Frontend {

	const STACK_CLASS = 'rynkowski-wstazki-stos';

	/**
	 * Aktywne wstążki, pobierane raz na żądanie.
	 *
	 * @var array<int, array{id: int, name: string, text: string, text_color: string, bg_color: string, active: bool, products: int[], categories: int[], tags: int[]}>|null
	 */
	private static ?array $active = null;

	/**
	 * Podpina filtry Blocksy i style.
	 */
	public static function register(): void {
		// Późny priorytet, żeby odznaki innych wtyczek (np. Companion) były już na liście.
		add_filter( 'blocksy:woocommerce:product-card:badges', array( self::class, 'add_badges' ), 99 );
		add_filter( 'blocksy:woocommerce:single:after-sale-badge', array( self::class, 'add_badges' ), 99 );
		add_action( 'wp_enqueue_scripts', array( self::class, 'enqueue' ) );
	}

	/**
	 * Dodaje wstążki bieżącego produktu i układa odznaki w stos.
	 *
	 * @param mixed $badges HTML odznak z Blocksy.
	 * @return mixed
	 */
	public static function add_badges( $badges ) {
		global $product;

		if ( ! is_array( $badges ) || ! $product instanceof \WC_Product ) {
			return $badges;
		}

		foreach ( self::ribbons_for( $product ) as $ribbon ) {
			$badges[] = self::badge_html( $ribbon );
		}

		$badges = array_values(
			array_filter(
				$badges,
				static function ( $badge ): bool {
					return is_string( $badge ) && '' !== trim( $badge );
				}
			)
		);

		if ( count( $badges ) < 2 || str_contains( implode( '', $badges ), self::STACK_CLASS ) ) {
			return $badges;
		}

		// Każda odznaka Blocksy ma pozycję absolutną w tym samym rogu; kontener układa je w kolumnę.
		return array( '<div class="' . esc_attr( self::STACK_CLASS ) . '">' . implode( '', $badges ) . '</div>' );
	}

	/**
	 * Aktywne wstążki obejmujące produkt.
	 *
	 * @param \WC_Product $product Produkt.
	 * @return array<int, array{id: int, name: string, text: string, text_color: string, bg_color: string, active: bool, products: int[], categories: int[], tags: int[]}>
	 */
	private static function ribbons_for( \WC_Product $product ): array {
		if ( null === self::$active ) {
			self::$active = array_values(
				array_filter(
					Ribbons::all(),
					static function ( array $ribbon ): bool {
						return $ribbon['active'] && '' !== $ribbon['text'];
					}
				)
			);
		}

		if ( ! self::$active ) {
			return array();
		}

		$product_id = $product->get_id();
		$categories = wc_get_product_term_ids( $product_id, 'product_cat' );
		$tags       = wc_get_product_term_ids( $product_id, 'product_tag' );

		return array_filter(
			self::$active,
			static function ( array $ribbon ) use ( $product_id, $categories, $tags ): bool {
				return in_array( $product_id, $ribbon['products'], true )
					|| array_intersect( $ribbon['categories'], $categories )
					|| array_intersect( $ribbon['tags'], $tags );
			}
		);
	}

	/**
	 * HTML jednej wstążki w stylu odznak Blocksy.
	 *
	 * @param array{text: string, text_color: string, bg_color: string} $ribbon Dane wstążki.
	 */
	private static function badge_html( array $ribbon ): string {
		$shape = function_exists( 'blocksy_get_theme_mod' ) ? blocksy_get_theme_mod( 'sale_badge_shape', 'type-2' ) : 'type-2';

		// Kolory sprawdzane ponownie przy wypisaniu, nie tylko przy zapisie.
		$style      = '';
		$text_color = sanitize_hex_color( $ribbon['text_color'] );
		$bg_color   = sanitize_hex_color( $ribbon['bg_color'] );
		if ( $text_color ) {
			$style .= '--badge-text-color:' . $text_color . ';';
		}
		if ( $bg_color ) {
			$style .= '--badge-background-color:' . $bg_color . ';';
		}

		return sprintf(
			'<span class="ct-woo-badge-rynkowski" data-shape="%s"%s>%s</span>',
			esc_attr( is_string( $shape ) ? $shape : 'type-2' ),
			$style ? ' style="' . esc_attr( $style ) . '"' : '',
			esc_html( $ribbon['text'] )
		);
	}

	/**
	 * Style kontenera układającego odznaki w stos.
	 */
	public static function enqueue(): void {
		wp_register_style( 'rynkowski-wstazki', false, array(), VERSION );
		wp_enqueue_style( 'rynkowski-wstazki' );
		wp_add_inline_style(
			'rynkowski-wstazki',
			'.' . self::STACK_CLASS . '{position:absolute;z-index:2;top:var(--badge-top,15px);inset-inline-start:var(--badge-left,15px);display:flex;flex-direction:column;align-items:flex-start;gap:5px;--has-container:relative;pointer-events:none;}'
		);
	}
}
