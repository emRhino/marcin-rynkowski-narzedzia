<?php
// phpcs:ignoreFile -- plik testowy.

namespace Rynkowski\Narzedzia\Moduly\Kafle\Tests;

use Brain\Monkey\Actions;
use Brain\Monkey\Functions;
use Rynkowski\Narzedzia\Moduly\Kafle\Tiles;
use Rynkowski\Narzedzia\Tests\TestCase;

final class TilesTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		Functions\when( 'get_the_title' )->alias( static fn( int $id ): string => 'Kafel ' . $id );
	}

	public function test_register_hooks_post_type_on_init(): void {
		Actions\expectAdded( 'init' )->once()->with( array( Tiles::class, 'register_post_type' ) );

		Tiles::register();
	}

	public function test_post_type_is_private_and_lives_in_woocommerce_menu(): void {
		Functions\expect( 'register_post_type' )
			->once()
			->with(
				Tiles::POST_TYPE,
				\Mockery::on(
					static fn( array $args ): bool => false === $args['public']
						&& 'woocommerce' === $args['show_in_menu']
						&& 'product' === $args['capability_type']
						&& 'Kafle promocyjne' === $args['labels']['name']
				)
			);

		Tiles::register_post_type();
	}

	public function test_get_defaults_for_new_tile(): void {
		$this->assertSame(
			array(
				'id'              => 3,
				'name'            => 'Kafel 3',
				'type'            => 'image',
				'link'            => '',
				'active'          => false,
				'on_shop'         => false,
				'categories'      => array(),
				'tags'            => array(),
				'position'        => 1,
				'first_page_only' => true,
				'image_id'        => 0,
				'video_id'        => 0,
				'poster_id'       => 0,
				'heading'         => '',
				'description'     => '',
				'button'          => '',
				'bg_color'        => '',
				'text_color'      => '',
			),
			Tiles::get( 3 )
		);
	}

	public function test_get_reads_saved_fields(): void {
		$this->meta[4] = array(
			'_rynkowski_kafel_type'            => 'video',
			'_rynkowski_kafel_link'            => 'https://sklep.test/promo',
			'_rynkowski_kafel_active'          => 'yes',
			'_rynkowski_kafel_on_shop'         => 'yes',
			'_rynkowski_kafel_categories'      => array( '10', 0 ),
			'_rynkowski_kafel_tags'            => 'zepsute',
			'_rynkowski_kafel_position'        => '6',
			'_rynkowski_kafel_first_page_only' => 'no',
			'_rynkowski_kafel_video_id'        => '12',
			'_rynkowski_kafel_heading'         => 'Nagłówek',
		);

		$tile = Tiles::get( 4 );

		$this->assertSame( 'video', $tile['type'] );
		$this->assertTrue( $tile['active'] );
		$this->assertTrue( $tile['on_shop'] );
		$this->assertSame( array( 10 ), $tile['categories'] );
		$this->assertSame( array(), $tile['tags'] );
		$this->assertSame( 6, $tile['position'] );
		$this->assertFalse( $tile['first_page_only'] );
		$this->assertSame( 12, $tile['video_id'] );
		$this->assertSame( 'Nagłówek', $tile['heading'] );
	}

	public function test_get_rejects_unknown_type(): void {
		$this->meta[5] = array( '_rynkowski_kafel_type' => '<script>' );

		$this->assertSame( 'image', Tiles::get( 5 )['type'] );
	}

	public function test_all_returns_published_tiles(): void {
		Functions\expect( 'get_posts' )
			->once()
			->with( \Mockery::on( static fn( array $args ): bool => Tiles::POST_TYPE === $args['post_type'] && 'publish' === $args['post_status'] && 'ids' === $args['fields'] ) )
			->andReturn( array( '8', 2 ) );

		$this->assertSame( array( 8, 2 ), array_column( Tiles::all(), 'id' ) );
	}
}
