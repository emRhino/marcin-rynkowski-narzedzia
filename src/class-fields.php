<?php
/**
 * Wspólne pola formularzy w panelu.
 *
 * @package Rynkowski\Narzedzia
 */

namespace Rynkowski\Narzedzia;

defined( 'ABSPATH' ) || exit;

/**
 * Pola i odczyt danych używane przez wiele modułów.
 */
final class Fields {

	/**
	 * Etykieta pola z dymkiem pomocy „?” jak w ustawieniach WooCommerce.
	 *
	 * @param string $id    Id pola, do którego prowadzi etykieta.
	 * @param string $label Etykieta.
	 * @param string $tip   Treść dymka; pusta = bez dymka.
	 */
	public static function label( string $id, string $label, string $tip = '' ): void {
		printf( '<label for="%s">%s</label>', esc_attr( $id ), esc_html( $label ) );

		if ( '' !== $tip ) {
			echo wc_help_tip( $tip ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- wc_help_tip() escapuje treść (esc_attr).
		}
	}

	/**
	 * Wiersz tabeli formularza z wielokrotnym wyborem terminów taksonomii.
	 *
	 * @param string $taxonomy Taksonomia.
	 * @param string $name     Nazwa pola.
	 * @param string $label    Etykieta.
	 * @param int[]  $selected Zaznaczone ID.
	 * @param string $tip      Treść dymka pomocy.
	 */
	public static function term_select( string $taxonomy, string $name, string $label, array $selected, string $tip = '' ): void {
		$terms = get_terms(
			array(
				'taxonomy'   => $taxonomy,
				'hide_empty' => false,
			)
		);
		$id    = 'rynkowski-' . $taxonomy;
		?>
		<tr>
			<th scope="row" class="titledesc"><?php self::label( $id, $label, $tip ); ?></th>
			<td class="forminp">
				<select id="<?php echo esc_attr( $id ); ?>" name="<?php echo esc_attr( $name ); ?>[]" class="wc-enhanced-select" multiple style="width: 100%;">
					<?php
					foreach ( is_array( $terms ) ? $terms : array() as $term ) {
						printf(
							'<option value="%d"%s>%s</option>',
							(int) $term->term_id,
							selected( in_array( (int) $term->term_id, $selected, true ), true, false ),
							esc_html( $term->name )
						);
					}
					?>
				</select>
			</td>
		</tr>
		<?php
	}

	/**
	 * Lista ID z wysłanego formularza, tylko te przyjęte przez walidator.
	 *
	 * Wołający musi wcześniej sprawdzić nonce i uprawnienia.
	 *
	 * @param string             $field     Nazwa pola w $_POST.
	 * @param callable(int):bool $validator Czy ID jest dopuszczalne.
	 * @return int[]
	 */
	public static function posted_ids( string $field, callable $validator ): array {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce sprawdza wołający.
		$raw = isset( $_POST[ $field ] ) ? (array) wp_unslash( $_POST[ $field ] ) : array();
		$ids = array_unique( array_filter( array_map( 'absint', $raw ) ) );

		return array_values( array_filter( $ids, $validator ) );
	}

	/**
	 * Walidator: ID jest terminem podanej taksonomii.
	 *
	 * @param string $taxonomy Taksonomia.
	 * @return callable(int):bool
	 */
	public static function is_term_of( string $taxonomy ): callable {
		return static function ( int $id ) use ( $taxonomy ): bool {
			return get_term( $id, $taxonomy ) instanceof \WP_Term;
		};
	}
}
