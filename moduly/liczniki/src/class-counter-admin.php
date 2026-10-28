<?php
/**
 * Liczniki promocji: ekran edycji i lista w panelu.
 *
 * @package Rynkowski\Narzedzia
 */

namespace Rynkowski\Narzedzia\Moduly\Liczniki;

use Rynkowski\Narzedzia\Admin_Screens;
use Rynkowski\Narzedzia\Fields;

defined( 'ABSPATH' ) || exit;

/**
 * Pola licznika, zapis i kolumny listy.
 */
final class Counter_Admin {

	const NONCE = 'rynkowski_licznik_nonce';

	// Format pola datetime-local; czas w strefie sklepu.
	const DATE_FORMAT = 'Y-m-d\TH:i';

	/**
	 * Podpina akcje panelu.
	 */
	public static function register(): void {
		Admin_Screens::register( Counters::POST_TYPE );
		add_action( 'add_meta_boxes_' . Counters::POST_TYPE, array( self::class, 'add_meta_boxes' ) );
		add_action( 'save_post_' . Counters::POST_TYPE, array( self::class, 'save' ), 10, 2 );
		add_filter( 'manage_' . Counters::POST_TYPE . '_posts_columns', array( self::class, 'columns' ) );
		add_action( 'manage_' . Counters::POST_TYPE . '_posts_custom_column', array( self::class, 'render_column' ), 10, 2 );
	}

	/**
	 * Dodaje pola licznika.
	 */
	public static function add_meta_boxes(): void {
		add_meta_box( 'rynkowski-licznik-promocja', __( 'Promocja', 'marcin-rynkowski-narzedzia' ), array( self::class, 'render_promo' ), Counters::POST_TYPE, 'normal', 'high' );
		add_meta_box( 'rynkowski-licznik-przypisanie', __( 'Przypisanie', 'marcin-rynkowski-narzedzia' ), array( self::class, 'render_assignment' ), Counters::POST_TYPE, 'normal', 'default' );
	}

	/**
	 * Pola promocji: tekst, koniec, aktywność.
	 *
	 * @param \WP_Post $post Edytowany licznik.
	 */
	public static function render_promo( \WP_Post $post ): void {
		$counter = Counters::get( $post->ID );

		wp_nonce_field( self::NONCE, self::NONCE );
		?>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row" class="titledesc"><?php Fields::label( 'rynkowski-text', __( 'Tekst nad licznikiem', 'marcin-rynkowski-narzedzia' ), __( 'Np. „-12% Z KODEM: FLASH”.', 'marcin-rynkowski-narzedzia' ) ); ?></th>
				<td class="forminp"><input type="text" id="rynkowski-text" name="rynkowski_text" value="<?php echo esc_attr( $counter['text'] ); ?>"></td>
			</tr>
			<tr>
				<th scope="row" class="titledesc"><?php Fields::label( 'rynkowski-end', __( 'Koniec promocji', 'marcin-rynkowski-narzedzia' ), __( 'Data i godzina w strefie czasowej sklepu. Po tym czasie licznik znika sam. Ustaw prawdziwy koniec promocji.', 'marcin-rynkowski-narzedzia' ) ); ?></th>
				<td class="forminp"><input type="datetime-local" id="rynkowski-end" name="rynkowski_end" value="<?php echo esc_attr( $counter['end'] ? wp_date( self::DATE_FORMAT, $counter['end'] ) : '' ); ?>" required></td>
			</tr>
			<tr>
				<th scope="row" class="titledesc"><?php Fields::label( 'rynkowski-active', __( 'Aktywny', 'marcin-rynkowski-narzedzia' ), __( 'Wyłączony licznik zachowuje ustawienia, ale nie pokazuje się w sklepie.', 'marcin-rynkowski-narzedzia' ) ); ?></th>
				<td class="forminp forminp-checkbox">
					<label>
						<input type="checkbox" id="rynkowski-active" name="rynkowski_active" value="yes" <?php checked( $counter['active'] ); ?>>
						<?php esc_html_e( 'Pokazuj licznik na przypisanych produktach', 'marcin-rynkowski-narzedzia' ); ?>
					</label>
				</td>
			</tr>
		</table>
		<?php
	}

	/**
	 * Pola przypisania: produkty, kategorie, tagi.
	 *
	 * @param \WP_Post $post Edytowany licznik.
	 */
	public static function render_assignment( \WP_Post $post ): void {
		$counter = Counters::get( $post->ID );
		?>
		<table class="form-table" role="presentation">
			<?php
			Fields::product_select( 'rynkowski_products', __( 'Produkty', 'marcin-rynkowski-narzedzia' ), $counter['products'], __( 'Licznik pokaże się na tych produktach.', 'marcin-rynkowski-narzedzia' ) );
			Fields::term_select( 'product_cat', 'rynkowski_categories', __( 'Kategorie', 'marcin-rynkowski-narzedzia' ), $counter['categories'], __( 'Licznik pokaże się na wszystkich produktach z tych kategorii.', 'marcin-rynkowski-narzedzia' ) );
			Fields::term_select( 'product_tag', 'rynkowski_tags', __( 'Tagi', 'marcin-rynkowski-narzedzia' ), $counter['tags'], __( 'Licznik pokaże się na wszystkich produktach z tymi tagami.', 'marcin-rynkowski-narzedzia' ) );
			?>
		</table>
		<?php
	}

	/**
	 * Zapisuje pola licznika.
	 *
	 * @param int      $post_id ID licznika.
	 * @param \WP_Post $post    Licznik.
	 */
	public static function save( int $post_id, \WP_Post $post ): void {
		if ( ! isset( $_POST[ self::NONCE ] ) || ! wp_verify_nonce( sanitize_key( $_POST[ self::NONCE ] ), self::NONCE ) ) {
			return;
		}
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}
		if ( ! current_user_can( 'edit_post', $post_id ) || Counters::POST_TYPE !== $post->post_type ) {
			return;
		}

		$text = isset( $_POST['rynkowski_text'] ) ? sanitize_text_field( wp_unslash( $_POST['rynkowski_text'] ) ) : '';
		update_post_meta( $post_id, Counters::META_TEXT, $text );

		$end = isset( $_POST['rynkowski_end'] ) ? sanitize_text_field( wp_unslash( $_POST['rynkowski_end'] ) ) : '';
		update_post_meta( $post_id, Counters::META_END, self::parse_end( $end ) );

		update_post_meta( $post_id, Counters::META_ACTIVE, isset( $_POST['rynkowski_active'] ) ? 'yes' : 'no' );

		// Zapisujemy tylko ID, które naprawdę są produktem albo terminem właściwej taksonomii.
		update_post_meta( $post_id, Counters::META_PRODUCTS, Fields::posted_ids( 'rynkowski_products', Fields::is_product() ) );
		update_post_meta( $post_id, Counters::META_CATEGORIES, Fields::posted_ids( 'rynkowski_categories', Fields::is_term_of( 'product_cat' ) ) );
		update_post_meta( $post_id, Counters::META_TAGS, Fields::posted_ids( 'rynkowski_tags', Fields::is_term_of( 'product_tag' ) ) );
	}

	/**
	 * Zamienia datę z pola datetime-local (strefa sklepu) na czas Unix; zła albo pusta = 0.
	 *
	 * @param string $value Wartość pola, np. 2026-11-30T23:59.
	 */
	public static function parse_end( string $value ): int {
		$date = \DateTimeImmutable::createFromFormat( '!' . self::DATE_FORMAT, $value, wp_timezone() );

		// Odrzuca też daty „przepełnione”, np. 2026-02-31, które PHP przesunąłby na marzec.
		if ( ! $date || $date->format( self::DATE_FORMAT ) !== $value ) {
			return 0;
		}

		return $date->getTimestamp();
	}

	/**
	 * Kolumny listy liczników.
	 *
	 * @param array<string, string> $columns Domyślne kolumny.
	 * @return array<string, string>
	 */
	public static function columns( array $columns ): array {
		return array(
			'cb'                 => $columns['cb'] ?? '',
			'title'              => __( 'Nazwa', 'marcin-rynkowski-narzedzia' ),
			'rynkowski_text'     => __( 'Tekst', 'marcin-rynkowski-narzedzia' ),
			'rynkowski_end'      => __( 'Koniec', 'marcin-rynkowski-narzedzia' ),
			'rynkowski_status'   => __( 'Stan', 'marcin-rynkowski-narzedzia' ),
			'rynkowski_assigned' => __( 'Przypisanie', 'marcin-rynkowski-narzedzia' ),
		);
	}

	/**
	 * Zawartość kolumn listy liczników.
	 *
	 * @param string $column  Kolumna.
	 * @param int    $post_id ID licznika.
	 */
	public static function render_column( string $column, int $post_id ): void {
		$counter = Counters::get( $post_id );

		switch ( $column ) {
			case 'rynkowski_text':
				echo esc_html( $counter['text'] );
				break;

			case 'rynkowski_end':
				echo $counter['end'] ? esc_html( wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $counter['end'] ) ) : '—';
				break;

			case 'rynkowski_status':
				echo esc_html( self::status( $counter ) );
				break;

			case 'rynkowski_assigned':
				echo esc_html(
					sprintf(
						/* translators: 1: liczba produktów, 2: liczba kategorii, 3: liczba tagów */
						__( 'produkty: %1$d, kategorie: %2$d, tagi: %3$d', 'marcin-rynkowski-narzedzia' ),
						count( $counter['products'] ),
						count( $counter['categories'] ),
						count( $counter['tags'] )
					)
				);
				break;
		}
	}

	/**
	 * Stan licznika słownie.
	 *
	 * @param array{active: bool, end: int} $counter Licznik.
	 */
	private static function status( array $counter ): string {
		if ( ! $counter['end'] ) {
			return __( 'Brak daty końca', 'marcin-rynkowski-narzedzia' );
		}
		if ( ! $counter['active'] ) {
			return __( 'Wyłączony', 'marcin-rynkowski-narzedzia' );
		}

		return $counter['end'] > time() ? __( 'Trwa', 'marcin-rynkowski-narzedzia' ) : __( 'Zakończony', 'marcin-rynkowski-narzedzia' );
	}
}
