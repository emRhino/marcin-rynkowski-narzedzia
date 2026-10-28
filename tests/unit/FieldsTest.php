<?php
// phpcs:ignoreFile -- plik testowy.

namespace Rynkowski\Narzedzia\Tests;

use Brain\Monkey\Functions;
use Rynkowski\Narzedzia\Fields;

final class FieldsTest extends TestCase {

	public function test_term_select_lists_terms_marks_selected_and_escapes_names(): void {
		Functions\expect( 'get_terms' )
			->once()
			->with( array( 'taxonomy' => 'product_cat', 'hide_empty' => false ) )
			->andReturn( array( new \WP_Term( 4, 'Torebki' ), new \WP_Term( 9, '<script>x</script>' ) ) );

		$html = $this->capture( fn() => Fields::term_select( 'product_cat', 'pole', 'Kategorie', array( 9 ) ) );

		$this->assertStringContainsString( 'id="rynkowski-product_cat"', $html );
		$this->assertStringContainsString( 'name="pole[]"', $html );
		$this->assertStringContainsString( '<option value="4">Torebki</option>', $html );
		$this->assertStringContainsString( '<option value="9" selected=\'selected\'>&lt;script&gt;x&lt;/script&gt;</option>', $html );
		$this->assertStringNotContainsString( '<script>', $html );
		// Układ jak w ustawieniach WooCommerce, bez dymka, gdy nie podano treści.
		$this->assertStringContainsString( '<th scope="row" class="titledesc"><label for="rynkowski-product_cat">Kategorie</label></th>', $html );
		$this->assertStringContainsString( '<td class="forminp">', $html );
		$this->assertStringNotContainsString( 'woocommerce-help-tip', $html );
	}

	public function test_term_select_with_help_tip(): void {
		Functions\when( 'get_terms' )->justReturn( array() );

		$html = $this->capture( fn() => Fields::term_select( 'product_tag', 'tagi', 'Tagi', array(), 'Podpowiedź' ) );

		$this->assertStringContainsString( '<label for="rynkowski-product_tag">Tagi</label><span class="woocommerce-help-tip"', $html );
		$this->assertStringContainsString( 'data-tip="Podpowiedź"', $html );
	}

	public function test_label_escapes_and_adds_tip_only_when_given(): void {
		$this->assertSame(
			'<label for="a&quot;b">&lt;b&gt;Etykieta&lt;/b&gt;</label>',
			$this->capture( fn() => Fields::label( 'a"b', '<b>Etykieta</b>' ) )
		);
		$this->assertSame(
			'<label for="x">X</label><span class="woocommerce-help-tip" tabindex="0" aria-label="&lt;i&gt;tip&lt;/i&gt;" data-tip="&lt;i&gt;tip&lt;/i&gt;"></span>',
			$this->capture( fn() => Fields::label( 'x', 'X', '<i>tip</i>' ) )
		);
	}

	public function test_term_select_survives_wp_error(): void {
		Functions\when( 'get_terms' )->justReturn( new \stdClass() );

		$html = $this->capture( fn() => Fields::term_select( 'product_tag', 'tagi', 'Tagi', array() ) );

		$this->assertStringNotContainsString( '<option', $html );
	}

	public function test_posted_ids_cleans_dedupes_and_validates(): void {
		$_POST['ids'] = array( '5', '5', '0', '-3', 'abc', '8', '11' );

		$ids = Fields::posted_ids( 'ids', static fn( int $id ): bool => 11 !== $id );

		$this->assertSame( array( 5, 3, 8 ), $ids );
	}

	public function test_posted_ids_accepts_single_value_and_missing_field(): void {
		$_POST['jeden'] = '6';

		$this->assertSame( array( 6 ), Fields::posted_ids( 'jeden', static fn(): bool => true ) );
		$this->assertSame( array(), Fields::posted_ids( 'brak', static fn(): bool => true ) );
	}

	public function test_is_term_of_checks_taxonomy(): void {
		Functions\when( 'get_term' )->alias(
			static fn( int $id, string $taxonomy ) => 1 === $id && 'product_cat' === $taxonomy ? new \WP_Term( 1 ) : null
		);

		$validator = Fields::is_term_of( 'product_cat' );

		$this->assertTrue( $validator( 1 ) );
		$this->assertFalse( $validator( 2 ) );
	}
}
