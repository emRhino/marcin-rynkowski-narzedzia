<?php
/**
 * Informacje o zakupie: typ wpisu „Wiersz informacji” i odczyt danych.
 *
 * @package Rynkowski\Narzedzia
 */

namespace Rynkowski\Narzedzia\Moduly\Informacje;

use Rynkowski\Narzedzia\Settings_Page;

defined( 'ABSPATH' ) || exit;

/**
 * Wiersze sekcji (ikona, tekst domyślny, aktywność; kolejność w polu „Kolejność”) i teksty produktów.
 *
 * @phpstan-type RowData array{id: int, name: string, icon_id: int, text: string, active: bool, order: int}
 */
final class Info_Rows {

	const POST_TYPE = 'rynkowski_informacja';

	const META_ICON   = '_rynkowski_informacja_ikona';
	const META_TEXT   = '_rynkowski_informacja_tekst';
	const META_ACTIVE = '_rynkowski_informacja_aktywny';

	// Własne teksty produktu: ID wiersza => tekst.
	const META_PRODUCT_TEXTS = '_rynkowski_informacje_teksty';

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
					'name'               => __( 'Informacje o zakupie', 'marcin-rynkowski-narzedzia' ),
					'singular_name'      => __( 'Wiersz informacji', 'marcin-rynkowski-narzedzia' ),
					'menu_name'          => __( 'Informacje o zakupie', 'marcin-rynkowski-narzedzia' ),
					'add_new'            => __( 'Dodaj wiersz', 'marcin-rynkowski-narzedzia' ),
					'add_new_item'       => __( 'Dodaj wiersz informacji', 'marcin-rynkowski-narzedzia' ),
					'edit_item'          => __( 'Edytuj wiersz informacji', 'marcin-rynkowski-narzedzia' ),
					'new_item'           => __( 'Nowy wiersz informacji', 'marcin-rynkowski-narzedzia' ),
					'search_items'       => __( 'Szukaj wierszy', 'marcin-rynkowski-narzedzia' ),
					'not_found'          => __( 'Brak wierszy.', 'marcin-rynkowski-narzedzia' ),
					'not_found_in_trash' => __( 'Brak wierszy w koszu.', 'marcin-rynkowski-narzedzia' ),
				),
				'public'          => false,
				'show_ui'         => true,
				'show_in_menu'    => Settings_Page::SLUG,
				'show_in_rest'    => false,
				'supports'        => array( 'title', 'page-attributes' ),
				'capability_type' => 'product',
				'map_meta_cap'    => true,
			)
		);
	}

	/**
	 * Dozwolony HTML podpisu: pogrubienie, link, wyróżnienie kolorem, mniejszy dopisek, nowa linia.
	 *
	 * @return array<string, array<string, bool>>
	 */
	public static function allowed_html(): array {
		return array(
			'strong' => array(),
			'b'      => array(),
			'em'     => array(),
			'mark'   => array(),
			'small'  => array(),
			'br'     => array(),
			'a'      => array( 'href' => true ),
		);
	}

	/**
	 * Czyści podpis do dozwolonego HTML (także adresy linków, np. bez javascript:).
	 *
	 * @param string $text Surowy tekst.
	 */
	public static function clean_text( string $text ): string {
		return trim( wp_kses( $text, self::allowed_html() ) );
	}

	/**
	 * Dane jednego wiersza.
	 *
	 * @param int $id ID wiersza.
	 * @return RowData
	 */
	public static function get( int $id ): array {
		return array(
			'id'      => $id,
			'name'    => get_the_title( $id ),
			'icon_id' => absint( get_post_meta( $id, self::META_ICON, true ) ),
			'text'    => (string) get_post_meta( $id, self::META_TEXT, true ),
			// Nowy wiersz bez zapisanej wartości jest aktywny.
			'active'  => 'no' !== get_post_meta( $id, self::META_ACTIVE, true ),
			'order'   => (int) get_post_field( 'menu_order', $id ),
		);
	}

	/**
	 * Opublikowane wiersze w kolejności z pola „Kolejność”.
	 *
	 * @return RowData[]
	 */
	public static function all(): array {
		$ids = get_posts(
			array(
				'post_type'      => self::POST_TYPE,
				'post_status'    => 'publish',
				'posts_per_page' => -1,
				'fields'         => 'ids',
				'orderby'        => array(
					'menu_order' => 'ASC',
					'ID'         => 'ASC',
				),
			)
		);

		return array_map(
			static function ( $id ): array {
				return self::get( (int) $id );
			},
			$ids
		);
	}

	/**
	 * Aktywne wiersze w kolejności.
	 *
	 * @return RowData[]
	 */
	public static function active(): array {
		return array_values(
			array_filter(
				self::all(),
				static function ( array $row ): bool {
					return $row['active'];
				}
			)
		);
	}

	/**
	 * Własne teksty produktu.
	 *
	 * @param int $product_id ID produktu.
	 * @return array<int, string> ID wiersza => tekst.
	 */
	public static function product_texts( int $product_id ): array {
		$stored = get_post_meta( $product_id, self::META_PRODUCT_TEXTS, true );
		$texts  = array();

		foreach ( is_array( $stored ) ? $stored : array() as $row_id => $text ) {
			if ( is_int( $row_id ) && is_string( $text ) && '' !== $text ) {
				$texts[ $row_id ] = $text;
			}
		}

		return $texts;
	}

	/**
	 * Wiersze do pokazania na produkcie: aktywne, z tekstem produktu albo domyślnym; puste pomija.
	 *
	 * @param int $product_id ID produktu.
	 * @return array<int, array{icon_id: int, text: string}>
	 */
	public static function for_product( int $product_id ): array {
		$texts = self::product_texts( $product_id );
		$rows  = array();

		foreach ( self::active() as $row ) {
			$text = $texts[ $row['id'] ] ?? $row['text'];
			if ( '' !== $text ) {
				$rows[] = array(
					'icon_id' => $row['icon_id'],
					'text'    => $text,
				);
			}
		}

		return $rows;
	}
}
