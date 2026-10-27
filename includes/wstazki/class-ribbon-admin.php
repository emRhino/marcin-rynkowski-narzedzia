<?php
/**
 * Wstążki: ekran edycji i lista w panelu.
 *
 * @package Rynkowski\Narzedzia
 */

namespace Rynkowski\Narzedzia;

defined( 'ABSPATH' ) || exit;

/**
 * Pola wstążki, zapis i kolumny listy.
 */
final class Ribbon_Admin {

	const NONCE = 'rynkowski_wstazka_nonce';

	/**
	 * Podpina akcje panelu.
	 */
	public static function register(): void {
		add_action( 'add_meta_boxes_' . Ribbons::POST_TYPE, array( self::class, 'add_meta_boxes' ) );
		add_action( 'save_post_' . Ribbons::POST_TYPE, array( self::class, 'save' ), 10, 2 );
		add_action( 'admin_enqueue_scripts', array( self::class, 'enqueue' ) );
		add_filter( 'manage_' . Ribbons::POST_TYPE . '_posts_columns', array( self::class, 'columns' ) );
		add_action( 'manage_' . Ribbons::POST_TYPE . '_posts_custom_column', array( self::class, 'render_column' ), 10, 2 );
		add_filter( 'enter_title_here', array( self::class, 'title_placeholder' ), 10, 2 );
	}

	/**
	 * Dodaje pola wstążki.
	 */
	public static function add_meta_boxes(): void {
		add_meta_box(
			'rynkowski-wstazka-wyglad',
			__( 'Wygląd', 'marcin-rynkowski-narzedzia' ),
			array( self::class, 'render_look' ),
			Ribbons::POST_TYPE,
			'normal',
			'high'
		);
		add_meta_box(
			'rynkowski-wstazka-przypisanie',
			__( 'Przypisanie', 'marcin-rynkowski-narzedzia' ),
			array( self::class, 'render_assignment' ),
			Ribbons::POST_TYPE,
			'normal',
			'default'
		);
	}

	/**
	 * Pola wyglądu: tekst, kolory, przełącznik aktywności.
	 *
	 * @param \WP_Post $post Edytowana wstążka.
	 */
	public static function render_look( \WP_Post $post ): void {
		$ribbon = Ribbons::get( $post->ID );

		wp_nonce_field( self::NONCE, self::NONCE );
		?>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><label for="rynkowski-text"><?php esc_html_e( 'Tekst wstążki', 'marcin-rynkowski-narzedzia' ); ?></label></th>
				<td><input type="text" id="rynkowski-text" name="rynkowski_text" class="regular-text" value="<?php echo esc_attr( $ribbon['text'] ); ?>" required></td>
			</tr>
			<tr>
				<th scope="row"><label for="rynkowski-text-color"><?php esc_html_e( 'Kolor tekstu', 'marcin-rynkowski-narzedzia' ); ?></label></th>
				<td>
					<input type="text" id="rynkowski-text-color" name="rynkowski_text_color" class="rynkowski-color" value="<?php echo esc_attr( $ribbon['text_color'] ); ?>">
					<p class="description"><?php esc_html_e( 'Puste pole = kolor z ustawień odznak w Blocksy.', 'marcin-rynkowski-narzedzia' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="rynkowski-bg-color"><?php esc_html_e( 'Kolor tła', 'marcin-rynkowski-narzedzia' ); ?></label></th>
				<td>
					<input type="text" id="rynkowski-bg-color" name="rynkowski_bg_color" class="rynkowski-color" value="<?php echo esc_attr( $ribbon['bg_color'] ); ?>">
					<p class="description"><?php esc_html_e( 'Puste pole = kolor z ustawień odznak w Blocksy.', 'marcin-rynkowski-narzedzia' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Aktywna', 'marcin-rynkowski-narzedzia' ); ?></th>
				<td>
					<label>
						<input type="checkbox" name="rynkowski_active" value="yes" <?php checked( $ribbon['active'] ); ?>>
						<?php esc_html_e( 'Pokazuj wstążkę na przypisanych produktach', 'marcin-rynkowski-narzedzia' ); ?>
					</label>
				</td>
			</tr>
		</table>
		<?php
	}

	/**
	 * Pola przypisania: produkty, kategorie, tagi.
	 *
	 * @param \WP_Post $post Edytowana wstążka.
	 */
	public static function render_assignment( \WP_Post $post ): void {
		$ribbon = Ribbons::get( $post->ID );
		?>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><label for="rynkowski-products"><?php esc_html_e( 'Produkty', 'marcin-rynkowski-narzedzia' ); ?></label></th>
				<td>
					<select id="rynkowski-products" name="rynkowski_products[]" class="wc-product-search" multiple style="width: 100%;" data-placeholder="<?php esc_attr_e( 'Wyszukaj produkt…', 'marcin-rynkowski-narzedzia' ); ?>" data-action="woocommerce_json_search_products">
						<?php
						foreach ( $ribbon['products'] as $product_id ) {
							$product = wc_get_product( $product_id );
							if ( $product ) {
								printf( '<option value="%d" selected>%s</option>', (int) $product_id, esc_html( wp_strip_all_tags( $product->get_formatted_name() ) ) );
							}
						}
						?>
					</select>
				</td>
			</tr>
			<?php
			self::render_term_select( 'product_cat', 'rynkowski_categories', __( 'Kategorie', 'marcin-rynkowski-narzedzia' ), $ribbon['categories'] );
			self::render_term_select( 'product_tag', 'rynkowski_tags', __( 'Tagi', 'marcin-rynkowski-narzedzia' ), $ribbon['tags'] );
			?>
		</table>
		<?php
	}

	/**
	 * Wiersz z wyborem terminów taksonomii.
	 *
	 * @param string $taxonomy Taksonomia.
	 * @param string $name     Nazwa pola.
	 * @param string $label    Etykieta.
	 * @param int[]  $selected Zaznaczone ID.
	 */
	private static function render_term_select( string $taxonomy, string $name, string $label, array $selected ): void {
		$terms = get_terms(
			array(
				'taxonomy'   => $taxonomy,
				'hide_empty' => false,
			)
		);
		$id    = 'rynkowski-' . $taxonomy;
		?>
		<tr>
			<th scope="row"><label for="<?php echo esc_attr( $id ); ?>"><?php echo esc_html( $label ); ?></label></th>
			<td>
				<select id="<?php echo esc_attr( $id ); ?>" name="<?php echo esc_attr( $name ); ?>[]" class="wc-enhanced-select" multiple style="width: 100%;">
					<?php
					if ( is_array( $terms ) ) {
						foreach ( $terms as $term ) {
							printf(
								'<option value="%d"%s>%s</option>',
								(int) $term->term_id,
								selected( in_array( (int) $term->term_id, $selected, true ), true, false ),
								esc_html( $term->name )
							);
						}
					}
					?>
				</select>
			</td>
		</tr>
		<?php
	}

	/**
	 * Zapisuje pola wstążki.
	 *
	 * @param int      $post_id ID wstążki.
	 * @param \WP_Post $post    Wstążka.
	 */
	public static function save( int $post_id, \WP_Post $post ): void {
		if ( ! isset( $_POST[ self::NONCE ] ) || ! wp_verify_nonce( sanitize_key( $_POST[ self::NONCE ] ), self::NONCE ) ) {
			return;
		}
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}
		if ( ! current_user_can( 'edit_post', $post_id ) || Ribbons::POST_TYPE !== $post->post_type ) {
			return;
		}

		$text = isset( $_POST['rynkowski_text'] ) ? sanitize_text_field( wp_unslash( $_POST['rynkowski_text'] ) ) : '';
		update_post_meta( $post_id, Ribbons::META_TEXT, $text );

		foreach ( array(
			'rynkowski_text_color' => Ribbons::META_TEXT_COLOR,
			'rynkowski_bg_color'   => Ribbons::META_BG_COLOR,
		) as $field => $meta_key ) {
			$color = isset( $_POST[ $field ] ) ? sanitize_hex_color( wp_unslash( $_POST[ $field ] ) ) : '';
			update_post_meta( $post_id, $meta_key, (string) $color );
		}

		update_post_meta( $post_id, Ribbons::META_ACTIVE, isset( $_POST['rynkowski_active'] ) ? 'yes' : 'no' );

		// Zapisujemy tylko ID, które naprawdę są produktem albo terminem właściwej taksonomii.
		foreach ( array(
			'rynkowski_products'   => array( Ribbons::META_PRODUCTS, null ),
			'rynkowski_categories' => array( Ribbons::META_CATEGORIES, 'product_cat' ),
			'rynkowski_tags'       => array( Ribbons::META_TAGS, 'product_tag' ),
		) as $field => list( $meta_key, $taxonomy ) ) {
			$ids = isset( $_POST[ $field ] ) ? array_map( 'absint', (array) wp_unslash( $_POST[ $field ] ) ) : array();
			$ids = array_filter(
				array_unique( array_filter( $ids ) ),
				static function ( int $id ) use ( $taxonomy ): bool {
					return null === $taxonomy
						? 'product' === get_post_type( $id )
						: get_term( $id, $taxonomy ) instanceof \WP_Term;
				}
			);
			update_post_meta( $post_id, $meta_key, array_values( $ids ) );
		}
	}

	/**
	 * Ładuje pola WooCommerce i próbnik kolorów tylko na ekranach wstążek.
	 */
	public static function enqueue(): void {
		$screen = get_current_screen();
		if ( ! $screen || Ribbons::POST_TYPE !== $screen->post_type ) {
			return;
		}

		wp_enqueue_style( 'woocommerce_admin_styles' );
		wp_enqueue_style( 'wp-color-picker' );
		wp_enqueue_script( 'wc-enhanced-select' );
		wp_enqueue_script( 'wp-color-picker' );
		wp_add_inline_script( 'wp-color-picker', 'jQuery(function($){$(".rynkowski-color").wpColorPicker();});' );
		wp_add_inline_style( 'wp-color-picker', self::preview_css() );
	}

	/**
	 * Kolumny listy wstążek.
	 *
	 * @param array<string, string> $columns Domyślne kolumny.
	 * @return array<string, string>
	 */
	public static function columns( array $columns ): array {
		return array(
			'cb'                 => $columns['cb'] ?? '',
			'title'              => __( 'Nazwa', 'marcin-rynkowski-narzedzia' ),
			'rynkowski_preview'  => __( 'Wstążka', 'marcin-rynkowski-narzedzia' ),
			'rynkowski_active'   => __( 'Aktywna', 'marcin-rynkowski-narzedzia' ),
			'rynkowski_assigned' => __( 'Przypisanie', 'marcin-rynkowski-narzedzia' ),
		);
	}

	/**
	 * Zawartość kolumn listy wstążek.
	 *
	 * @param string $column  Kolumna.
	 * @param int    $post_id ID wstążki.
	 */
	public static function render_column( string $column, int $post_id ): void {
		$ribbon = Ribbons::get( $post_id );

		switch ( $column ) {
			case 'rynkowski_preview':
				echo self::preview( $ribbon ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escapowane w preview().
				break;

			case 'rynkowski_active':
				echo $ribbon['active'] ? esc_html__( 'Tak', 'marcin-rynkowski-narzedzia' ) : '—';
				break;

			case 'rynkowski_assigned':
				echo esc_html(
					sprintf(
						/* translators: 1: liczba produktów, 2: liczba kategorii, 3: liczba tagów */
						__( 'produkty: %1$d, kategorie: %2$d, tagi: %3$d', 'marcin-rynkowski-narzedzia' ),
						count( $ribbon['products'] ),
						count( $ribbon['categories'] ),
						count( $ribbon['tags'] )
					)
				);
				break;
		}
	}

	/**
	 * Podgląd wstążki w panelu.
	 *
	 * @param array{text: string, text_color: string, bg_color: string} $ribbon Dane wstążki.
	 */
	public static function preview( array $ribbon ): string {
		$style = '';
		if ( $ribbon['text_color'] ) {
			$style .= 'color:' . $ribbon['text_color'] . ';';
		}
		if ( $ribbon['bg_color'] ) {
			$style .= 'background-color:' . $ribbon['bg_color'] . ';';
		}

		return sprintf(
			'<span class="rynkowski-wstazka-podglad" style="%s">%s</span>',
			esc_attr( $style ),
			esc_html( $ribbon['text'] )
		);
	}

	/**
	 * Style podglądu wstążki w panelu.
	 */
	public static function preview_css(): string {
		return '.rynkowski-wstazka-podglad{display:inline-block;padding:3px 10px;border-radius:3px;background:#2271b1;color:#fff;font-size:12px;font-weight:600;line-height:1.6;}';
	}

	/**
	 * Podpowiedź w polu tytułu.
	 *
	 * @param string   $placeholder Domyślny tekst.
	 * @param \WP_Post $post        Edytowany wpis.
	 */
	public static function title_placeholder( string $placeholder, \WP_Post $post ): string {
		return Ribbons::POST_TYPE === $post->post_type ? __( 'Nazwa wewnętrzna, np. Black Friday 2026', 'marcin-rynkowski-narzedzia' ) : $placeholder;
	}
}
