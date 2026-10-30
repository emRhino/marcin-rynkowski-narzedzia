<?php
/**
 * Zakładki produktu: pola w edycji produktu.
 *
 * @package Rynkowski\Narzedzia
 */

namespace Rynkowski\Narzedzia\Moduly\Zakladki;

defined( 'ABSPATH' ) || exit;

/**
 * Pole „Zakładki produktu” z edytorem dla każdej zakładki i zapis treści.
 */
final class Tab_Editor {

	const NONCE = 'rynkowski_zakladki_nonce';

	// Nazwa pola formularza: rynkowski_zakladki[<klucz>].
	const FIELD = 'rynkowski_zakladki';

	/**
	 * Podpina pole i zapis.
	 */
	public static function register(): void {
		add_action( 'add_meta_boxes_product', array( self::class, 'add_meta_box' ) );
		add_action( 'save_post_product', array( self::class, 'save' ), 10, 2 );
	}

	/**
	 * Dodaje pole pod opisem produktu.
	 */
	public static function add_meta_box(): void {
		add_meta_box( 'rynkowski-zakladki', __( 'Zakładki produktu', 'marcin-rynkowski-narzedzia' ), array( self::class, 'render' ), 'product', 'normal', 'high' );
	}

	/**
	 * Edytor dla każdej zakładki.
	 *
	 * @param \WP_Post $post Edytowany produkt.
	 */
	public static function render( \WP_Post $post ): void {
		wp_nonce_field( self::NONCE, self::NONCE );

		foreach ( Product_Tabs::labels() as $key => $title ) {
			$id = self::FIELD . '_' . $key;
			printf( '<p><label for="%s"><strong>%s</strong></label></p>', esc_attr( $id ), esc_html( $title ) );
			wp_editor(
				Product_Tabs::content( $post->ID, $key ),
				$id,
				array(
					'textarea_name' => self::FIELD . '[' . $key . ']',
					'textarea_rows' => 8,
				)
			);
		}
		echo '<p>' . esc_html__( 'Pusta zakładka nie pokazuje się w sklepie.', 'marcin-rynkowski-narzedzia' ) . '</p>';
	}

	/**
	 * Zapisuje treści zakładek; pusta treść usuwa meta.
	 *
	 * @param int      $post_id ID produktu.
	 * @param \WP_Post $post    Produkt.
	 */
	public static function save( int $post_id, \WP_Post $post ): void {
		if ( ! isset( $_POST[ self::NONCE ] ) || ! wp_verify_nonce( sanitize_key( $_POST[ self::NONCE ] ), self::NONCE ) ) {
			return;
		}
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}
		if ( ! current_user_can( 'edit_post', $post_id ) || 'product' !== $post->post_type ) {
			return;
		}

		// Pojedyncze pola czytane i sanityzowane niżej (wp_kses_post).
		$posted = isset( $_POST[ self::FIELD ] ) && is_array( $_POST[ self::FIELD ] ) ? $_POST[ self::FIELD ] : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized

		foreach ( array_keys( Product_Tabs::labels() ) as $key ) {
			$content = isset( $posted[ $key ] ) && is_string( $posted[ $key ] ) ? trim( wp_kses_post( wp_unslash( $posted[ $key ] ) ) ) : '';

			if ( '' === $content ) {
				delete_post_meta( $post_id, Product_Tabs::meta_key( $key ) );
			} else {
				update_post_meta( $post_id, Product_Tabs::meta_key( $key ), $content );
			}
		}
	}
}
