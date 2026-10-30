<?php
// phpcs:ignoreFile -- plik testowy.

namespace Rynkowski\Narzedzia\Moduly\Informacje\Tests;

require_once __DIR__ . '/InfoTestCase.php';

use Brain\Monkey\Actions;
use Brain\Monkey\Functions;
use Rynkowski\Narzedzia\Moduly\Informacje\Info_Rows;

final class InfoRowsTest extends InfoTestCase {

	public function test_register_hooks_post_type_on_init(): void {
		Actions\expectAdded( 'init' )->once()->with( array( Info_Rows::class, 'register_post_type' ) );

		Info_Rows::register();
	}

	public function test_post_type_is_private_with_order_field_in_tools_menu(): void {
		Functions\expect( 'register_post_type' )
			->once()
			->with(
				Info_Rows::POST_TYPE,
				\Mockery::on(
					static fn( array $args ): bool => false === $args['public']
						&& 'rynkowski-narzedzia' === $args['show_in_menu']
						&& array( 'title', 'page-attributes' ) === $args['supports']
						&& 'product' === $args['capability_type']
						&& 'Informacje o zakupie' === $args['labels']['name']
				)
			);

		Info_Rows::register_post_type();
	}

	public function test_clean_text_keeps_formatting_and_drops_the_rest(): void {
		$raw = ' Dostawa <strong>od 0 zł</strong> <a href="/dostawa" onclick="x()" target="_blank">InPost</a> <mark>GRATIS</mark><br><small>1-2 dni</small><script>alert(1)</script><img src=x onerror=alert(1)> ';

		$this->assertSame(
			'Dostawa <strong>od 0 zł</strong> <a href="/dostawa">InPost</a> <mark>GRATIS</mark><br><small>1-2 dni</small>alert(1)',
			Info_Rows::clean_text( $raw )
		);
		$this->assertSame( '<a>klik</a>', Info_Rows::clean_text( '<a href="javascript:alert(1)">klik</a>' ) );
	}

	public function test_get_defaults_for_new_row(): void {
		$row = Info_Rows::get( 5 );

		$this->assertSame(
			array( 'id' => 5, 'name' => 'Wiersz 5', 'icon_id' => 0, 'text' => '', 'active' => true, 'order' => 0 ),
			$row
		);
	}

	public function test_all_and_active_in_order(): void {
		$this->row( 3, 'Trzeci', 2 );
		$this->row( 1, 'Pierwszy', 0, 44 );
		$this->row( 2, 'Wyłączony', 1, 0, 'no' );

		$this->assertSame( array( 1, 2, 3 ), array_column( Info_Rows::all(), 'id' ) );
		$this->assertSame( array( 'menu_order' => 'ASC', 'ID' => 'ASC' ), $this->query['orderby'] );
		$this->assertSame( 'publish', $this->query['post_status'] );

		$active = Info_Rows::active();
		$this->assertSame( array( 1, 3 ), array_column( $active, 'id' ) );
		$this->assertSame( 44, $active[0]['icon_id'] );
		$this->assertSame( 2, $active[1]['order'] );
	}

	public function test_product_texts_skip_garbage(): void {
		$this->meta[7] = array( Info_Rows::META_PRODUCT_TEXTS => array( 1 => 'Tekst', 'x' => 'zły klucz', 2 => array(), 3 => '' ) );
		$this->assertSame( array( 1 => 'Tekst' ), Info_Rows::product_texts( 7 ) );

		$this->meta[7] = array( Info_Rows::META_PRODUCT_TEXTS => 'nie tablica' );
		$this->assertSame( array(), Info_Rows::product_texts( 7 ) );
	}

	public function test_for_product_uses_own_text_then_default_and_skips_empty(): void {
		$this->row( 1, 'Domyślny 1', 0, 11 );
		$this->row( 2, 'Domyślny 2', 1 );
		$this->row( 3, '', 2 );
		$this->row( 4, '', 3 );
		$this->row( 5, 'Wyłączony', 4, 0, 'no' );
		$this->meta[7] = array( Info_Rows::META_PRODUCT_TEXTS => array( 2 => 'Własny 2', 4 => 'Własny 4', 5 => 'Własny 5' ) );

		$this->assertSame(
			array(
				array( 'icon_id' => 11, 'text' => 'Domyślny 1' ),
				array( 'icon_id' => 0, 'text' => 'Własny 2' ),
				array( 'icon_id' => 0, 'text' => 'Własny 4' ),
			),
			Info_Rows::for_product( 7 )
		);
	}
}
