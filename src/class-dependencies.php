<?php
/**
 * Sprawdzanie zależności modułów.
 *
 * @package Rynkowski\Narzedzia
 */

namespace Rynkowski\Narzedzia;

defined( 'ABSPATH' ) || exit;

/**
 * Zna wtyczki i motyw, których mogą wymagać moduły.
 */
final class Dependencies {

	const LABELS = array(
		'woocommerce' => 'WooCommerce',
		'stackable'   => 'Stackable',
		'blocksy'     => 'Blocksy',
	);

	/**
	 * Czy zależność jest aktywna.
	 *
	 * @param string $dependency Klucz z LABELS.
	 */
	public static function is_met( string $dependency ): bool {
		switch ( $dependency ) {
			case 'woocommerce':
				// WooCommerce odpala tę akcję przy własnym starcie, przed naszym plugins_loaded.
				return did_action( 'woocommerce_loaded' ) > 0;
			case 'stackable':
				// Stała jest ustawiana zarówno przez darmowy Stackable, jak i Premium.
				return defined( 'STACKABLE_VERSION' );
			case 'blocksy':
				// Szablon nadrzędny, więc motyw potomny Blocksy też się liczy.
				return 'blocksy' === get_template();
			default:
				return false;
		}
	}

	/**
	 * Nazwy brakujących zależności.
	 *
	 * @param string[] $dependencies Klucze z LABELS.
	 * @return string[]
	 */
	public static function missing( array $dependencies ): array {
		$missing = array();

		foreach ( $dependencies as $dependency ) {
			if ( ! self::is_met( $dependency ) ) {
				$missing[] = self::LABELS[ $dependency ] ?? $dependency;
			}
		}

		return $missing;
	}
}
