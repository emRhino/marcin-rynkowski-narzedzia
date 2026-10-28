<?php
/**
 * Wygląd ekranów edycji modułów w stylu ustawień WooCommerce.
 *
 * @package Rynkowski\Narzedzia
 */

namespace Rynkowski\Narzedzia;

defined( 'ABSPATH' ) || exit;

/**
 * Ekran edycji typu wpisu modułu: jedna kolumna, sekcje bez ramek, pola i dymki jak w WooCommerce.
 *
 * Zostaje zwykły edytor WordPressa (zapis, kosz, szkice), zmienia się tylko wygląd.
 */
final class Admin_Screens {

	/**
	 * Typy wpisów, których ekrany dostają ten wygląd.
	 *
	 * @var string[]
	 */
	private static array $post_types = array();

	/**
	 * Dołącza typ wpisu; wspólne hooki podpina tylko raz.
	 *
	 * @param string $post_type Typ wpisu modułu.
	 */
	public static function register( string $post_type ): void {
		if ( ! self::$post_types ) {
			add_filter( 'woocommerce_screen_ids', array( self::class, 'screen_ids' ) );
			add_action( 'admin_enqueue_scripts', array( self::class, 'enqueue' ) );
		}

		if ( ! in_array( $post_type, self::$post_types, true ) ) {
			self::$post_types[] = $post_type;
			add_filter( 'get_user_option_screen_layout_' . $post_type, array( self::class, 'one_column' ) );
		}
	}

	/**
	 * Edytor zawsze w jednej kolumnie, jak strona ustawień WooCommerce.
	 *
	 * Bez parametrów celowo: WordPress przekazuje tu kilka argumentów, nie są potrzebne.
	 */
	public static function one_column(): int {
		return 1;
	}

	/**
	 * Ekrany modułów jako ekrany WooCommerce: jego style, dymki pomocy i pola wyboru.
	 *
	 * @param mixed $screen_ids Ekrany WooCommerce.
	 * @return mixed
	 */
	public static function screen_ids( $screen_ids ) {
		if ( ! is_array( $screen_ids ) ) {
			return $screen_ids;
		}

		foreach ( self::$post_types as $post_type ) {
			$screen_ids[] = $post_type;
			$screen_ids[] = 'edit-' . $post_type;
		}

		return $screen_ids;
	}

	/**
	 * Style ekranu edycji, tylko na ekranach edycji modułów.
	 */
	public static function enqueue(): void {
		$screen = get_current_screen();
		if ( ! $screen || 'post' !== $screen->base || ! in_array( $screen->post_type, self::$post_types, true ) ) {
			return;
		}

		wp_register_style( 'rynkowski-ekrany', false, array(), Plugin::VERSION );
		wp_enqueue_style( 'rynkowski-ekrany' );
		wp_add_inline_style( 'rynkowski-ekrany', self::css( $screen->post_type ) );
	}

	/**
	 * CSS ograniczony do ekranu danego typu wpisu.
	 *
	 * @param string $post_type Typ wpisu.
	 */
	private static function css( string $post_type ): string {
		$s = 'body.post-type-' . sanitize_html_class( $post_type ) . ' ';

		// Pole „Opublikuj” na dole, jak „Zapisz zmiany” w WooCommerce (tylko kolejność, zapis bez zmian).
		return $s . '#post-body.columns-1{display:flex;flex-direction:column;}'
			. $s . '#post-body.columns-1 #post-body-content{order:0;}'
			. $s . '#post-body.columns-1 #postbox-container-2{order:1;}'
			. $s . '#post-body.columns-1 #postbox-container-1{order:2;width:auto;}'
			// Sekcje bez ramek, z nagłówkiem jak w ustawieniach WooCommerce.
			. $s . '#postbox-container-2 .postbox{border:0;background:transparent;box-shadow:none;margin:0 0 8px;}'
			. $s . '#postbox-container-2 .postbox-header{border:0;}'
			. $s . '#postbox-container-2 .postbox-header .hndle{padding:8px 0 0;font-size:1.3em;font-weight:600;cursor:default;}'
			. $s . '#postbox-container-2 .handle-actions{display:none;}'
			. $s . '#postbox-container-2 .postbox .inside{padding:0;margin:0;}'
			// Etykiety i pola o szerokościach z WooCommerce.
			. $s . '.form-table th.titledesc{width:200px;}'
			. $s . '.form-table th.titledesc .woocommerce-help-tip{margin-left:4px;}'
			. $s . '.form-table td.forminp input[type=text],' . $s . '.form-table td.forminp input[type=url],' . $s . '.form-table td.forminp textarea{width:400px;max-width:100%;}';
	}
}
