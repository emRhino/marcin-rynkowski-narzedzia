<?php
// phpcs:ignoreFile -- plik testowy.

namespace Rynkowski\Narzedzia\Moduly\Informacje\Tests;

use Brain\Monkey\Functions;
use Rynkowski\Narzedzia\Moduly\Informacje\Info_Rows;
use Rynkowski\Narzedzia\Tests\TestCase;

/**
 * Wspólne zaślepki modułu: wiersze w pamięci i uproszczony wp_kses.
 */
abstract class InfoTestCase extends TestCase {

	/**
	 * Kolejność wierszy (menu_order): ID => liczba.
	 *
	 * @var array<int, int>
	 */
	protected array $orders = array();

	/**
	 * Argumenty ostatniego get_posts.
	 *
	 * @var array<string, mixed>
	 */
	protected array $query = array();

	protected function setUp(): void {
		parent::setUp();
		$this->orders = array();

		Functions\when( 'get_the_title' )->alias( static fn( int $id ): string => 'Wiersz ' . $id );
		Functions\when( 'get_post_field' )->alias( fn( string $field, int $id ) => $this->orders[ $id ] ?? 0 );
		// Jak WP_Query: menu_order rosnąco, potem ID.
		Functions\when( 'get_posts' )->alias(
			function ( array $args ): array {
				$this->query = $args;
				$ids         = array_keys( $this->orders );
				usort( $ids, fn( int $a, int $b ): int => array( $this->orders[ $a ], $a ) <=> array( $this->orders[ $b ], $b ) );
				return $ids;
			}
		);
		Functions\when( 'wp_kses' )->alias( array( self::class, 'kses' ) );
		Functions\when( 'delete_post_meta' )->alias(
			function ( int $id, string $key ): bool {
				unset( $this->meta[ $id ][ $key ] );
				return true;
			}
		);
	}

	/**
	 * Dodaje opublikowany wiersz.
	 */
	protected function row( int $id, string $text, int $order = 0, int $icon = 0, string $active = 'yes' ): void {
		$this->orders[ $id ] = $order;
		$this->meta[ $id ]   = array(
			Info_Rows::META_TEXT   => $text,
			Info_Rows::META_ICON   => $icon,
			Info_Rows::META_ACTIVE => $active,
		);
	}

	/**
	 * Uproszczony wp_kses: tylko dozwolone znaczniki, atrybuty z listy, bez javascript: w adresach.
	 *
	 * @param array<string, array<string, bool>> $allowed
	 */
	public static function kses( string $html, array $allowed ): string {
		$html = strip_tags( $html, array_keys( $allowed ) );

		return (string) preg_replace_callback(
			'#<([a-z]+)([^>]*)>#i',
			static function ( array $m ) use ( $allowed ): string {
				$attrs = '';
				foreach ( array_keys( $allowed[ strtolower( $m[1] ) ] ?? array() ) as $attr ) {
					if ( preg_match( '#\s' . $attr . '="([^"]*)"#', $m[2], $value ) && ! preg_match( '#^\s*javascript:#i', $value[1] ) ) {
						$attrs .= ' ' . $attr . '="' . $value[1] . '"';
					}
				}
				return '<' . $m[1] . $attrs . '>';
			},
			$html
		);
	}
}
