<?php
/**
 * Informacje o zakupie: ekran edycji wiersza i lista w panelu.
 *
 * @package Rynkowski\Narzedzia
 */

namespace Rynkowski\Narzedzia\Moduly\Informacje;

use Rynkowski\Narzedzia\Admin_Screens;
use Rynkowski\Narzedzia\Fields;
use Rynkowski\Narzedzia\Plugin;

defined( 'ABSPATH' ) || exit;

/**
 * Pola wiersza (ikona, tekst domyślny, aktywność), zapis, kolumny listy i wspólny edytor podpisu.
 */
final class Info_Row_Admin {

	const NONCE = 'rynkowski_informacja_nonce';

	/**
	 * Podpina akcje panelu.
	 */
	public static function register(): void {
		Admin_Screens::register( Info_Rows::POST_TYPE );
		add_action( 'add_meta_boxes_' . Info_Rows::POST_TYPE, array( self::class, 'add_meta_boxes' ) );
		add_action( 'save_post_' . Info_Rows::POST_TYPE, array( self::class, 'save' ), 10, 2 );
		add_filter( 'manage_' . Info_Rows::POST_TYPE . '_posts_columns', array( self::class, 'columns' ) );
		add_action( 'manage_' . Info_Rows::POST_TYPE . '_posts_custom_column', array( self::class, 'render_column' ), 10, 2 );
		add_action( 'admin_enqueue_scripts', array( self::class, 'enqueue' ) );
	}

	/**
	 * Dodaje pola wiersza.
	 */
	public static function add_meta_boxes(): void {
		add_meta_box( 'rynkowski-informacja', __( 'Wiersz', 'marcin-rynkowski-narzedzia' ), array( self::class, 'render' ), Info_Rows::POST_TYPE, 'normal', 'high' );
	}

	/**
	 * Pola wiersza.
	 *
	 * @param \WP_Post $post Edytowany wiersz.
	 */
	public static function render( \WP_Post $post ): void {
		$row = Info_Rows::get( $post->ID );

		wp_nonce_field( self::NONCE, self::NONCE );
		?>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row" class="titledesc"><?php Fields::label( 'rynkowski-ikona-wybierz', __( 'Ikona', 'marcin-rynkowski-narzedzia' ), __( 'Obrazek z biblioteki mediów, najlepiej kwadratowy (PNG albo SVG, jeśli sklep pozwala je wgrywać).', 'marcin-rynkowski-narzedzia' ) ); ?></th>
				<td class="forminp rynkowski-media">
					<input type="hidden" name="rynkowski_ikona" value="<?php echo esc_attr( $row['icon_id'] ? (string) $row['icon_id'] : '' ); ?>" class="rynkowski-media-id">
					<div class="rynkowski-media-podglad">
						<?php
						if ( $row['icon_id'] ) {
							echo wp_get_attachment_image( $row['icon_id'], array( 48, 48 ) );
						}
						?>
					</div>
					<button type="button" class="button rynkowski-media-wybierz" id="rynkowski-ikona-wybierz"><?php esc_html_e( 'Wybierz z biblioteki', 'marcin-rynkowski-narzedzia' ); ?></button>
					<button type="button" class="button-link rynkowski-media-usun"><?php esc_html_e( 'Usuń', 'marcin-rynkowski-narzedzia' ); ?></button>
				</td>
			</tr>
			<tr>
				<th scope="row" class="titledesc"><?php Fields::label( 'rynkowski_informacja_tekst', __( 'Tekst domyślny', 'marcin-rynkowski-narzedzia' ), __( 'Pokazuje się na każdym produkcie, który nie ma własnego tekstu dla tego wiersza. Przyciski: b = pogrubienie, link, wyróżnij = kolor z ustawień, dopisek = mniejsza druga linia.', 'marcin-rynkowski-narzedzia' ) ); ?></th>
				<td class="forminp"><?php self::editor( $row['text'], 'rynkowski_informacja_tekst', 'rynkowski_tekst' ); ?></td>
			</tr>
			<tr>
				<th scope="row" class="titledesc"><?php Fields::label( 'rynkowski-aktywny', __( 'Aktywny', 'marcin-rynkowski-narzedzia' ), __( 'Wyłączony wiersz zachowuje treść, ale nie pokazuje się w sklepie ani w edycji produktu. Kolejność ustawiasz w polu „Kolejność”.', 'marcin-rynkowski-narzedzia' ) ); ?></th>
				<td class="forminp forminp-checkbox">
					<label>
						<input type="checkbox" id="rynkowski-aktywny" name="rynkowski_aktywny" value="yes" <?php checked( $row['active'] ); ?>>
						<?php esc_html_e( 'Pokazuj wiersz w sklepie', 'marcin-rynkowski-narzedzia' ); ?>
					</label>
				</td>
			</tr>
		</table>
		<?php
	}

	/**
	 * Edytor podpisu: tylko tryb tekstowy z przyciskami pogrubienia, linku, wyróżnienia i dopisku.
	 *
	 * @param string $content Treść.
	 * @param string $id      Id edytora (małe litery i podkreślenia).
	 * @param string $name    Nazwa pola formularza.
	 */
	public static function editor( string $content, string $id, string $name ): void {
		wp_editor(
			$content,
			$id,
			array(
				'textarea_name' => $name,
				'textarea_rows' => 3,
				'media_buttons' => false,
				'tinymce'       => false,
				'quicktags'     => array( 'buttons' => 'strong,link' ),
			)
		);

		// Własne przyciski tylko dla tego edytora (8. argument QTags.addButton), nie dla opisu produktu.
		wp_add_inline_script(
			'quicktags',
			sprintf(
				'QTags.addButton(%1$s,%2$s,"<mark>","</mark>","",%3$s,200,%4$s);QTags.addButton(%5$s,%6$s,"<small>","</small>","",%7$s,201,%4$s);',
				wp_json_encode( 'rynkowski_wyroznij_' . $id ),
				wp_json_encode( __( 'wyróżnij', 'marcin-rynkowski-narzedzia' ) ),
				wp_json_encode( __( 'Wyróżnij kolorem z ustawień', 'marcin-rynkowski-narzedzia' ) ),
				wp_json_encode( $id ),
				wp_json_encode( 'rynkowski_dopisek_' . $id ),
				wp_json_encode( __( 'dopisek', 'marcin-rynkowski-narzedzia' ) ),
				wp_json_encode( __( 'Mniejszy dopisek w drugiej linii', 'marcin-rynkowski-narzedzia' ) )
			)
		);
	}

	/**
	 * Zapisuje pola wiersza.
	 *
	 * @param int      $post_id ID wiersza.
	 * @param \WP_Post $post    Wiersz.
	 */
	public static function save( int $post_id, \WP_Post $post ): void {
		if ( ! isset( $_POST[ self::NONCE ] ) || ! wp_verify_nonce( sanitize_key( $_POST[ self::NONCE ] ), self::NONCE ) ) {
			return;
		}
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}
		if ( ! current_user_can( 'edit_post', $post_id ) || Info_Rows::POST_TYPE !== $post->post_type ) {
			return;
		}

		// Ikona tylko jako istniejący obrazek z biblioteki.
		$icon = isset( $_POST['rynkowski_ikona'] ) ? absint( $_POST['rynkowski_ikona'] ) : 0;
		update_post_meta( $post_id, Info_Rows::META_ICON, $icon && wp_attachment_is_image( $icon ) ? $icon : 0 );

		// Tekst czyszczony do dozwolonego HTML w Info_Rows::clean_text().
		$text = isset( $_POST['rynkowski_tekst'] ) && is_string( $_POST['rynkowski_tekst'] ) ? Info_Rows::clean_text( wp_unslash( $_POST['rynkowski_tekst'] ) ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		update_post_meta( $post_id, Info_Rows::META_TEXT, $text );

		update_post_meta( $post_id, Info_Rows::META_ACTIVE, isset( $_POST['rynkowski_aktywny'] ) ? 'yes' : 'no' );
	}

	/**
	 * Kolumny listy wierszy.
	 *
	 * @param array<string, string> $columns Domyślne kolumny.
	 * @return array<string, string>
	 */
	public static function columns( array $columns ): array {
		return array(
			'cb'                  => $columns['cb'] ?? '',
			'rynkowski_ikona'     => __( 'Ikona', 'marcin-rynkowski-narzedzia' ),
			'title'               => __( 'Nazwa', 'marcin-rynkowski-narzedzia' ),
			'rynkowski_tekst'     => __( 'Tekst domyślny', 'marcin-rynkowski-narzedzia' ),
			'rynkowski_kolejnosc' => __( 'Kolejność', 'marcin-rynkowski-narzedzia' ),
			'rynkowski_stan'      => __( 'Stan', 'marcin-rynkowski-narzedzia' ),
		);
	}

	/**
	 * Zawartość kolumn listy wierszy.
	 *
	 * @param string $column  Kolumna.
	 * @param int    $post_id ID wiersza.
	 */
	public static function render_column( string $column, int $post_id ): void {
		$row = Info_Rows::get( $post_id );

		switch ( $column ) {
			case 'rynkowski_ikona':
				echo $row['icon_id'] ? wp_get_attachment_image( $row['icon_id'], array( 24, 24 ) ) : '—';
				break;

			case 'rynkowski_tekst':
				echo wp_kses( $row['text'], Info_Rows::allowed_html() );
				break;

			case 'rynkowski_kolejnosc':
				echo esc_html( (string) $row['order'] );
				break;

			case 'rynkowski_stan':
				echo $row['active'] ? esc_html__( 'Aktywny', 'marcin-rynkowski-narzedzia' ) : esc_html__( 'Wyłączony', 'marcin-rynkowski-narzedzia' );
				break;
		}
	}

	/**
	 * Biblioteka mediów i skrypt wyboru ikony na ekranie wiersza.
	 */
	public static function enqueue(): void {
		$screen = get_current_screen();
		if ( ! $screen || Info_Rows::POST_TYPE !== $screen->post_type || 'post' !== $screen->base ) {
			return;
		}

		wp_enqueue_media();
		wp_enqueue_script( 'rynkowski-informacje-admin', plugins_url( 'assets/informacje-admin.js', dirname( __DIR__ ) . '/module.php' ), array( 'jquery' ), Plugin::VERSION, true );
		wp_localize_script(
			'rynkowski-informacje-admin',
			'rynkowskiInformacje',
			array(
				'wybierz' => __( 'Wybierz ikonę', 'marcin-rynkowski-narzedzia' ),
				'uzyj'    => __( 'Użyj tej ikony', 'marcin-rynkowski-narzedzia' ),
			)
		);
	}
}
