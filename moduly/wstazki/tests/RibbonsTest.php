<?php
// phpcs:ignoreFile -- plik testowy.

namespace Rynkowski\Narzedzia\Moduly\Wstazki\Tests;

use Brain\Monkey\Actions;
use Brain\Monkey\Functions;
use Rynkowski\Narzedzia\Moduly\Wstazki\Ribbons;
use Rynkowski\Narzedzia\Tests\TestCase;

final class RibbonsTest extends TestCase {

	/**
	 * Zapisuje wstążkę w pamięci testu.
	 *
	 * @param array<string, mixed> $meta Pola wstążki bez prefiksu.
	 */
	private function ribbon( int $id, array $meta ): void {
		foreach ( $meta as $key => $value ) {
			$this->meta[ $id ][ '_rynkowski_wstazka_' . $key ] = $value;
		}
	}

	protected function setUp(): void {
		parent::setUp();
		Functions\when( 'get_the_title' )->alias( static fn( int $id ): string => 'Wstążka ' . $id );
	}

	public function test_register_hooks_post_type_on_init(): void {
		Actions\expectAdded( 'init' )->once()->with( array( Ribbons::class, 'register_post_type' ) );

		Ribbons::register();
	}

	public function test_post_type_is_private_and_lives_in_tools_menu(): void {
		Functions\expect( 'register_post_type' )
			->once()
			->with(
				Ribbons::POST_TYPE,
				\Mockery::on(
					static fn( array $args ): bool => false === $args['public']
						&& true === $args['show_ui']
						&& 'rynkowski-narzedzia' === $args['show_in_menu']
						&& 'product' === $args['capability_type']
						&& array( 'title' ) === $args['supports']
						&& 'Wstążki' === $args['labels']['name']
				)
			);

		Ribbons::register_post_type();
	}

	public function test_get_reads_all_fields_with_defaults(): void {
		$this->ribbon(
			7,
			array(
				'text'       => 'Nowość',
				'text_color' => '#fff',
				'bg_color'   => '#000',
				'active'     => 'yes',
				'products'   => array( '5', 0, 'x', 9 ),
				'categories' => 'zepsute',
			)
		);

		$this->assertSame(
			array(
				'id'         => 7,
				'name'       => 'Wstążka 7',
				'text'       => 'Nowość',
				'text_color' => '#fff',
				'bg_color'   => '#000',
				'active'     => true,
				'products'   => array( 5, 9 ),
				'categories' => array(),
				'tags'       => array(),
			),
			Ribbons::get( 7 )
		);
	}

	public function test_get_treats_anything_but_yes_as_inactive(): void {
		$this->ribbon( 3, array( 'active' => 'no' ) );

		$this->assertFalse( Ribbons::get( 3 )['active'] );
		$this->assertFalse( Ribbons::get( 4 )['active'] );
	}

	public function test_all_returns_published_ribbons_in_order(): void {
		Functions\expect( 'get_posts' )
			->once()
			->with(
				array(
					'post_type'      => Ribbons::POST_TYPE,
					'post_status'    => 'publish',
					'posts_per_page' => -1,
					'fields'         => 'ids',
					'orderby'        => 'menu_order title',
					'order'          => 'ASC',
				)
			)
			->andReturn( array( 2, '1' ) );

		$this->assertSame( array( 2, 1 ), array_column( Ribbons::all(), 'id' ) );
	}

	public function test_for_product_explains_why_each_ribbon_applies(): void {
		Functions\when( 'get_posts' )->justReturn( array( 1, 2, 3, 4 ) );
		Functions\when( 'wc_get_product_term_ids' )->alias(
			static fn( int $id, string $taxonomy ): array => 'product_cat' === $taxonomy ? array( 10, 11 ) : array( 20 )
		);
		Functions\when( 'get_term' )->alias(
			static fn( int $id ) => 10 === $id ? new \WP_Term( 10, 'Torebki' ) : ( 20 === $id ? new \WP_Term( 20, 'Skóra' ) : null )
		);
		$this->ribbon( 1, array( 'active' => 'yes', 'products' => array( 50 ) ) );
		$this->ribbon( 2, array( 'active' => 'yes', 'categories' => array( 10, 99 ), 'tags' => array( 20 ) ) );
		$this->ribbon( 3, array( 'active' => 'no', 'products' => array( 50 ) ) );
		$this->ribbon( 4, array( 'active' => 'yes', 'categories' => array( 11 ), 'products' => array( 51 ) ) );

		$active = Ribbons::for_product( 50 );

		$this->assertSame( array( 1, 2, 4 ), array_map( static fn( array $item ): int => $item['ribbon']['id'], $active ) );
		$this->assertSame( array( 'produkt' ), $active[0]['reasons'] );
		$this->assertSame( array( 'kategoria: Torebki', 'tag: Skóra' ), $active[1]['reasons'] );
		// Termin, którego już nie ma, nie wywraca listy.
		$this->assertSame( array( 'kategoria: ' ), $active[2]['reasons'] );

		$all = Ribbons::for_product( 50, false );
		$this->assertSame( array( 1, 2, 3, 4 ), array_map( static fn( array $item ): int => $item['ribbon']['id'], $all ) );
	}

	public function test_for_product_without_matches_is_empty(): void {
		Functions\when( 'get_posts' )->justReturn( array( 1 ) );
		Functions\when( 'wc_get_product_term_ids' )->justReturn( array() );
		$this->ribbon( 1, array( 'active' => 'yes', 'products' => array( 5 ) ) );

		$this->assertSame( array(), Ribbons::for_product( 6 ) );
	}
}
