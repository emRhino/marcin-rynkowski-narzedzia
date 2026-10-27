<?php
// phpcs:ignoreFile -- moduł testowy z zakładką ustawień.

namespace Rynkowski\Narzedzia\Tests;

use Rynkowski\Narzedzia\Has_Settings;
use Rynkowski\Narzedzia\Module;

final class Fake_Settings_Module implements Module, Has_Settings {

	public function __construct( private string $slug = 'ustawiony' ) {}

	public function slug(): string {
		return $this->slug;
	}

	public function name(): string {
		return 'Moduł ' . $this->slug;
	}

	public function description(): string {
		return '';
	}

	public function requires(): array {
		return array();
	}

	public function data_version(): int {
		return 1;
	}

	public function migrations(): array {
		return array();
	}

	public function register(): void {}

	public function uninstall(): void {}

	public function settings_tab(): array {
		return array(
			'title'  => 'Zakładka <b>' . $this->slug . '</b>',
			'option' => 'rynkowski_test_' . $this->slug,
			'fields' => array(
				array( 'id' => 'liczba', 'type' => 'number', 'title' => 'Liczba', 'desc' => 'Opis liczby', 'default' => 5, 'min' => 0, 'max' => 10, 'suffix' => 'px' ),
				array( 'id' => 'bez_zakresu', 'type' => 'number', 'title' => 'Bez zakresu', 'default' => 1 ),
				array( 'id' => 'kolor', 'type' => 'color', 'title' => 'Kolor', 'default' => '' ),
				array( 'id' => 'wybor', 'type' => 'select', 'title' => 'Wybór', 'default' => 'a', 'options' => array( 'a' => 'A', 'b' => 'B' ) ),
				array( 'id' => 'flaga', 'type' => 'checkbox', 'title' => 'Flaga', 'default' => 'yes' ),
			),
		);
	}
}
