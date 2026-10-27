<?php
/**
 * Sprzątanie danych modułów.
 *
 * @package Rynkowski\Narzedzia
 */

namespace Rynkowski\Narzedzia;

defined( 'ABSPATH' ) || exit;

/**
 * Wspólne funkcje usuwania danych przy odinstalowaniu.
 */
final class Cleanup {

	/**
	 * Trwale usuwa wszystkie wpisy danego typu, razem z ich meta.
	 *
	 * @param string $post_type Typ wpisu.
	 */
	public static function delete_posts( string $post_type ): void {
		$ids = get_posts(
			array(
				'post_type'      => $post_type,
				'post_status'    => 'any',
				'posts_per_page' => -1,
				'fields'         => 'ids',
			)
		);

		foreach ( $ids as $id ) {
			wp_delete_post( (int) $id, true );
		}
	}
}
