<?php
/**
 * Zakładki produktu: pola w edycji produktu.
 *
 * @package Rynkowski\Narzedzia
 */

namespace Rynkowski\Narzedzia\Moduly\Zakladki;

defined( 'ABSPATH' ) || exit;

/**
 * Osobne pole z edytorem dla każdej zakładki i zapis treści.
 */
final class Tab_Editor {

	const NONCE = 'rynkowski_zakladki_nonce';

	// Nazwa pola formularza: rynkowski_zakladki[<klucz>].
	const FIELD = 'rynkowski_zakladki';

	/**
	 * Podpina pole i zapis.
	 */
	public static function register(): void {
		add_action( 'add_meta_boxes_product', array( self::class, 'add_meta_boxes' ) );
		add_action( 'save_post_product', array( self::class, 'save' ), 10, 2 );
	}

	/**
	 * Dodaje osobne pole dla każdej włączonej zakładki, pod krótkim opisem produktu.
	 *
	 * Krótki opis WooCommerce ma kontekst „normal” i priorytet domyślny, a ten hook działa po
	 * `add_meta_boxes`, więc pola z tym samym priorytetem lądują za nim.
	 */
	public static function add_meta_boxes(): void {
		$tip = wc_help_tip( __( 'Treść zakładki na karcie produktu, zaraz po Opisie. Pusta zakładka nie pokazuje się w sklepie.', 'marcin-rynkowski-narzedzia' ) );

		foreach ( Product_Tabs::enabled() as $key => $title ) {
			add_meta_box( 'rynkowski-zakladki-' . $key, esc_html( $title ) . ' ' . $tip, array( self::class, 'render' ), 'product', 'normal', 'default', array( 'key' => $key ) );
		}
	}

	/**
	 * Edytor jednej zakładki; pole pierwszej włączonej zakładki dodaje też nonce.
	 *
	 * @param \WP_Post             $post Edytowany produkt.
	 * @param array<string, mixed> $box  Pole; w `args` klucz zakładki.
	 */
	public static function render( \WP_Post $post, array $box ): void {
		$key    = is_array( $box['args'] ?? null ) && is_string( $box['args']['key'] ?? null ) ? $box['args']['key'] : '';
		$labels = Product_Tabs::enabled();
		if ( ! array_key_exists( $key, $labels ) ) {
			return;
		}

		if ( array_key_first( $labels ) === $key ) {
			wp_nonce_field( self::NONCE, self::NONCE );
		}

		$id = self::FIELD . '_' . $key;
		printf( '<label class="screen-reader-text" for="%s">%s</label>', esc_attr( $id ), esc_html( $labels[ $key ] ) );
		wp_editor(
			Product_Tabs::content( $post->ID, $key ),
			$id,
			array(
				'textarea_name' => self::FIELD . '[' . $key . ']',
				'textarea_rows' => 8,
			)
		);
	}

	/**
	 * Zapisuje treści włączonych zakładek; pusta treść usuwa meta.
	 *
	 * Wyłączonych nie ruszamy: ich pól nie ma w formularzu, a treść ma przetrwać ponowne włączenie.
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

		foreach ( array_keys( Product_Tabs::enabled() ) as $key ) {
			$content = isset( $posted[ $key ] ) && is_string( $posted[ $key ] ) ? trim( wp_kses_post( wp_unslash( $posted[ $key ] ) ) ) : '';

			if ( '' === $content ) {
				delete_post_meta( $post_id, Product_Tabs::meta_key( $key ) );
			} else {
				update_post_meta( $post_id, Product_Tabs::meta_key( $key ), $content );
			}
		}
	}
}
