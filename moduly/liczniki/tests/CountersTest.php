<?php
// phpcs:ignoreFile -- plik testowy.

namespace Rynkowski\Narzedzia\Moduly\Liczniki\Tests;

use Brain\Monkey\Actions;
use Brain\Monkey\Functions;
use Rynkowski\Narzedzia\Moduly\Liczniki\Counters;
use Rynkowski\Narzedzia\Tests\TestCase;

final class CountersTest extends TestCase {

	private const NOW = 1800000000;

	protected function setUp(): void {
		parent::setUp();
		Functions\when( 'get_the_title' )->alias( static fn( int $id ): string => 'Licznik ' . $id );
		Functions\when( 'wc_get_product_term_ids' )->alias(
			static fn( int $id, string $taxonomy ): array => 'product_cat' === $taxonomy ? array( 10 ) : array( 20 )
		);
	}

	private function counter( int $id, array $fields ): array {
		return $fields + array(
			'id'         => $id,
			'name'       => 'L' . $id,
			'text'       => 'Promo',
			'end'        => self::NOW + 3600,
			'active'     => true,
			'products'   => array(),
			'categories' => array(),
			'tags'       => array(),
		);
	}

	public function test_register_hooks_post_type_on_init(): void {
		Actions\expectAdded( 'init' )->once()->with( array( Counters::class, 'register_post_type' ) );

		Counters::register();
	}

	public function test_post_type_is_private_and_lives_in_tools_menu(): void {
		Functions\expect( 'register_post_type' )
			->once()
			->with(
				Counters::POST_TYPE,
				\Mockery::on(
					static fn( array $args ): bool => false === $args['public']
						&& 'rynkowski-narzedzia' === $args['show_in_menu']
						&& 'product' === $args['capability_type']
						&& 'Liczniki promocji' === $args['labels']['name']
				)
			);

		Counters::register_post_type();
	}

	public function test_get_reads_fields_with_defaults(): void {
		$this->meta[3] = array(
			'_rynkowski_licznik_text'       => '-12% Z KODEM: FLASH',
			'_rynkowski_licznik_end'        => '1800003600',
			'_rynkowski_licznik_active'     => 'yes',
			'_rynkowski_licznik_products'   => array( '5', 0 ),
			'_rynkowski_licznik_categories' => 'zepsute',
		);

		$this->assertSame(
			array(
				'id'         => 3,
				'name'       => 'Licznik 3',
				'text'       => '-12% Z KODEM: FLASH',
				'end'        => 1800003600,
				'active'     => true,
				'products'   => array( 5 ),
				'categories' => array(),
				'tags'       => array(),
			),
			Counters::get( 3 )
		);
		$this->assertSame( 0, Counters::get( 4 )['end'] );
		$this->assertFalse( Counters::get( 4 )['active'] );
	}

	public function test_all_returns_published_counters(): void {
		Functions\expect( 'get_posts' )
			->once()
			->with( \Mockery::on( static fn( array $args ): bool => Counters::POST_TYPE === $args['post_type'] && 'publish' === $args['post_status'] && 'ids' === $args['fields'] ) )
			->andReturn( array( '7', 2 ) );

		$this->assertSame( array( 7, 2 ), array_column( Counters::all(), 'id' ) );
	}

	public function test_is_running_needs_active_and_future_end(): void {
		$this->assertTrue( Counters::is_running( $this->counter( 1, array() ), self::NOW ) );
		$this->assertFalse( Counters::is_running( $this->counter( 1, array( 'active' => false ) ), self::NOW ) );
		$this->assertFalse( Counters::is_running( $this->counter( 1, array( 'end' => self::NOW ) ), self::NOW ) );
		$this->assertFalse( Counters::is_running( $this->counter( 1, array( 'end' => 0 ) ), self::NOW ) );
	}

	public function test_for_product_picks_the_earliest_running_match(): void {
		$counters = array(
			$this->counter( 1, array( 'products' => array( 5 ), 'end' => self::NOW + 7200 ) ),
			$this->counter( 2, array( 'categories' => array( 10 ), 'end' => self::NOW + 600 ) ),
			$this->counter( 3, array( 'tags' => array( 20 ), 'end' => self::NOW + 900 ) ),
			// Kończy się najwcześniej, ale jest wyłączony albo już po czasie.
			$this->counter( 4, array( 'products' => array( 5 ), 'end' => self::NOW + 60, 'active' => false ) ),
			$this->counter( 5, array( 'products' => array( 5 ), 'end' => self::NOW - 60 ) ),
			// Inny produkt.
			$this->counter( 6, array( 'products' => array( 6 ), 'end' => self::NOW + 30 ) ),
		);

		$this->assertSame( 2, Counters::for_product( $counters, 5, self::NOW )['id'] );
	}

	public function test_for_product_without_match_is_null(): void {
		$this->assertNull( Counters::for_product( array( $this->counter( 1, array( 'products' => array( 6 ) ) ) ), 5, self::NOW ) );
		$this->assertNull( Counters::for_product( array(), 5, self::NOW ) );
	}
}
