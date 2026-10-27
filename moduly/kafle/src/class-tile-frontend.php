<?php
/**
 * Kafle promocyjne: wstawianie do siatki produktów.
 *
 * @package Rynkowski\Narzedzia
 */

namespace Rynkowski\Narzedzia\Moduly\Kafle;

use Rynkowski\Narzedzia\Plugin;

defined( 'ABSPATH' ) || exit;

/**
 * Wstawia kafle między produkty na stronie sklepu, kategorii i tagu.
 *
 * @phpstan-import-type TileData from Tiles
 */
final class Tile_Frontend {

	/**
	 * Kafle pasujące do bieżącej listy, posortowane po pozycji.
	 *
	 * @var TileData[]|null
	 */
	private static ?array $tiles = null;

	/**
	 * Ile komórek siatki (produktów i kafli) już wypisano.
	 *
	 * @var int
	 */
	private static int $cells = 0;

	/**
	 * Podpina pętlę produktów i style.
	 */
	public static function register(): void {
		// Akcja odpala się przed każdym produktem w pętli archiwum.
		add_action( 'woocommerce_shop_loop', array( self::class, 'before_product' ), 5 );
		add_action( 'wp_enqueue_scripts', array( self::class, 'enqueue' ) );
	}

	/**
	 * Wypisuje kafle, których pozycja wypada przed bieżącym produktem.
	 */
	public static function before_product(): void {
		if ( wc_get_loop_prop( 'is_shortcode' ) || ! self::is_target_archive() ) {
			return;
		}

		if ( null === self::$tiles ) {
			self::$tiles = self::matching_tiles();
		}

		// Kafle zajmują kolejne wolne komórki, dopóki ich pozycja nie wypada dalej niż następna komórka.
		while ( self::$tiles && self::$tiles[0]['position'] <= self::$cells + 1 ) {
			$tile = array_shift( self::$tiles );
			echo self::tile_html( $tile ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escapowane w tile_html().
			++self::$cells;
		}

		// Komórka bieżącego produktu.
		++self::$cells;
	}

	/**
	 * Czy jesteśmy na liście produktów obsługiwanej przez kafle.
	 */
	private static function is_target_archive(): bool {
		return is_shop() || is_product_category() || is_product_tag();
	}

	/**
	 * Aktywne kafle przypisane do bieżącej listy i strony.
	 *
	 * @return TileData[]
	 */
	private static function matching_tiles(): array {
		$term    = is_product_category() || is_product_tag() ? get_queried_object() : null;
		$term_id = $term instanceof \WP_Term ? $term->term_id : 0;
		$page    = max( 1, absint( get_query_var( 'paged' ) ) );

		$tiles = array_filter(
			Tiles::all(),
			static function ( array $tile ) use ( $term_id, $page ): bool {
				if ( ! $tile['active'] || ( $tile['first_page_only'] && $page > 1 ) || ! self::has_content( $tile ) ) {
					return false;
				}
				if ( is_shop() ) {
					return $tile['on_shop'];
				}
				if ( is_product_category() ) {
					return in_array( $term_id, $tile['categories'], true );
				}

				return in_array( $term_id, $tile['tags'], true );
			}
		);

		// Stabilne sortowanie po pozycji; przy tej samej pozycji kolejność z listy kafli.
		usort(
			$tiles,
			static function ( array $a, array $b ): int {
				return $a['position'] <=> $b['position'];
			}
		);

		return $tiles;
	}

	/**
	 * Czy kafel ma treść wymaganą przez swój typ.
	 *
	 * @param array $tile Kafel.
	 * @phpstan-param TileData $tile
	 */
	private static function has_content( array $tile ): bool {
		switch ( $tile['type'] ) {
			case 'video':
				return $tile['video_id'] > 0;
			case 'text':
				return '' !== $tile['heading'] || '' !== $tile['description'];
			default:
				return $tile['image_id'] > 0;
		}
	}

	/**
	 * HTML kafla jako element siatki produktów.
	 *
	 * @param array $tile Kafel.
	 * @phpstan-param TileData $tile
	 */
	private static function tile_html( array $tile ): string {
		$inner = '';
		$label = '';
		$extra = '';

		switch ( $tile['type'] ) {
			case 'video':
				$poster = $tile['poster_id'] ? wp_get_attachment_image_url( $tile['poster_id'], 'woocommerce_single' ) : '';
				$inner  = sprintf(
					'<video class="rynkowski-kafel__media" autoplay muted loop playsinline preload="metadata"%s><source src="%s" type="video/mp4"></video>',
					$poster ? ' poster="' . esc_url( $poster ) . '"' : '',
					esc_url( (string) wp_get_attachment_url( $tile['video_id'] ) )
				);
				$label  = $tile['poster_id'] ? (string) get_post_meta( $tile['poster_id'], '_wp_attachment_image_alt', true ) : '';
				// Przycisk poza linkiem: zatrzymanie ruchomej treści wymagane przez WCAG 2.2.2.
				$extra = sprintf(
					'<button type="button" class="rynkowski-kafel__pauza" aria-pressed="false">%s</button>',
					esc_html__( 'Zatrzymaj wideo', 'marcin-rynkowski-narzedzia' )
				);
				wp_enqueue_script( 'rynkowski-kafle' );
				break;

			case 'text':
				$style = '';
				$bg    = sanitize_hex_color( $tile['bg_color'] );
				$color = sanitize_hex_color( $tile['text_color'] );
				if ( $bg ) {
					$style .= '--kafel-bg:' . $bg . ';';
				}
				if ( $color ) {
					$style .= '--kafel-color:' . $color . ';';
				}
				$inner = sprintf(
					'<div class="rynkowski-kafel__tekst"%s>%s%s%s</div>',
					$style ? ' style="' . esc_attr( $style ) . '"' : '',
					'' !== $tile['heading'] ? '<h2 class="rynkowski-kafel__naglowek">' . esc_html( $tile['heading'] ) . '</h2>' : '',
					'' !== $tile['description'] ? '<p class="rynkowski-kafel__opis">' . nl2br( esc_html( $tile['description'] ) ) . '</p>' : '',
					'' !== $tile['button'] && '' !== $tile['link'] ? '<span class="rynkowski-kafel__przycisk ct-button">' . esc_html( $tile['button'] ) . '</span>' : ''
				);
				break;

			default:
				$inner = wp_get_attachment_image(
					$tile['image_id'],
					'woocommerce_single',
					false,
					array(
						'class'   => 'rynkowski-kafel__media',
						'loading' => 'lazy',
					)
				);
				$label = (string) get_post_meta( $tile['image_id'], '_wp_attachment_image_alt', true );
				break;
		}

		if ( '' !== $tile['link'] ) {
			// Link bez tekstu potrzebuje nazwy dla czytników ekranu.
			$aria  = 'text' !== $tile['type'] && '' === $label ? ' aria-label="' . esc_attr( $tile['name'] ) . '"' : '';
			$inner = '<a class="rynkowski-kafel__wnetrze" href="' . esc_url( $tile['link'] ) . '"' . $aria . '>' . $inner . '</a>';
		} else {
			$inner = '<div class="rynkowski-kafel__wnetrze">' . $inner . '</div>';
		}

		return sprintf(
			'<li class="rynkowski-kafel rynkowski-kafel--%s">%s%s</li>',
			esc_attr( $tile['type'] ),
			$inner,
			$extra
		);
	}

	/**
	 * Style kafli i skrypt pauzy wideo na listach produktów.
	 */
	public static function enqueue(): void {
		if ( ! self::is_target_archive() ) {
			return;
		}

		wp_register_style( 'rynkowski-kafle', false, array(), Plugin::VERSION );
		wp_enqueue_style( 'rynkowski-kafle' );
		wp_add_inline_style( 'rynkowski-kafle', self::css() );

		// Ładowany dopiero, gdy na stronie jest kafel wideo.
		wp_register_script( 'rynkowski-kafle', plugins_url( 'assets/kafle.js', dirname( __DIR__ ) . '/module.php' ), array(), Plugin::VERSION, true );
		wp_localize_script(
			'rynkowski-kafle',
			'rynkowskiKafle',
			array(
				'zatrzymaj' => __( 'Zatrzymaj wideo', 'marcin-rynkowski-narzedzia' ),
				'wznow'     => __( 'Wznów wideo', 'marcin-rynkowski-narzedzia' ),
			)
		);
	}

	/**
	 * Style kafli.
	 */
	private static function css(): string {
		return '.rynkowski-kafel{position:relative;list-style:none;margin:0;min-height:18rem;overflow:hidden;}'
			. '.rynkowski-kafel__wnetrze{position:absolute;inset:0;display:block;color:inherit;text-decoration:none;}'
			. '.rynkowski-kafel__wnetrze:focus-visible{outline:3px solid currentColor;outline-offset:-6px;}'
			. '.rynkowski-kafel__media{position:absolute;inset:0;width:100%;height:100%;object-fit:cover;}'
			. '.rynkowski-kafel__tekst{position:absolute;inset:0;display:flex;flex-direction:column;justify-content:center;align-items:flex-start;gap:12px;padding:24px;background:var(--kafel-bg,var(--theme-palette-color-1,#000));color:var(--kafel-color,#fff);}'
			. '.rynkowski-kafel__naglowek{margin:0;color:inherit;font-size:1.5em;line-height:1.2;}'
			. '.rynkowski-kafel__opis{margin:0;color:inherit;}'
			. '.rynkowski-kafel__pauza{position:absolute;inset-inline-end:10px;bottom:10px;z-index:2;padding:6px 10px;border:0;border-radius:3px;background:rgba(0,0,0,.65);color:#fff;font-size:12px;line-height:1.2;cursor:pointer;}'
			. '.rynkowski-kafel__pauza:focus-visible{outline:2px solid #fff;outline-offset:2px;}';
	}
}
