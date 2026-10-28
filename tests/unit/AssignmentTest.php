<?php
// phpcs:ignoreFile -- plik testowy.

namespace Rynkowski\Narzedzia\Tests;

use Brain\Monkey\Functions;
use Rynkowski\Narzedzia\Assignment;

final class AssignmentTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		Functions\when( 'wc_get_product_term_ids' )->alias(
			static fn( int $id, string $taxonomy ): array => 'product_cat' === $taxonomy ? array( 10, 11 ) : array( 20 )
		);
	}

	private function rules( array $products = array(), array $categories = array(), array $tags = array() ): array {
		return array( 'products' => $products, 'categories' => $categories, 'tags' => $tags );
	}

	public function test_matches_product_directly(): void {
		$this->assertTrue( Assignment::matches( $this->rules( array( 5 ) ), 5 ) );
	}

	public function test_matches_by_category_or_tag(): void {
		$this->assertTrue( Assignment::matches( $this->rules( array(), array( 11 ) ), 5 ) );
		$this->assertTrue( Assignment::matches( $this->rules( array(), array(), array( 20 ) ), 5 ) );
	}

	public function test_does_not_match_other_items(): void {
		$this->assertFalse( Assignment::matches( $this->rules( array( 6 ), array( 12 ), array( 21 ) ), 5 ) );
		$this->assertFalse( Assignment::matches( $this->rules(), 5 ) );
	}

	public function test_empty_rules_do_not_query_terms(): void {
		Functions\expect( 'wc_get_product_term_ids' )->never();

		$this->assertFalse( Assignment::matches( $this->rules( array( 6 ) ), 5 ) );
	}
}
