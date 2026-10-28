<?php
/**
 * Liczniki promocji: wyświetlanie na stronie produktu.
 *
 * @package Rynkowski\Narzedzia
 */

namespace Rynkowski\Narzedzia\Moduly\Liczniki;

use Rynkowski\Narzedzia\Plugin;

defined( 'ABSPATH' ) || exit;

/**
 * Licznik pod ceną na stronie produktu; odlicza i znika w przeglądarce.
 *
 * @phpstan-import-type CounterData from Counters
 */
final class Counter_Frontend {

	/**
	 * Liczniki pobierane raz na żądanie.
	 *
	 * @var CounterData[]|null
	 */
	private static ?array $counters = null;

	/**
	 * Podpina wyświetlanie i zasoby.
	 */
	public static function register(): void {
		// Priorytet 11 = zaraz po cenie; Blocksy przenosi 11–20 na swój hook „po cenie”.
		add_action( 'woocommerce_single_product_summary', array( self::class, 'render' ), 11 );
		add_action( 'wp_enqueue_scripts', array( self::class, 'enqueue' ) );
	}

	/**
	 * Wypisuje licznik bieżącego produktu, jeśli jakiś trwa.
	 */
	public static function render(): void {
		global $product;

		if ( ! $product instanceof \WC_Product ) {
			return;
		}

		if ( null === self::$counters ) {
			self::$counters = Counters::all();
		}

		$now     = time();
		$counter = Counters::for_product( self::$counters, $product->get_id(), $now );
		if ( null === $counter ) {
			return;
		}

		echo self::html( $counter, $now ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escapowane w html().
		wp_enqueue_script( 'rynkowski-liczniki' );
	}

	/**
	 * HTML licznika z wartościami na chwilę wygenerowania strony (JS je odświeża).
	 *
	 * @param array $counter Licznik.
	 * @param int   $now     Bieżący czas (Unix).
	 * @phpstan-param CounterData $counter
	 */
	private static function html( array $counter, int $now ): string {
		$left  = max( 0, $counter['end'] - $now );
		$parts = array(
			'dni'  => array( intdiv( $left, DAY_IN_SECONDS ), __( 'Dni', 'marcin-rynkowski-narzedzia' ) ),
			'godz' => array( intdiv( $left % DAY_IN_SECONDS, HOUR_IN_SECONDS ), __( 'Godz.', 'marcin-rynkowski-narzedzia' ) ),
			'min'  => array( intdiv( $left % HOUR_IN_SECONDS, MINUTE_IN_SECONDS ), __( 'Min.', 'marcin-rynkowski-narzedzia' ) ),
			'sek'  => array( $left % MINUTE_IN_SECONDS, __( 'Sek.', 'marcin-rynkowski-narzedzia' ) ),
		);

		$fields = '';
		foreach ( $parts as $unit => list( $value, $label ) ) {
			$fields .= sprintf(
				'<div class="rynkowski-licznik__pole"><span class="rynkowski-licznik__liczba" data-jednostka="%s">%s</span><span class="rynkowski-licznik__nazwa">%s</span></div>',
				esc_attr( $unit ),
				esc_html( str_pad( (string) $value, 2, '0', STR_PAD_LEFT ) ),
				esc_html( $label )
			);
		}

		$text = '' !== $counter['text'] ? '<p class="rynkowski-licznik__tekst">' . esc_html( $counter['text'] ) . '</p>' : '';
		/* translators: %s: data i godzina końca promocji */
		$ends = sprintf( __( 'Promocja kończy się %s.', 'marcin-rynkowski-narzedzia' ), wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $counter['end'] ) );

		// Tykające cyfry ukryte przed czytnikami ekranu; zamiast nich jedno zdanie z datą końca.
		return sprintf(
			'<div class="rynkowski-licznik" data-koniec="%d">%s<span class="screen-reader-text">%s</span><div class="rynkowski-licznik__pola" aria-hidden="true">%s</div></div>',
			(int) $counter['end'],
			$text,
			esc_html( $ends ),
			$fields
		);
	}

	/**
	 * Style z ustawień i skrypt odliczania, tylko na stronie produktu.
	 */
	public static function enqueue(): void {
		if ( ! is_product() ) {
			return;
		}

		wp_register_style( 'rynkowski-liczniki', false, array(), Plugin::VERSION );
		wp_enqueue_style( 'rynkowski-liczniki' );
		wp_add_inline_style( 'rynkowski-liczniki', self::css() );

		// Ładowany dopiero, gdy na stronie jest licznik (render()).
		wp_register_script( 'rynkowski-liczniki', plugins_url( 'assets/liczniki.js', dirname( __DIR__ ) . '/module.php' ), array(), Plugin::VERSION, true );
	}

	/**
	 * Style licznika z ustawień modułu (wartości zwalidowane: liczby w zakresie, kolory HEX, białe listy).
	 */
	private static function css(): string {
		$color = static function ( string $key, string $fallback ): string {
			$value = sanitize_hex_color( (string) Counter_Settings::get( $key ) );
			return $value ? $value : $fallback;
		};
		$int   = static function ( string $key ): int {
			return (int) Counter_Settings::get( $key );
		};

		return '.rynkowski-licznik{margin:16px 0;}'
			. '.rynkowski-licznik__tekst{margin:0 0 8px;color:' . $color( 'kolor_tekstu', 'inherit' ) . ';font-weight:' . $int( 'grubosc_tekstu' ) . ';font-size:' . $int( 'rozmiar_tekstu' ) . 'px;}'
			. '.rynkowski-licznik__pola{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:' . $int( 'odstep' ) . 'px;}'
			. '.rynkowski-licznik__pole{display:flex;flex-direction:column;align-items:center;padding:' . $int( 'padding' ) . 'px;border:1px solid ' . $color( 'kolor_ramki', 'var(--theme-border-color,#e0e0e0)' ) . ';background:' . $color( 'kolor_tla', 'transparent' ) . ';}'
			. '.rynkowski-licznik__liczba{font-size:' . $int( 'rozmiar_cyfr' ) . 'px;font-weight:' . $int( 'grubosc_cyfr' ) . ';line-height:1.1;color:' . $color( 'kolor_cyfr', 'var(--theme-palette-color-1,currentColor)' ) . ';font-variant-numeric:tabular-nums;}'
			. '.rynkowski-licznik__nazwa{font-size:11px;letter-spacing:.08em;text-transform:uppercase;opacity:.8;}';
	}
}
