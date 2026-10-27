<?php
/**
 * Wstążki: typ wpisu i odczyt danych.
 *
 * @package Rynkowski\Narzedzia
 */

namespace Rynkowski\Narzedzia;

defined( 'ABSPATH' ) || exit;

/**
 * Rejestruje typ wpisu „Wstążka” i udostępnia jej dane.
 *
 * @phpstan-type RibbonData array{
 *     id: int,
 *     name: string,
 *     text: string,
 *     text_color: string,
 *     bg_color: string,
 *     active: bool,
 *     products: int[],
 *     categories: int[],
 *     tags: int[]
 * }
 */
final class Ribbons {

	const POST_TYPE = 'rynkowski_wstazka';

	const META_TEXT       = '_rynkowski_text';
	const META_TEXT_COLOR = '_rynkowski_text_color';
	const META_BG_COLOR   = '_rynkowski_bg_color';
	const META_ACTIVE     = '_rynkowski_active';
	const META_PRODUCTS   = '_rynkowski_products';
	const META_CATEGORIES = '_rynkowski_categories';
	const META_TAGS       = '_rynkowski_tags';

	/**
	 * Podpina rejestrację typu wpisu.
	 */
	public static function register(): void {
		add_action( 'init', array( self::class, 'register_post_type' ) );
	}

	/**
	 * Rejestruje niepubliczny typ wpisu w menu WooCommerce.
	 */
	public static function register_post_type(): void {
		register_post_type(
			self::POST_TYPE,
			array(
				'labels'          => array(
					'name'               => __( 'Wstążki', 'marcin-rynkowski-narzedzia' ),
					'singular_name'      => __( 'Wstążka', 'marcin-rynkowski-narzedzia' ),
					'menu_name'          => __( 'Wstążki', 'marcin-rynkowski-narzedzia' ),
					'add_new'            => __( 'Dodaj wstążkę', 'marcin-rynkowski-narzedzia' ),
					'add_new_item'       => __( 'Dodaj wstążkę', 'marcin-rynkowski-narzedzia' ),
					'edit_item'          => __( 'Edytuj wstążkę', 'marcin-rynkowski-narzedzia' ),
					'new_item'           => __( 'Nowa wstążka', 'marcin-rynkowski-narzedzia' ),
					'search_items'       => __( 'Szukaj wstążek', 'marcin-rynkowski-narzedzia' ),
					'not_found'          => __( 'Brak wstążek.', 'marcin-rynkowski-narzedzia' ),
					'not_found_in_trash' => __( 'Brak wstążek w koszu.', 'marcin-rynkowski-narzedzia' ),
				),
				'public'          => false,
				'show_ui'         => true,
				'show_in_menu'    => 'woocommerce',
				'show_in_rest'    => false,
				'supports'        => array( 'title' ),
				'capability_type' => 'product',
				'map_meta_cap'    => true,
			)
		);
	}

	/**
	 * Zwraca dane jednej wstążki.
	 *
	 * @param int $id ID wstążki.
	 * @return RibbonData
	 */
	public static function get( int $id ): array {
		return array(
			'id'         => $id,
			'name'       => get_the_title( $id ),
			'text'       => (string) get_post_meta( $id, self::META_TEXT, true ),
			'text_color' => (string) get_post_meta( $id, self::META_TEXT_COLOR, true ),
			'bg_color'   => (string) get_post_meta( $id, self::META_BG_COLOR, true ),
			'active'     => 'yes' === get_post_meta( $id, self::META_ACTIVE, true ),
			'products'   => self::get_ids( $id, self::META_PRODUCTS ),
			'categories' => self::get_ids( $id, self::META_CATEGORIES ),
			'tags'       => self::get_ids( $id, self::META_TAGS ),
		);
	}

	/**
	 * Zwraca wszystkie opublikowane wstążki.
	 *
	 * @return RibbonData[]
	 */
	public static function all(): array {
		$ids = get_posts(
			array(
				'post_type'      => self::POST_TYPE,
				'post_status'    => 'publish',
				'posts_per_page' => -1,
				'fields'         => 'ids',
				'orderby'        => 'menu_order title',
				'order'          => 'ASC',
			)
		);

		return array_map( array( self::class, 'get' ), array_map( 'intval', $ids ) );
	}

	/**
	 * Zwraca wstążki obejmujące produkt, z powodem przypisania.
	 *
	 * @param int  $product_id  ID produktu.
	 * @param bool $only_active Czy pominąć wyłączone wstążki.
	 * @return array<int, array{ribbon: RibbonData, reasons: string[]}>
	 */
	public static function for_product( int $product_id, bool $only_active = true ): array {
		$categories = wc_get_product_term_ids( $product_id, 'product_cat' );
		$tags       = wc_get_product_term_ids( $product_id, 'product_tag' );
		$result     = array();

		foreach ( self::all() as $ribbon ) {
			if ( $only_active && ! $ribbon['active'] ) {
				continue;
			}

			$reasons = array();

			if ( in_array( $product_id, $ribbon['products'], true ) ) {
				$reasons[] = __( 'produkt', 'marcin-rynkowski-narzedzia' );
			}

			foreach ( array_intersect( $ribbon['categories'], $categories ) as $term_id ) {
				/* translators: %s: nazwa kategorii */
				$reasons[] = sprintf( __( 'kategoria: %s', 'marcin-rynkowski-narzedzia' ), self::term_name( $term_id ) );
			}

			foreach ( array_intersect( $ribbon['tags'], $tags ) as $term_id ) {
				/* translators: %s: nazwa tagu */
				$reasons[] = sprintf( __( 'tag: %s', 'marcin-rynkowski-narzedzia' ), self::term_name( $term_id ) );
			}

			if ( $reasons ) {
				$result[] = array(
					'ribbon'  => $ribbon,
					'reasons' => $reasons,
				);
			}
		}

		return $result;
	}

	/**
	 * Odczytuje listę ID zapisaną w meta.
	 *
	 * @param int    $id  ID wstążki.
	 * @param string $key Klucz meta.
	 * @return int[]
	 */
	private static function get_ids( int $id, string $key ): array {
		$value = get_post_meta( $id, $key, true );

		return is_array( $value ) ? array_values( array_filter( array_map( 'absint', $value ) ) ) : array();
	}

	/**
	 * Zwraca nazwę terminu albo pusty tekst.
	 *
	 * @param int $term_id ID terminu.
	 */
	private static function term_name( int $term_id ): string {
		$term = get_term( $term_id );

		return $term instanceof \WP_Term ? $term->name : '';
	}
}
