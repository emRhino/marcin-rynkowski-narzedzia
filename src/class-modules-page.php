<?php
/**
 * Ekran włączania modułów.
 *
 * @package Rynkowski\Narzedzia
 */

namespace Rynkowski\Narzedzia;

defined( 'ABSPATH' ) || exit;

/**
 * WooCommerce → Narzędzia: przełączniki modułów i zgoda na usunięcie danych.
 */
final class Modules_Page {

	const SLUG   = 'rynkowski-narzedzia';
	const ACTION = 'rynkowski_narzedzia_zapisz';

	/**
	 * Tworzy ekran dla rejestru modułów.
	 *
	 * @param Registry $registry Rejestr modułów.
	 */
	public function __construct( private Registry $registry ) {}

	/**
	 * Podpina menu i zapis formularza.
	 */
	public function register(): void {
		add_action( 'admin_menu', array( $this, 'add_page' ), 99 );
		add_action( 'admin_post_' . self::ACTION, array( $this, 'save' ) );
	}

	/**
	 * Dodaje stronę do menu WooCommerce.
	 */
	public function add_page(): void {
		add_submenu_page(
			'woocommerce',
			__( 'Narzędzia Marcina Rynkowskiego', 'marcin-rynkowski-narzedzia' ),
			__( 'Narzędzia', 'marcin-rynkowski-narzedzia' ),
			'manage_woocommerce',
			self::SLUG,
			array( $this, 'render' )
		);
	}

	/**
	 * Formularz z listą modułów.
	 */
	public function render(): void {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}

		$missing = $this->registry->missing();
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Narzędzia', 'marcin-rynkowski-narzedzia' ); ?></h1>
			<?php
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- tylko komunikat po przekierowaniu, bez zmiany danych.
			if ( isset( $_GET['zapisano'] ) ) :
				?>
				<div class="notice notice-success"><p><?php esc_html_e( 'Zapisano ustawienia.', 'marcin-rynkowski-narzedzia' ); ?></p></div>
			<?php endif; ?>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="<?php echo esc_attr( self::ACTION ); ?>">
				<?php wp_nonce_field( self::ACTION ); ?>
				<table class="widefat striped">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Włączony', 'marcin-rynkowski-narzedzia' ); ?></th>
							<th><?php esc_html_e( 'Moduł', 'marcin-rynkowski-narzedzia' ); ?></th>
							<th><?php esc_html_e( 'Wymaga', 'marcin-rynkowski-narzedzia' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $this->registry->all() as $slug => $module ) : ?>
							<tr>
								<td>
									<input type="checkbox" id="rynkowski-modul-<?php echo esc_attr( $slug ); ?>" name="moduly[]" value="<?php echo esc_attr( $slug ); ?>" <?php checked( $this->registry->is_enabled( $slug ) ); ?>>
								</td>
								<td>
									<label for="rynkowski-modul-<?php echo esc_attr( $slug ); ?>"><strong><?php echo esc_html( $module->name() ); ?></strong></label>
									<p class="description"><?php echo esc_html( $module->description() ); ?></p>
									<?php if ( isset( $missing[ $slug ] ) ) : ?>
										<p style="color: #b32d2e;">
											<?php
											echo esc_html(
												sprintf(
													/* translators: %s: lista brakujących wtyczek lub motywu */
													__( 'Nie działa, brakuje: %s.', 'marcin-rynkowski-narzedzia' ),
													implode( ', ', $missing[ $slug ] )
												)
											);
											?>
										</p>
									<?php endif; ?>
								</td>
								<td>
									<?php
									echo esc_html(
										implode(
											', ',
											array_map(
												static function ( string $dependency ): string {
													return Dependencies::LABELS[ $dependency ] ?? $dependency;
												},
												$module->requires()
											)
										)
									);
									?>
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
				<p>
					<label>
						<input type="checkbox" name="usun_dane" value="yes" <?php checked( 'yes' === get_option( Plugin::OPTION_DELETE_DATA ) ); ?>>
						<?php esc_html_e( 'Usuń dane wszystkich modułów przy odinstalowaniu wtyczki', 'marcin-rynkowski-narzedzia' ); ?>
					</label>
				</p>
				<?php submit_button(); ?>
			</form>
		</div>
		<?php
	}

	/**
	 * Zapisuje formularz i wraca na stronę modułów.
	 */
	public function save(): void {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'Brak uprawnień.', 'marcin-rynkowski-narzedzia' ), '', array( 'response' => 403 ) );
		}

		check_admin_referer( self::ACTION );

		$slugs = isset( $_POST['moduly'] ) ? array_map( 'sanitize_key', (array) wp_unslash( $_POST['moduly'] ) ) : array();
		$this->registry->set_enabled( $slugs );
		update_option( Plugin::OPTION_DELETE_DATA, isset( $_POST['usun_dane'] ) ? 'yes' : 'no' );

		// admin-post.php kończy żądanie po tej akcji, więc exit nie jest potrzebny.
		wp_safe_redirect(
			add_query_arg(
				array(
					'page'     => self::SLUG,
					'zapisano' => '1',
				),
				admin_url( 'admin.php' )
			)
		);
	}
}
