<?php
/**
 * Kafle promocyjne: ekran edycji i lista w panelu.
 *
 * @package Rynkowski\Narzedzia
 */

namespace Rynkowski\Narzedzia;

defined( 'ABSPATH' ) || exit;

/**
 * Pola kafla, zapis i kolumny listy.
 */
final class Tile_Admin {

	const NONCE = 'rynkowski_kafel_nonce';

	/**
	 * Podpina akcje panelu.
	 */
	public static function register(): void {
		add_action( 'add_meta_boxes_' . Tiles::POST_TYPE, array( self::class, 'add_meta_boxes' ) );
		add_action( 'save_post_' . Tiles::POST_TYPE, array( self::class, 'save' ), 10, 2 );
		add_action( 'admin_enqueue_scripts', array( self::class, 'enqueue' ) );
		add_filter( 'manage_' . Tiles::POST_TYPE . '_posts_columns', array( self::class, 'columns' ) );
		add_action( 'manage_' . Tiles::POST_TYPE . '_posts_custom_column', array( self::class, 'render_column' ), 10, 2 );
	}

	/**
	 * Dodaje pola kafla.
	 */
	public static function add_meta_boxes(): void {
		add_meta_box( 'rynkowski-kafel-tresc', __( 'Treść kafla', 'marcin-rynkowski-narzedzia' ), array( self::class, 'render_content' ), Tiles::POST_TYPE, 'normal', 'high' );
		add_meta_box( 'rynkowski-kafel-wyswietlanie', __( 'Gdzie i kiedy', 'marcin-rynkowski-narzedzia' ), array( self::class, 'render_display' ), Tiles::POST_TYPE, 'normal', 'default' );
	}

	/**
	 * Pola treści: typ, link i pola zależne od typu.
	 *
	 * @param \WP_Post $post Edytowany kafel.
	 */
	public static function render_content( \WP_Post $post ): void {
		$tile  = Tiles::get( $post->ID );
		$types = array(
			'image' => __( 'Grafika', 'marcin-rynkowski-narzedzia' ),
			'video' => __( 'Wideo', 'marcin-rynkowski-narzedzia' ),
			'text'  => __( 'Tekst na kolorowym tle', 'marcin-rynkowski-narzedzia' ),
		);

		wp_nonce_field( self::NONCE, self::NONCE );
		?>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><?php esc_html_e( 'Typ', 'marcin-rynkowski-narzedzia' ); ?></th>
				<td>
					<fieldset>
						<?php foreach ( $types as $value => $label ) : ?>
							<label style="margin-right: 16px;">
								<input type="radio" name="rynkowski_type" value="<?php echo esc_attr( $value ); ?>" class="rynkowski-kafel-typ" <?php checked( $tile['type'], $value ); ?>>
								<?php echo esc_html( $label ); ?>
							</label>
						<?php endforeach; ?>
					</fieldset>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="rynkowski-link"><?php esc_html_e( 'Link', 'marcin-rynkowski-narzedzia' ); ?></label></th>
				<td>
					<input type="url" id="rynkowski-link" name="rynkowski_link" class="regular-text" value="<?php echo esc_attr( $tile['link'] ); ?>" placeholder="https://">
					<p class="description"><?php esc_html_e( 'Opcjonalny. Po kliknięciu w kafel klient trafi pod ten adres.', 'marcin-rynkowski-narzedzia' ); ?></p>
				</td>
			</tr>

			<?php self::render_media_row( 'image', 'rynkowski_image_id', __( 'Grafika', 'marcin-rynkowski-narzedzia' ), $tile['image_id'], 'image', __( 'Tekst alternatywny ustawiasz w bibliotece mediów.', 'marcin-rynkowski-narzedzia' ) ); ?>

			<?php self::render_media_row( 'video', 'rynkowski_video_id', __( 'Plik wideo (MP4)', 'marcin-rynkowski-narzedzia' ), $tile['video_id'], 'video/mp4', __( 'Odtwarzany bez dźwięku, w pętli. Klient może go zatrzymać.', 'marcin-rynkowski-narzedzia' ) ); ?>
			<?php self::render_media_row( 'video', 'rynkowski_poster_id', __( 'Obrazek zastępczy', 'marcin-rynkowski-narzedzia' ), $tile['poster_id'], 'image', __( 'Widoczny, zanim wideo się wczyta.', 'marcin-rynkowski-narzedzia' ) ); ?>

			<tr class="rynkowski-kafel-pole" data-typ="text">
				<th scope="row"><label for="rynkowski-heading"><?php esc_html_e( 'Nagłówek', 'marcin-rynkowski-narzedzia' ); ?></label></th>
				<td><input type="text" id="rynkowski-heading" name="rynkowski_heading" class="regular-text" value="<?php echo esc_attr( $tile['heading'] ); ?>"></td>
			</tr>
			<tr class="rynkowski-kafel-pole" data-typ="text">
				<th scope="row"><label for="rynkowski-description"><?php esc_html_e( 'Opis', 'marcin-rynkowski-narzedzia' ); ?></label></th>
				<td><textarea id="rynkowski-description" name="rynkowski_description" class="large-text" rows="3"><?php echo esc_textarea( $tile['description'] ); ?></textarea></td>
			</tr>
			<tr class="rynkowski-kafel-pole" data-typ="text">
				<th scope="row"><label for="rynkowski-button"><?php esc_html_e( 'Tekst przycisku', 'marcin-rynkowski-narzedzia' ); ?></label></th>
				<td>
					<input type="text" id="rynkowski-button" name="rynkowski_button" class="regular-text" value="<?php echo esc_attr( $tile['button'] ); ?>">
					<p class="description"><?php esc_html_e( 'Przycisk pokazuje się tylko, gdy kafel ma link.', 'marcin-rynkowski-narzedzia' ); ?></p>
				</td>
			</tr>
			<tr class="rynkowski-kafel-pole" data-typ="text">
				<th scope="row"><label for="rynkowski-bg-color"><?php esc_html_e( 'Kolor tła', 'marcin-rynkowski-narzedzia' ); ?></label></th>
				<td><input type="text" id="rynkowski-bg-color" name="rynkowski_bg_color" class="rynkowski-color" value="<?php echo esc_attr( $tile['bg_color'] ); ?>"></td>
			</tr>
			<tr class="rynkowski-kafel-pole" data-typ="text">
				<th scope="row"><label for="rynkowski-text-color"><?php esc_html_e( 'Kolor tekstu', 'marcin-rynkowski-narzedzia' ); ?></label></th>
				<td><input type="text" id="rynkowski-text-color" name="rynkowski_text_color" class="rynkowski-color" value="<?php echo esc_attr( $tile['text_color'] ); ?>"></td>
			</tr>
		</table>
		<?php
	}

	/**
	 * Wiersz z wyborem pliku z biblioteki mediów.
	 *
	 * @param string $type        Typ kafla, dla którego wiersz jest widoczny.
	 * @param string $name        Nazwa pola.
	 * @param string $label       Etykieta.
	 * @param int    $value       ID załącznika.
	 * @param string $library     Filtr biblioteki: „image” albo typ MIME.
	 * @param string $description Podpowiedź pod polem.
	 */
	private static function render_media_row( string $type, string $name, string $label, int $value, string $library, string $description ): void {
		$id   = str_replace( '_', '-', $name );
		$file = $value ? get_the_title( $value ) : '';
		?>
		<tr class="rynkowski-kafel-pole" data-typ="<?php echo esc_attr( $type ); ?>">
			<th scope="row"><label for="<?php echo esc_attr( $id ); ?>-wybierz"><?php echo esc_html( $label ); ?></label></th>
			<td class="rynkowski-media" data-library="<?php echo esc_attr( $library ); ?>">
				<input type="hidden" name="<?php echo esc_attr( $name ); ?>" value="<?php echo esc_attr( (string) ( $value ? $value : '' ) ); ?>" class="rynkowski-media-id">
				<div class="rynkowski-media-podglad">
					<?php
					if ( $value && wp_attachment_is_image( $value ) ) {
						echo wp_get_attachment_image( $value, 'thumbnail' );
					} elseif ( $file ) {
						echo '<code>' . esc_html( $file ) . '</code>';
					}
					?>
				</div>
				<button type="button" class="button rynkowski-media-wybierz" id="<?php echo esc_attr( $id ); ?>-wybierz"><?php esc_html_e( 'Wybierz z biblioteki', 'marcin-rynkowski-narzedzia' ); ?></button>
				<button type="button" class="button-link rynkowski-media-usun"><?php esc_html_e( 'Usuń', 'marcin-rynkowski-narzedzia' ); ?></button>
				<p class="description"><?php echo esc_html( $description ); ?></p>
			</td>
		</tr>
		<?php
	}

	/**
	 * Pola wyświetlania: aktywność, miejsca, pozycja.
	 *
	 * @param \WP_Post $post Edytowany kafel.
	 */
	public static function render_display( \WP_Post $post ): void {
		$tile = Tiles::get( $post->ID );
		?>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><?php esc_html_e( 'Aktywny', 'marcin-rynkowski-narzedzia' ); ?></th>
				<td>
					<label>
						<input type="checkbox" name="rynkowski_active" value="yes" <?php checked( $tile['active'] ); ?>>
						<?php esc_html_e( 'Pokazuj kafel na wybranych listach produktów', 'marcin-rynkowski-narzedzia' ); ?>
					</label>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Strona sklepu', 'marcin-rynkowski-narzedzia' ); ?></th>
				<td>
					<label>
						<input type="checkbox" name="rynkowski_on_shop" value="yes" <?php checked( $tile['on_shop'] ); ?>>
						<?php esc_html_e( 'Pokazuj na głównej stronie sklepu', 'marcin-rynkowski-narzedzia' ); ?>
					</label>
				</td>
			</tr>
			<?php
			Ribbon_Admin::render_term_select( 'product_cat', 'rynkowski_categories', __( 'Kategorie', 'marcin-rynkowski-narzedzia' ), $tile['categories'] );
			Ribbon_Admin::render_term_select( 'product_tag', 'rynkowski_tags', __( 'Tagi', 'marcin-rynkowski-narzedzia' ), $tile['tags'] );
			?>
			<tr>
				<th scope="row"><label for="rynkowski-position"><?php esc_html_e( 'Pozycja', 'marcin-rynkowski-narzedzia' ); ?></label></th>
				<td>
					<input type="number" id="rynkowski-position" name="rynkowski_position" min="1" step="1" class="small-text" value="<?php echo esc_attr( (string) $tile['position'] ); ?>">
					<p class="description"><?php esc_html_e( 'Który element listy, licząc od lewej i od góry. Np. 4 = czwarte miejsce.', 'marcin-rynkowski-narzedzia' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Strona listy', 'marcin-rynkowski-narzedzia' ); ?></th>
				<td>
					<label>
						<input type="checkbox" name="rynkowski_first_page_only" value="yes" <?php checked( $tile['first_page_only'] ); ?>>
						<?php esc_html_e( 'Tylko pierwsza strona', 'marcin-rynkowski-narzedzia' ); ?>
					</label>
				</td>
			</tr>
		</table>
		<?php
	}

	/**
	 * Zapisuje pola kafla.
	 *
	 * @param int      $post_id ID kafla.
	 * @param \WP_Post $post    Kafel.
	 */
	public static function save( int $post_id, \WP_Post $post ): void {
		if ( ! isset( $_POST[ self::NONCE ] ) || ! wp_verify_nonce( sanitize_key( $_POST[ self::NONCE ] ), self::NONCE ) ) {
			return;
		}
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}
		if ( ! current_user_can( 'edit_post', $post_id ) || Tiles::POST_TYPE !== $post->post_type ) {
			return;
		}

		$type = isset( $_POST['rynkowski_type'] ) ? sanitize_key( $_POST['rynkowski_type'] ) : 'image';
		update_post_meta( $post_id, Tiles::META_TYPE, in_array( $type, Tiles::TYPES, true ) ? $type : 'image' );

		$link = isset( $_POST['rynkowski_link'] ) ? esc_url_raw( wp_unslash( $_POST['rynkowski_link'] ), array( 'http', 'https' ) ) : '';
		update_post_meta( $post_id, Tiles::META_LINK, $link );

		foreach ( array(
			'rynkowski_active'          => Tiles::META_ACTIVE,
			'rynkowski_on_shop'         => Tiles::META_ON_SHOP,
			'rynkowski_first_page_only' => Tiles::META_FIRST_PAGE_ONLY,
		) as $field => $meta_key ) {
			update_post_meta( $post_id, $meta_key, isset( $_POST[ $field ] ) ? 'yes' : 'no' );
		}

		$position = isset( $_POST['rynkowski_position'] ) ? absint( $_POST['rynkowski_position'] ) : 1;
		update_post_meta( $post_id, Tiles::META_POSITION, max( 1, $position ) );

		// Tylko ID terminów właściwej taksonomii.
		foreach ( array(
			'rynkowski_categories' => array( Tiles::META_CATEGORIES, 'product_cat' ),
			'rynkowski_tags'       => array( Tiles::META_TAGS, 'product_tag' ),
		) as $field => list( $meta_key, $taxonomy ) ) {
			$ids = isset( $_POST[ $field ] ) ? array_map( 'absint', (array) wp_unslash( $_POST[ $field ] ) ) : array();
			$ids = array_filter(
				array_unique( array_filter( $ids ) ),
				static function ( int $id ) use ( $taxonomy ): bool {
					return get_term( $id, $taxonomy ) instanceof \WP_Term;
				}
			);
			update_post_meta( $post_id, $meta_key, array_values( $ids ) );
		}

		// Tylko załączniki właściwego rodzaju: obrazy albo MP4.
		foreach ( array(
			'rynkowski_image_id'  => array( Tiles::META_IMAGE_ID, 'image' ),
			'rynkowski_poster_id' => array( Tiles::META_POSTER_ID, 'image' ),
			'rynkowski_video_id'  => array( Tiles::META_VIDEO_ID, 'video/mp4' ),
		) as $field => list( $meta_key, $kind ) ) {
			$id = isset( $_POST[ $field ] ) ? absint( $_POST[ $field ] ) : 0;
			if ( $id && ! self::is_valid_attachment( $id, $kind ) ) {
				$id = 0;
			}
			update_post_meta( $post_id, $meta_key, $id );
		}

		foreach ( array(
			'rynkowski_heading' => Tiles::META_HEADING,
			'rynkowski_button'  => Tiles::META_BUTTON,
		) as $field => $meta_key ) {
			$value = isset( $_POST[ $field ] ) ? sanitize_text_field( wp_unslash( $_POST[ $field ] ) ) : '';
			update_post_meta( $post_id, $meta_key, $value );
		}

		$description = isset( $_POST['rynkowski_description'] ) ? sanitize_textarea_field( wp_unslash( $_POST['rynkowski_description'] ) ) : '';
		update_post_meta( $post_id, Tiles::META_DESCRIPTION, $description );

		foreach ( array(
			'rynkowski_bg_color'   => Tiles::META_BG_COLOR,
			'rynkowski_text_color' => Tiles::META_TEXT_COLOR,
		) as $field => $meta_key ) {
			$color = isset( $_POST[ $field ] ) ? sanitize_hex_color( wp_unslash( $_POST[ $field ] ) ) : '';
			update_post_meta( $post_id, $meta_key, (string) $color );
		}
	}

	/**
	 * Sprawdza, czy załącznik jest obrazem albo plikiem o podanym typie MIME.
	 *
	 * @param int    $id   ID załącznika.
	 * @param string $kind „image” albo typ MIME.
	 */
	private static function is_valid_attachment( int $id, string $kind ): bool {
		if ( 'attachment' !== get_post_type( $id ) ) {
			return false;
		}

		return 'image' === $kind ? wp_attachment_is_image( $id ) : get_post_mime_type( $id ) === $kind;
	}

	/**
	 * Ładuje bibliotekę mediów, próbnik kolorów i skrypt pól na ekranie kafla.
	 */
	public static function enqueue(): void {
		$screen = get_current_screen();
		if ( ! $screen || Tiles::POST_TYPE !== $screen->post_type ) {
			return;
		}

		wp_enqueue_style( 'woocommerce_admin_styles' );
		wp_enqueue_style( 'wp-color-picker' );
		wp_enqueue_script( 'wc-enhanced-select' );

		if ( 'post' === $screen->base ) {
			wp_enqueue_media();
			wp_enqueue_script( 'rynkowski-kafle-admin', plugins_url( 'assets/js/kafle-admin.js', FILE ), array( 'jquery', 'wp-color-picker' ), VERSION, true );
			wp_localize_script(
				'rynkowski-kafle-admin',
				'rynkowskiKafle',
				array(
					'wybierz' => __( 'Wybierz plik', 'marcin-rynkowski-narzedzia' ),
					'uzyj'    => __( 'Użyj tego pliku', 'marcin-rynkowski-narzedzia' ),
				)
			);
		}
	}

	/**
	 * Kolumny listy kafli.
	 *
	 * @param array<string, string> $columns Domyślne kolumny.
	 * @return array<string, string>
	 */
	public static function columns( array $columns ): array {
		return array(
			'cb'                 => $columns['cb'] ?? '',
			'title'              => __( 'Nazwa', 'marcin-rynkowski-narzedzia' ),
			'rynkowski_type'     => __( 'Typ', 'marcin-rynkowski-narzedzia' ),
			'rynkowski_where'    => __( 'Gdzie', 'marcin-rynkowski-narzedzia' ),
			'rynkowski_position' => __( 'Pozycja', 'marcin-rynkowski-narzedzia' ),
			'rynkowski_active'   => __( 'Aktywny', 'marcin-rynkowski-narzedzia' ),
		);
	}

	/**
	 * Zawartość kolumn listy kafli.
	 *
	 * @param string $column  Kolumna.
	 * @param int    $post_id ID kafla.
	 */
	public static function render_column( string $column, int $post_id ): void {
		$tile = Tiles::get( $post_id );

		switch ( $column ) {
			case 'rynkowski_type':
				$labels = array(
					'image' => __( 'Grafika', 'marcin-rynkowski-narzedzia' ),
					'video' => __( 'Wideo', 'marcin-rynkowski-narzedzia' ),
					'text'  => __( 'Tekst', 'marcin-rynkowski-narzedzia' ),
				);
				echo esc_html( $labels[ $tile['type'] ] ?? '' );
				break;

			case 'rynkowski_where':
				$parts = array();
				if ( $tile['on_shop'] ) {
					$parts[] = __( 'sklep', 'marcin-rynkowski-narzedzia' );
				}
				if ( $tile['categories'] ) {
					/* translators: %d: liczba kategorii */
					$parts[] = sprintf( __( 'kategorie: %d', 'marcin-rynkowski-narzedzia' ), count( $tile['categories'] ) );
				}
				if ( $tile['tags'] ) {
					/* translators: %d: liczba tagów */
					$parts[] = sprintf( __( 'tagi: %d', 'marcin-rynkowski-narzedzia' ), count( $tile['tags'] ) );
				}
				echo $parts ? esc_html( implode( ', ', $parts ) ) : '—';
				break;

			case 'rynkowski_position':
				echo esc_html( (string) $tile['position'] );
				if ( $tile['first_page_only'] ) {
					echo ' <span class="description">' . esc_html__( '(1. strona)', 'marcin-rynkowski-narzedzia' ) . '</span>';
				}
				break;

			case 'rynkowski_active':
				echo $tile['active'] ? esc_html__( 'Tak', 'marcin-rynkowski-narzedzia' ) : '—';
				break;
		}
	}
}
