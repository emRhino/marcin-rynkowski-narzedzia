<?php
/**
 * Liczniki promocji: typ wpisu i odczyt danych.
 *
 * @package Rynkowski\Narzedzia
 */

namespace Rynkowski\Narzedzia\Moduly\Liczniki;

use Rynkowski\Narzedzia\Assignment;
use Rynkowski\Narzedzia\Settings_Page;

defined( 'ABSPATH' ) || exit;

/**
 * Rejestruje typ wpisu „Licznik promocji” i udostępnia jego dane.
 *
 * @phpstan-type CounterData array{
 *     id: int,
 *     name: string,
 *     text: string,
 *     end: int,
 *     active: bool,
 *     products: int[],
 *     categories: int[],
 *     tags: int[]
 * }
 */
final class Counters {

	const POST_TYPE = 'rynkowski_licznik';

	const META_TEXT       = '_rynkowski_licznik_text';
	const META_END        = '_rynkowski_licznik_end';
	const META_ACTIVE     = '_rynkowski_licznik_active';
	const META_PRODUCTS   = '_rynkowski_licznik_products';
	const META_CATEGORIES = '_rynkowski_licznik_categories';
	const META_TAGS       = '_rynkowski_licznik_tags';

	/**
	 * Podpina rejestrację typu wpisu.
	 */
	public static function register(): void {
		add_action( 'init', array( self::class, 'register_post_type' ) );
	}

	/**
	 * Rejestruje niepubliczny typ wpisu w menu Narzędzia.
	 */
	public static function register_post_type(): void {
		register_post_type(
			self::POST_TYPE,
			array(
				'labels'          => array(
					'name'               => __( 'Liczniki promocji', 'marcin-rynkowski-narzedzia' ),
					'singular_name'      => __( 'Licznik promocji', 'marcin-rynkowski-narzedzia' ),
					'menu_name'          => __( 'Liczniki promocji', 'marcin-rynkowski-narzedzia' ),
					'add_new'            => __( 'Dodaj licznik', 'marcin-rynkowski-narzedzia' ),
					'add_new_item'       => __( 'Dodaj licznik promocji', 'marcin-rynkowski-narzedzia' ),
					'edit_item'          => __( 'Edytuj licznik promocji', 'marcin-rynkowski-narzedzia' ),
					'new_item'           => __( 'Nowy licznik promocji', 'marcin-rynkowski-narzedzia' ),
					'search_items'       => __( 'Szukaj liczników', 'marcin-rynkowski-narzedzia' ),
					'not_found'          => __( 'Brak liczników.', 'marcin-rynkowski-narzedzia' ),
					'not_found_in_trash' => __( 'Brak liczników w koszu.', 'marcin-rynkowski-narzedzia' ),
				),
				'public'          => false,
				'show_ui'         => true,
				'show_in_menu'    => Settings_Page::SLUG,
				'show_in_rest'    => false,
				'supports'        => array( 'title' ),
				'capability_type' => 'product',
				'map_meta_cap'    => true,
			)
		);
	}

	/**
	 * Zwraca dane jednego licznika.
	 *
	 * @param int $id ID licznika.
	 * @return CounterData
	 */
	public static function get( int $id ): array {
		return array(
			'id'         => $id,
			'name'       => get_the_title( $id ),
			'text'       => (string) get_post_meta( $id, self::META_TEXT, true ),
			'end'        => absint( get_post_meta( $id, self::META_END, true ) ),
			'active'     => 'yes' === get_post_meta( $id, self::META_ACTIVE, true ),
			'products'   => self::get_ids( $id, self::META_PRODUCTS ),
			'categories' => self::get_ids( $id, self::META_CATEGORIES ),
			'tags'       => self::get_ids( $id, self::META_TAGS ),
		);
	}

	/**
	 * Zwraca wszystkie opublikowane liczniki.
	 *
	 * @return CounterData[]
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
	 * Czy licznik trwa: włączony, z końcem w przyszłości.
	 *
	 * @param array $counter Licznik.
	 * @param int   $now     Bieżący czas (Unix).
	 * @phpstan-param CounterData $counter
	 */
	public static function is_running( array $counter, int $now ): bool {
		return $counter['active'] && $counter['end'] > $now;
	}

	/**
	 * Trwający licznik dla produktu, który kończy się najwcześniej.
	 *
	 * @param CounterData[] $counters   Liczniki.
	 * @param int           $product_id ID produktu.
	 * @param int           $now        Bieżący czas (Unix).
	 * @return CounterData|null
	 */
	public static function for_product( array $counters, int $product_id, int $now ): ?array {
		$found = null;

		foreach ( $counters as $counter ) {
			if ( ! self::is_running( $counter, $now ) || ! Assignment::matches( $counter, $product_id ) ) {
				continue;
			}
			if ( null === $found || $counter['end'] < $found['end'] ) {
				$found = $counter;
			}
		}

		return $found;
	}

	/**
	 * Odczytuje listę ID zapisaną w meta.
	 *
	 * @param int    $id  ID licznika.
	 * @param string $key Klucz meta.
	 * @return int[]
	 */
	private static function get_ids( int $id, string $key ): array {
		$value = get_post_meta( $id, $key, true );

		return is_array( $value ) ? array_values( array_filter( array_map( 'absint', $value ) ) ) : array();
	}
}
