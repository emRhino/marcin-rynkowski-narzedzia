<?php
/**
 * Wstążki: zakładka w edycji produktu.
 *
 * @package Rynkowski\Narzedzia
 */

namespace Rynkowski\Narzedzia;

defined( 'ABSPATH' ) || exit;

/**
 * Zakładka „Wstążki” w danych produktu, tylko do odczytu.
 */
final class Ribbon_Product_Tab {

	/**
	 * Podpina zakładkę.
	 */
	public static function register(): void {
		add_filter( 'woocommerce_product_data_tabs', array( self::class, 'add_tab' ) );
		add_action( 'woocommerce_product_data_panels', array( self::class, 'render_panel' ) );
		add_action( 'admin_enqueue_scripts', array( self::class, 'enqueue' ) );
	}

	/**
	 * Dodaje zakładkę do danych produktu.
	 *
	 * @param array<string, array<string, mixed>> $tabs Zakładki.
	 * @return array<string, array<string, mixed>>
	 */
	public static function add_tab( array $tabs ): array {
		$tabs['rynkowski_wstazki'] = array(
			'label'    => __( 'Wstążki', 'marcin-rynkowski-narzedzia' ),
			'target'   => 'rynkowski_wstazki_panel',
			'class'    => array(),
			'priority' => 85,
		);

		return $tabs;
	}

	/**
	 * Lista wstążek obejmujących produkt.
	 */
	public static function render_panel(): void {
		global $post;

		$items = $post instanceof \WP_Post ? Ribbons::for_product( $post->ID, false ) : array();
		?>
		<div id="rynkowski_wstazki_panel" class="panel woocommerce_options_panel hidden">
			<div class="options_group" style="padding: 12px;">
				<?php if ( ! $items ) : ?>
					<p><?php esc_html_e( 'Ten produkt nie ma przypisanych wstążek.', 'marcin-rynkowski-narzedzia' ); ?></p>
				<?php else : ?>
					<table class="widefat striped">
						<thead>
							<tr>
								<th><?php esc_html_e( 'Wstążka', 'marcin-rynkowski-narzedzia' ); ?></th>
								<th><?php esc_html_e( 'Przypisana przez', 'marcin-rynkowski-narzedzia' ); ?></th>
								<th><?php esc_html_e( 'Aktywna', 'marcin-rynkowski-narzedzia' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ( $items as $item ) : ?>
								<tr>
									<td>
										<?php echo Ribbon_Admin::preview( $item['ribbon'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escapowane w preview(). ?>
										<br>
										<a href="<?php echo esc_url( (string) get_edit_post_link( $item['ribbon']['id'] ) ); ?>"><?php echo esc_html( $item['ribbon']['name'] ); ?></a>
									</td>
									<td><?php echo esc_html( implode( ', ', $item['reasons'] ) ); ?></td>
									<td><?php echo $item['ribbon']['active'] ? esc_html__( 'Tak', 'marcin-rynkowski-narzedzia' ) : esc_html__( 'Nie', 'marcin-rynkowski-narzedzia' ); ?></td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
				<?php endif; ?>
				<p class="description"><?php esc_html_e( 'Wstążki przypisujesz w WooCommerce → Wstążki.', 'marcin-rynkowski-narzedzia' ); ?></p>
			</div>
		</div>
		<?php
	}

	/**
	 * Style podglądu na ekranie produktu.
	 */
	public static function enqueue(): void {
		$screen = get_current_screen();
		if ( ! $screen || 'product' !== $screen->id ) {
			return;
		}

		wp_register_style( 'rynkowski-wstazki-admin', false, array(), VERSION );
		wp_enqueue_style( 'rynkowski-wstazki-admin' );
		wp_add_inline_style( 'rynkowski-wstazki-admin', Ribbon_Admin::preview_css() );
	}
}
