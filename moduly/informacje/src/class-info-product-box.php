<?php
/**
 * Informacje o zakupie: własne teksty w edycji produktu.
 *
 * @package Rynkowski\Narzedzia
 */

namespace Rynkowski\Narzedzia\Moduly\Informacje;

defined( 'ABSPATH' ) || exit;

/**
 * Okno „Informacje o zakupie” w edycji produktu: pole dla każdego aktywnego wiersza.
 */
final class Info_Product_Box {

	const NONCE = 'rynkowski_informacje_nonce';

	// Nazwa pola formularza: rynkowski_informacje[<ID wiersza>].
	const FIELD = 'rynkowski_informacje';

	/**
	 * Podpina okno i zapis.
	 */
	public static function register(): void {
		add_action( 'add_meta_boxes_product', array( self::class, 'add_meta_box' ) );
		add_action( 'save_post_product', array( self::class, 'save' ), 10, 2 );
	}

	/**
	 * Dodaje okno.
	 */
	public static function add_meta_box(): void {
		add_meta_box( 'rynkowski-informacje', __( 'Informacje o zakupie', 'marcin-rynkowski-narzedzia' ), array( self::class, 'render' ), 'product', 'normal', 'default' );
	}

	/**
	 * Pole dla każdego aktywnego wiersza; pod nim tekst domyślny.
	 *
	 * @param \WP_Post $post Edytowany produkt.
	 */
	public static function render( \WP_Post $post ): void {
		$rows = Info_Rows::active();
		if ( ! $rows ) {
			echo '<p>' . esc_html__( 'Brak aktywnych wierszy. Dodaj je w Narzędzia → Informacje o zakupie.', 'marcin-rynkowski-narzedzia' ) . '</p>';
			return;
		}

		$texts = Info_Rows::product_texts( $post->ID );

		wp_nonce_field( self::NONCE, self::NONCE );
		echo '<p>' . esc_html__( 'Puste pole = tekst domyślny wiersza.', 'marcin-rynkowski-narzedzia' ) . '</p>';
		?>
		<table class="form-table" role="presentation">
			<?php foreach ( $rows as $row ) : ?>
				<?php $id = self::FIELD . '_' . $row['id']; ?>
				<tr>
					<th scope="row" class="titledesc"><label for="<?php echo esc_attr( $id ); ?>"><?php echo esc_html( $row['name'] ); ?></label></th>
					<td class="forminp">
						<?php Info_Row_Admin::editor( $texts[ $row['id'] ] ?? '', $id, self::FIELD . '[' . $row['id'] . ']' ); ?>
						<p class="description">
							<?php esc_html_e( 'Domyślnie:', 'marcin-rynkowski-narzedzia' ); ?>
							<?php echo '' !== $row['text'] ? wp_kses( $row['text'], Info_Rows::allowed_html() ) : esc_html__( '(pusty, wiersz się nie pokaże)', 'marcin-rynkowski-narzedzia' ); ?>
						</p>
					</td>
				</tr>
			<?php endforeach; ?>
		</table>
		<?php
	}

	/**
	 * Zapisuje teksty aktywnych wierszy; teksty wyłączonych zostają.
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

		// Pojedyncze teksty czyszczone niżej w Info_Rows::clean_text(); klucze tylko z listy aktywnych wierszy.
		$posted = isset( $_POST[ self::FIELD ] ) && is_array( $_POST[ self::FIELD ] ) ? $_POST[ self::FIELD ] : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$texts  = Info_Rows::product_texts( $post_id );

		foreach ( Info_Rows::active() as $row ) {
			$raw  = $posted[ $row['id'] ] ?? '';
			$text = is_string( $raw ) ? Info_Rows::clean_text( wp_unslash( $raw ) ) : '';

			if ( '' === $text ) {
				unset( $texts[ $row['id'] ] );
			} else {
				$texts[ $row['id'] ] = $text;
			}
		}

		if ( $texts ) {
			update_post_meta( $post_id, Info_Rows::META_PRODUCT_TEXTS, $texts );
		} else {
			delete_post_meta( $post_id, Info_Rows::META_PRODUCT_TEXTS );
		}
	}
}
