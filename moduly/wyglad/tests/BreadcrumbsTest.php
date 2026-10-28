<?php
// phpcs:ignoreFile -- plik testowy.

namespace Rynkowski\Narzedzia\Moduly\Wyglad\Tests;

use Brain\Monkey\Actions;
use Brain\Monkey\Functions;
use Rynkowski\Narzedzia\Moduly\Wyglad\Breadcrumbs;
use Rynkowski\Narzedzia\Moduly\Wyglad\Look_Settings;
use Rynkowski\Narzedzia\Plugin;
use Rynkowski\Narzedzia\Tests\TestCase;

final class BreadcrumbsTest extends TestCase {

	public function test_register_hooks_front_assets(): void {
		Actions\expectAdded( 'wp_enqueue_scripts' )->once()->with( array( Breadcrumbs::class, 'enqueue' ) );

		Breadcrumbs::register();
	}

	public function test_enabled_by_default_adds_mobile_styles_and_script(): void {
		Functions\when( 'plugins_url' )->alias( static fn( string $path, string $file ): string => 'https://sklep.test/' . basename( dirname( $file ) ) . '/' . $path );
		Functions\expect( 'wp_register_style' )->once()->with( 'rynkowski-okruszki', false, array(), Plugin::VERSION );
		Functions\expect( 'wp_enqueue_style' )->once()->with( 'rynkowski-okruszki' );
		Functions\expect( 'wp_add_inline_style' )->once()->with(
			'rynkowski-okruszki',
			\Mockery::on(
				static fn( string $css ): bool => str_starts_with( $css, '@media (max-width:689.98px){' )
					&& str_contains( $css, '.ct-breadcrumbs{white-space:nowrap;overflow-x:auto;overflow-y:hidden;' )
					&& str_contains( $css, '.ct-breadcrumbs::-webkit-scrollbar{display:none;}' )
					&& str_ends_with( $css, '}}' )
			)
		);
		Functions\expect( 'wp_enqueue_script' )->once()->with( 'rynkowski-okruszki', 'https://sklep.test/wyglad/assets/okruszki.js', array(), Plugin::VERSION, true );

		Breadcrumbs::enqueue();
	}

	public function test_switched_off_loads_nothing(): void {
		$this->options[ Look_Settings::OPTION ] = array( 'breadcrumb_w_linii' => 'no' );
		Functions\expect( 'wp_register_style' )->never();
		Functions\expect( 'wp_enqueue_script' )->never();

		Breadcrumbs::enqueue();
	}

	public function test_called_like_wordpress_hook(): void {
		$this->options[ Look_Settings::OPTION ] = array( 'breadcrumb_w_linii' => 'no' );

		// wp_enqueue_scripts bez argumentów: WordPress podaje callbackowi ''.
		call_user_func_array( array( Breadcrumbs::class, 'enqueue' ), array( '' ) );

		$this->addToAssertionCount( 1 );
	}
}
