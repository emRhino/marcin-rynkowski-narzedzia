<?php
/**
 * Przypisanie do produktów, kategorii i tagów.
 *
 * @package Rynkowski\Narzedzia
 */

namespace Rynkowski\Narzedzia;

defined( 'ABSPATH' ) || exit;

/**
 * Wspólna reguła: element obejmuje produkt wprost albo przez jego kategorię lub tag.
 */
final class Assignment {

	/**
	 * Czy reguły przypisania obejmują produkt.
	 *
	 * @param array{products: int[], categories: int[], tags: int[]} $rules      Przypisanie.
	 * @param int                                                    $product_id ID produktu.
	 */
	public static function matches( array $rules, int $product_id ): bool {
		if ( in_array( $product_id, $rules['products'], true ) ) {
			return true;
		}

		if ( $rules['categories'] && array_intersect( $rules['categories'], wc_get_product_term_ids( $product_id, 'product_cat' ) ) ) {
			return true;
		}

		return $rules['tags'] && array_intersect( $rules['tags'], wc_get_product_term_ids( $product_id, 'product_tag' ) );
	}
}
