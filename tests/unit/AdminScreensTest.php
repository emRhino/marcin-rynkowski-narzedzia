<?php
// phpcs:ignoreFile -- plik testowy.

namespace Rynkowski\Narzedzia\Tests;

use Brain\Monkey\Actions;
use Brain\Monkey\Filters;
use Brain\Monkey\Functions;
use Rynkowski\Narzedzia\Admin_Screens;
use Rynkowski\Narzedzia\Plugin;

final class AdminScreensTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		// Lista typów wpisów żyje przez żądanie; każdy test to nowe żądanie.
		( new \ReflectionProperty( Admin_Screens::class, 'post_types' ) )->setValue( null, array() );
		Functions\when( 'sanitize_html_class' )->alias( static fn( string $class ): string => preg_replace( '/[^A-Za-z0-9_-]/', '', $class ) );
	}

	public function test_first_registration_hooks_shared_filters_once_and_layout_per_type(): void {
		Filters\expectAdded( 'woocommerce_screen_ids' )->once()->with( array( Admin_Screens::class, 'screen_ids' ) );
		Actions\expectAdded( 'admin_enqueue_scripts' )->once()->with( array( Admin_Screens::class, 'enqueue' ) );
		Filters\expectAdded( 'get_user_option_screen_layout_typ_a' )->once()->with( array( Admin_Screens::class, 'one_column' ) );
		Filters\expectAdded( 'get_user_option_screen_layout_typ_b' )->once()->with( array( Admin_Screens::class, 'one_column' ) );

		Admin_Screens::register( 'typ_a' );
		Admin_Screens::register( 'typ_b' );
		Admin_Screens::register( 'typ_a' );
	}

	public function test_one_column_ignores_arguments_passed_by_wordpress(): void {
		// WordPress przekazuje do filtra get_user_option_* wartość, nazwę opcji i użytkownika.
		$this->assertSame( 1, call_user_func_array( array( Admin_Screens::class, 'one_column' ), array( '2', 'screen_layout_typ_a', new \stdClass() ) ) );
	}

	public function test_screen_ids_add_editor_and_list_of_each_type(): void {
		Admin_Screens::register( 'typ_a' );
		Admin_Screens::register( 'typ_b' );

		$this->assertSame(
			array( 'product', 'typ_a', 'edit-typ_a', 'typ_b', 'edit-typ_b' ),
			Admin_Screens::screen_ids( array( 'product' ) )
		);
		$this->assertSame( 'zepsute', Admin_Screens::screen_ids( 'zepsute' ) );
	}

	public function test_enqueue_skips_other_screens(): void {
		Admin_Screens::register( 'typ_a' );
		Functions\expect( 'wp_register_style' )->never();

		Functions\when( 'get_current_screen' )->justReturn( null );
		Admin_Screens::enqueue();

		// Lista wpisów tego typu: wygląd edytora nie jest potrzebny.
		Functions\when( 'get_current_screen' )->justReturn( new \WP_Screen( 'edit-typ_a', 'edit', 'typ_a' ) );
		Admin_Screens::enqueue();

		Functions\when( 'get_current_screen' )->justReturn( new \WP_Screen( 'product', 'post', 'product' ) );
		Admin_Screens::enqueue();
	}

	public function test_enqueue_adds_styles_scoped_to_the_edited_type(): void {
		Admin_Screens::register( 'typ_a' );
		Functions\when( 'get_current_screen' )->justReturn( new \WP_Screen( 'typ_a', 'post', 'typ_a' ) );
		Functions\expect( 'wp_register_style' )->once()->with( 'rynkowski-ekrany', false, array(), Plugin::VERSION );
		Functions\expect( 'wp_enqueue_style' )->once()->with( 'rynkowski-ekrany' );
		Functions\expect( 'wp_add_inline_style' )->once()->with(
			'rynkowski-ekrany',
			\Mockery::on(
				static function ( string $css ): bool {
					// Każda reguła ograniczona do ekranu typu, więc nie zmienia innych ekranów panelu.
					foreach ( array_filter( explode( '}', $css ) ) as $rule ) {
						if ( ! str_starts_with( $rule, 'body.post-type-typ_a ' ) && ! str_starts_with( $rule, ',' ) ) {
							return false;
						}
					}
					return str_contains( $css, '#postbox-container-1{order:2;' )
						&& str_contains( $css, '.form-table th.titledesc{width:200px;}' );
				}
			)
		);

		Admin_Screens::enqueue();
	}
}
