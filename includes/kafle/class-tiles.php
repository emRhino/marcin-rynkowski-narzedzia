<?php
/**
 * Kafle promocyjne: typ wpisu i odczyt danych.
 *
 * @package Rynkowski\Narzedzia
 */

namespace Rynkowski\Narzedzia;

defined( 'ABSPATH' ) || exit;

/**
 * Rejestruje typ wpisu „Kafel promocyjny” i udostępnia jego dane.
 *
 * @phpstan-type TileData array{
 *     id: int,
 *     name: string,
 *     type: string,
 *     link: string,
 *     active: bool,
 *     on_shop: bool,
 *     categories: int[],
 *     tags: int[],
 *     position: int,
 *     first_page_only: bool,
 *     image_id: int,
 *     video_id: int,
 *     poster_id: int,
 *     heading: string,
 *     description: string,
 *     button: string,
 *     bg_color: string,
 *     text_color: string
 * }
 */
final class Tiles {

	const POST_TYPE = 'rynkowski_kafel';
	const TYPES     = array( 'image', 'video', 'text' );

	const META_TYPE            = '_rynkowski_type';
	const META_LINK            = '_rynkowski_link';
	const META_ACTIVE          = '_rynkowski_active';
	const META_ON_SHOP         = '_rynkowski_on_shop';
	const META_CATEGORIES      = '_rynkowski_categories';
	const META_TAGS            = '_rynkowski_tags';
	const META_POSITION        = '_rynkowski_position';
	const META_FIRST_PAGE_ONLY = '_rynkowski_first_page_only';
	const META_IMAGE_ID        = '_rynkowski_image_id';
	const META_VIDEO_ID        = '_rynkowski_video_id';
	const META_POSTER_ID       = '_rynkowski_poster_id';
	const META_HEADING         = '_rynkowski_heading';
	const META_DESCRIPTION     = '_rynkowski_description';
	const META_BUTTON          = '_rynkowski_button';
	const META_BG_COLOR        = '_rynkowski_bg_color';
	const META_TEXT_COLOR      = '_rynkowski_text_color';

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
					'name'               => __( 'Kafle promocyjne', 'marcin-rynkowski-narzedzia' ),
					'singular_name'      => __( 'Kafel promocyjny', 'marcin-rynkowski-narzedzia' ),
					'menu_name'          => __( 'Kafle promocyjne', 'marcin-rynkowski-narzedzia' ),
					'add_new'            => __( 'Dodaj kafel', 'marcin-rynkowski-narzedzia' ),
					'add_new_item'       => __( 'Dodaj kafel promocyjny', 'marcin-rynkowski-narzedzia' ),
					'edit_item'          => __( 'Edytuj kafel promocyjny', 'marcin-rynkowski-narzedzia' ),
					'new_item'           => __( 'Nowy kafel promocyjny', 'marcin-rynkowski-narzedzia' ),
					'search_items'       => __( 'Szukaj kafli', 'marcin-rynkowski-narzedzia' ),
					'not_found'          => __( 'Brak kafli.', 'marcin-rynkowski-narzedzia' ),
					'not_found_in_trash' => __( 'Brak kafli w koszu.', 'marcin-rynkowski-narzedzia' ),
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
	 * Zwraca dane jednego kafla.
	 *
	 * @param int $id ID kafla.
	 * @return TileData
	 */
	public static function get( int $id ): array {
		$type = (string) get_post_meta( $id, self::META_TYPE, true );

		return array(
			'id'              => $id,
			'name'            => get_the_title( $id ),
			'type'            => in_array( $type, self::TYPES, true ) ? $type : 'image',
			'link'            => (string) get_post_meta( $id, self::META_LINK, true ),
			'active'          => 'yes' === get_post_meta( $id, self::META_ACTIVE, true ),
			'on_shop'         => 'yes' === get_post_meta( $id, self::META_ON_SHOP, true ),
			'categories'      => self::get_ids( $id, self::META_CATEGORIES ),
			'tags'            => self::get_ids( $id, self::META_TAGS ),
			'position'        => max( 1, absint( get_post_meta( $id, self::META_POSITION, true ) ) ),
			// Domyślnie tylko pierwsza strona listy, także dla nowych kafli bez zapisanej wartości.
			'first_page_only' => 'no' !== get_post_meta( $id, self::META_FIRST_PAGE_ONLY, true ),
			'image_id'        => absint( get_post_meta( $id, self::META_IMAGE_ID, true ) ),
			'video_id'        => absint( get_post_meta( $id, self::META_VIDEO_ID, true ) ),
			'poster_id'       => absint( get_post_meta( $id, self::META_POSTER_ID, true ) ),
			'heading'         => (string) get_post_meta( $id, self::META_HEADING, true ),
			'description'     => (string) get_post_meta( $id, self::META_DESCRIPTION, true ),
			'button'          => (string) get_post_meta( $id, self::META_BUTTON, true ),
			'bg_color'        => (string) get_post_meta( $id, self::META_BG_COLOR, true ),
			'text_color'      => (string) get_post_meta( $id, self::META_TEXT_COLOR, true ),
		);
	}

	/**
	 * Zwraca wszystkie opublikowane kafle.
	 *
	 * @return TileData[]
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
	 * Odczytuje listę ID zapisaną w meta.
	 *
	 * @param int    $id  ID kafla.
	 * @param string $key Klucz meta.
	 * @return int[]
	 */
	private static function get_ids( int $id, string $key ): array {
		$value = get_post_meta( $id, $key, true );

		return is_array( $value ) ? array_values( array_filter( array_map( 'absint', $value ) ) ) : array();
	}
}
