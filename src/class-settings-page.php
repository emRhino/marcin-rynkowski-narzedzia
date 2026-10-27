<?php
/**
 * Narzędzia → Ustawienia: zakładki jak w ustawieniach WooCommerce.
 *
 * @package Rynkowski\Narzedzia
 */

namespace Rynkowski\Narzedzia;

defined( 'ABSPATH' ) || exit;

/**
 * Własne menu obok Blocksy z zakładkami: Moduły + jedna na każdy uruchomiony moduł z ustawieniami.
 *
 * Pola modułów rysuje WooCommerce (woocommerce_admin_fields), zapis i walidacja są nasze (Settings).
 *
 * @phpstan-import-type SettingsField from Has_Settings
 * @phpstan-import-type SettingsTab from Has_Settings
 */
final class Settings_Page {

	const SLUG        = 'rynkowski-narzedzia';
	const ACTION      = 'rynkowski_narzedzia_zapisz';
	const TAB_MODULES = 'moduly';
	const SCREEN_ID   = 'toplevel_page_rynkowski-narzedzia';
	const CAPABILITY  = 'manage_woocommerce';

	/**
	 * Tworzy ekran dla rejestru modułów.
	 *
	 * @param Registry $registry Rejestr modułów.
	 */
	public function __construct( private Registry $registry ) {}

	/**
	 * Podpina menu, zapis, style WooCommerce i pole koloru.
	 */
	public function register(): void {
		// Priorytet 9: menu istnieje, zanim WordPress dopnie pod nie listy typów wpisów.
		add_action( 'admin_menu', array( $this, 'add_page' ), 9 );
		add_action( 'admin_post_' . self::ACTION, array( $this, 'save' ) );
		add_filter( 'woocommerce_screen_ids', array( $this, 'screen_ids' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue' ) );
		add_action( 'woocommerce_admin_field_rynkowski_color', array( $this, 'render_color_field' ) );
	}

	/**
	 * Menu najwyższego poziomu zaraz pod Blocksy (pozycja 2) i pierwsza podstrona „Ustawienia”.
	 */
	public function add_page(): void {
		add_menu_page(
			__( 'Narzędzia', 'marcin-rynkowski-narzedzia' ),
			__( 'Narzędzia', 'marcin-rynkowski-narzedzia' ),
			self::CAPABILITY,
			self::SLUG,
			array( $this, 'render' ),
			'dashicons-admin-tools',
			3
		);
		add_submenu_page(
			self::SLUG,
			__( 'Ustawienia', 'marcin-rynkowski-narzedzia' ),
			__( 'Ustawienia', 'marcin-rynkowski-narzedzia' ),
			self::CAPABILITY,
			self::SLUG,
			array( $this, 'render' )
		);
	}

	/**
	 * Zakładki: slug => tytuł.
	 *
	 * @return array<string, string>
	 */
	public function tabs(): array {
		$tabs = array( self::TAB_MODULES => __( 'Moduły', 'marcin-rynkowski-narzedzia' ) );

		foreach ( $this->settings_tabs() as $slug => $tab ) {
			$tabs[ $slug ] = $tab['title'];
		}

		return $tabs;
	}

	/**
	 * Zakładki ustawień uruchomionych modułów.
	 *
	 * @return array<string, SettingsTab>
	 */
	private function settings_tabs(): array {
		$tabs = array();

		foreach ( $this->registry->active() as $slug => $module ) {
			if ( $module instanceof Has_Settings ) {
				$tabs[ $slug ] = $module->settings_tab();
			}
		}

		return $tabs;
	}

	/**
	 * Bieżąca zakładka z adresu; nieznana albo brak = Moduły.
	 */
	public function current_tab(): string {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- tylko wybór widoku, porównany z białą listą.
		$tab = isset( $_GET['zakladka'] ) ? sanitize_key( wp_unslash( $_GET['zakladka'] ) ) : self::TAB_MODULES;

		return array_key_exists( $tab, $this->tabs() ) ? $tab : self::TAB_MODULES;
	}

	/**
	 * Ekran ustawień: zakładki, formularz, przycisk zapisu.
	 */
	public function render(): void {
		if ( ! current_user_can( self::CAPABILITY ) ) {
			return;
		}

		$current = $this->current_tab();
		?>
		<div class="wrap woocommerce">
			<h1 class="screen-reader-text"><?php esc_html_e( 'Narzędzia: ustawienia', 'marcin-rynkowski-narzedzia' ); ?></h1>
			<nav class="nav-tab-wrapper woo-nav-tab-wrapper" aria-label="<?php esc_attr_e( 'Zakładki ustawień', 'marcin-rynkowski-narzedzia' ); ?>">
				<?php foreach ( $this->tabs() as $slug => $title ) : ?>
					<a href="<?php echo esc_url( $this->tab_url( $slug ) ); ?>" class="nav-tab<?php echo $slug === $current ? ' nav-tab-active' : ''; ?>"<?php echo $slug === $current ? ' aria-current="page"' : ''; ?>><?php echo esc_html( $title ); ?></a>
				<?php endforeach; ?>
			</nav>
			<?php
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- tylko komunikat po przekierowaniu, bez zmiany danych.
			if ( isset( $_GET['zapisano'] ) ) :
				?>
				<div class="notice notice-success"><p><?php esc_html_e( 'Zapisano ustawienia.', 'marcin-rynkowski-narzedzia' ); ?></p></div>
			<?php endif; ?>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="<?php echo esc_attr( self::ACTION ); ?>">
				<input type="hidden" name="zakladka" value="<?php echo esc_attr( $current ); ?>">
				<?php
				wp_nonce_field( self::ACTION . '_' . $current );

				if ( self::TAB_MODULES === $current ) {
					$this->render_modules();
				} else {
					woocommerce_admin_fields( $this->wc_fields( $this->settings_tabs()[ $current ] ) );
				}
				?>
				<p class="submit">
					<button type="submit" class="button-primary woocommerce-save-button"><?php esc_html_e( 'Zapisz zmiany', 'marcin-rynkowski-narzedzia' ); ?></button>
				</p>
			</form>
		</div>
		<?php
	}

	/**
	 * Adres zakładki.
	 *
	 * @param string $tab Slug zakładki.
	 */
	private function tab_url( string $tab ): string {
		return add_query_arg(
			array(
				'page'     => self::SLUG,
				'zakladka' => $tab,
			),
			admin_url( 'admin.php' )
		);
	}

	/**
	 * Zakładka Moduły: przełączniki i zgoda na usunięcie danych.
	 */
	private function render_modules(): void {
		$missing = $this->registry->missing();
		?>
		<h2><?php esc_html_e( 'Moduły', 'marcin-rynkowski-narzedzia' ); ?></h2>
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
		<?php
	}

	/**
	 * Pola zakładki w formacie WooCommerce, z bieżącymi (zwalidowanymi) wartościami.
	 *
	 * @param array $tab Zakładka modułu.
	 * @return array<int, array<string, mixed>>
	 * @phpstan-param SettingsTab $tab
	 */
	public function wc_fields( array $tab ): array {
		$values = Settings::values( $tab['option'], $tab['fields'] );
		$fields = array(
			array(
				'type'  => 'title',
				'title' => $tab['title'],
				'id'    => $tab['option'],
			),
		);

		foreach ( $tab['fields'] as $field ) {
			$wc = array(
				'id'      => $tab['option'] . '[' . $field['id'] . ']',
				'type'    => 'color' === $field['type'] ? 'rynkowski_color' : $field['type'],
				'title'   => $field['title'],
				'desc'    => $field['desc'] ?? '',
				'default' => $field['default'],
				'value'   => $values[ $field['id'] ],
			);

			if ( 'number' === $field['type'] ) {
				$wc['custom_attributes'] = array_filter(
					array(
						'min'  => $field['min'] ?? null,
						'max'  => $field['max'] ?? null,
						'step' => 1,
					),
					static function ( $value ): bool {
						return null !== $value;
					}
				);
				$wc['suffix']            = $field['suffix'] ?? '';
			}

			if ( 'select' === $field['type'] ) {
				$wc['options'] = $field['options'] ?? array();
			}

			$fields[] = $wc;
		}

		$fields[] = array(
			'type' => 'sectionend',
			'id'   => $tab['option'],
		);

		return $fields;
	}

	/**
	 * Pole koloru z próbnikiem WordPressa (WooCommerce ładuje swój tylko na własnej stronie ustawień).
	 *
	 * @param array<string, mixed> $field Pole w formacie WooCommerce.
	 */
	public function render_color_field( array $field ): void {
		$id    = (string) $field['id'];
		$value = (string) $field['value'];
		?>
		<tr>
			<th scope="row" class="titledesc">
				<label for="<?php echo esc_attr( $id ); ?>"><?php echo esc_html( (string) $field['title'] ); ?></label>
			</th>
			<td class="forminp forminp-color">
				<input type="text" id="<?php echo esc_attr( $id ); ?>" name="<?php echo esc_attr( $id ); ?>" value="<?php echo esc_attr( $value ); ?>" class="rynkowski-color">
				<?php if ( '' !== (string) ( $field['desc'] ?? '' ) ) : ?>
					<p class="description"><?php echo esc_html( (string) $field['desc'] ); ?></p>
				<?php endif; ?>
			</td>
		</tr>
		<?php
	}

	/**
	 * Zapisuje bieżącą zakładkę i wraca do niej.
	 */
	public function save(): void {
		if ( ! current_user_can( self::CAPABILITY ) ) {
			wp_die( esc_html__( 'Brak uprawnień.', 'marcin-rynkowski-narzedzia' ), '', array( 'response' => 403 ) );
		}

		$tab = isset( $_POST['zakladka'] ) ? sanitize_key( wp_unslash( $_POST['zakladka'] ) ) : '';
		check_admin_referer( self::ACTION . '_' . $tab );

		$settings_tabs = $this->settings_tabs();

		if ( self::TAB_MODULES === $tab ) {
			$slugs = isset( $_POST['moduly'] ) ? array_map( 'sanitize_key', (array) wp_unslash( $_POST['moduly'] ) ) : array();
			$this->registry->set_enabled( $slugs );
			update_option( Plugin::OPTION_DELETE_DATA, isset( $_POST['usun_dane'] ) ? 'yes' : 'no' );
		} elseif ( isset( $settings_tabs[ $tab ] ) ) {
			$option = $settings_tabs[ $tab ]['option'];
			// Każda wartość przechodzi ścisłą walidację w Settings::from_form() według schematu pola.
			$raw = isset( $_POST[ $option ] ) && is_array( $_POST[ $option ] ) ? wp_unslash( $_POST[ $option ] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- walidacja w Settings::from_form().
			update_option( $option, Settings::from_form( $raw, $settings_tabs[ $tab ]['fields'] ) );
		} else {
			$tab = self::TAB_MODULES;
		}

		// admin-post.php kończy żądanie po tej akcji, więc exit nie jest potrzebny.
		wp_safe_redirect( add_query_arg( 'zapisano', '1', $this->tab_url( $tab ) ) );
	}

	/**
	 * Ekran ustawień dostaje style i skrypty WooCommerce (podpowiedzi, wygląd pól).
	 *
	 * @param mixed $screen_ids Ekrany WooCommerce.
	 * @return mixed
	 */
	public function screen_ids( $screen_ids ) {
		if ( is_array( $screen_ids ) ) {
			$screen_ids[] = self::SCREEN_ID;
		}

		return $screen_ids;
	}

	/**
	 * Próbnik kolorów tylko na ekranie ustawień.
	 *
	 * @param string $hook Uchwyt bieżącego ekranu.
	 */
	public function enqueue( string $hook ): void {
		if ( self::SCREEN_ID !== $hook ) {
			return;
		}

		wp_enqueue_style( 'wp-color-picker' );
		wp_enqueue_script( 'wp-color-picker' );
		wp_add_inline_script( 'wp-color-picker', 'jQuery(function($){$(".rynkowski-color").wpColorPicker();});' );
	}
}
