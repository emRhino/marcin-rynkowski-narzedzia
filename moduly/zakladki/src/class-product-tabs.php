<?php
/**
 * Zakładki produktu: zakładki na karcie produktu.
 *
 * @package Rynkowski\Narzedzia
 */

namespace Rynkowski\Narzedzia\Moduly\Zakladki;

defined( 'ABSPATH' ) || exit;

/**
 * Dodaje niepuste treści włączonych zakładek jako zakładki WooCommerce; wygląd (karty albo akordeon) zostawia motywowi.
 */
final class Product_Tabs {

	const META_PREFIX = '_rynkowski_zakladki_';

	// Prefiks kluczy zakładek WooCommerce (id panelu: tab-rynkowski_<klucz>).
	const TAB_PREFIX = 'rynkowski_';

	// Opis WooCommerce ma priorytet 10, Informacje dodatkowe 20, Opinie 30.
	const FIRST_PRIORITY = 11;

	/**
	 * Zakładki w stałej kolejności: klucz => tytuł.
	 *
	 * @return array<string, string>
	 */
	public static function labels(): array {
		return array(
			'pielegnacja'    => __( 'Pielęgnacja', 'marcin-rynkowski-narzedzia' ),
			'cechy'          => __( 'Cechy produktu', 'marcin-rynkowski-narzedzia' ),
			'bezpieczenstwo' => __( 'Bezpieczeństwo produktu', 'marcin-rynkowski-narzedzia' ),
		);
	}

	/**
	 * Zakładki włączone w ustawieniach, w stałej kolejności: klucz => tytuł.
	 *
	 * @return array<string, string>
	 */
	public static function enabled(): array {
		return array_filter( self::labels(), array( Tab_Settings::class, 'is_enabled' ), ARRAY_FILTER_USE_KEY );
	}

	/**
	 * Klucz meta treści zakładki.
	 *
	 * @param string $key Klucz zakładki.
	 */
	public static function meta_key( string $key ): string {
		return self::META_PREFIX . $key;
	}

	/**
	 * Treść zakładki produktu.
	 *
	 * @param int    $product_id ID produktu.
	 * @param string $key        Klucz zakładki.
	 */
	public static function content( int $product_id, string $key ): string {
		$content = get_post_meta( $product_id, self::meta_key( $key ), true );

		return is_string( $content ) ? $content : '';
	}

	/**
	 * Podpina zakładki.
	 */
	public static function register(): void {
		add_filter( 'woocommerce_product_tabs', array( self::class, 'add_tabs' ) );
	}

	/**
	 * Dodaje zakładki z treścią; puste pomija.
	 *
	 * @param array<string, array<string, mixed>> $tabs Zakładki WooCommerce.
	 * @return array<string, array<string, mixed>>
	 */
	public static function add_tabs( array $tabs ): array {
		$product_id = (int) get_the_ID();
		if ( ! $product_id ) {
			return $tabs;
		}

		// Priorytet z pozycji na pełnej liście, więc kolejność nie zależy od wyłączonych zakładek.
		$priority = self::FIRST_PRIORITY;
		foreach ( self::labels() as $key => $title ) {
			if ( Tab_Settings::is_enabled( $key ) && '' !== trim( self::content( $product_id, $key ) ) ) {
				$tabs[ self::TAB_PREFIX . $key ] = array(
					'title'    => $title,
					'priority' => $priority,
					'callback' => array( self::class, 'render' ),
				);
			}
			++$priority;
		}

		return $tabs;
	}

	/**
	 * Treść zakładki, sformatowana jak krótki opis WooCommerce.
	 *
	 * @param string $tab_key Klucz zakładki WooCommerce, np. rynkowski_pielegnacja.
	 */
	public static function render( string $tab_key ): void {
		$key = substr( $tab_key, strlen( self::TAB_PREFIX ) );
		if ( ! str_starts_with( $tab_key, self::TAB_PREFIX ) || ! array_key_exists( $key, self::enabled() ) ) {
			return;
		}

		echo wp_kses_post( wc_format_content( self::content( (int) get_the_ID(), $key ) ) );
	}
}
