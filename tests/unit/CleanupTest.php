<?php
// phpcs:ignoreFile -- plik testowy.

namespace Rynkowski\Narzedzia\Tests;

use Brain\Monkey\Functions;
use Rynkowski\Narzedzia\Cleanup;

final class CleanupTest extends TestCase {

	public function test_deletes_every_post_of_type_permanently(): void {
		Functions\expect( 'get_posts' )
			->once()
			->with(
				array(
					'post_type'      => 'rynkowski_test',
					'post_status'    => 'any',
					'posts_per_page' => -1,
					'fields'         => 'ids',
				)
			)
			->andReturn( array( 3, '7' ) );
		Functions\expect( 'wp_delete_post' )->once()->with( 3, true );
		Functions\expect( 'wp_delete_post' )->once()->with( 7, true );

		Cleanup::delete_posts( 'rynkowski_test' );
	}
}
