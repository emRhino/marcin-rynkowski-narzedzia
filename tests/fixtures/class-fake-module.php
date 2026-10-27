<?php
// phpcs:ignoreFile -- moduł testowy.

namespace Rynkowski\Narzedzia\Tests;

use Rynkowski\Narzedzia\Module;

final class Fake_Module implements Module {

	/** @var string[] Dziennik wywołań wspólny dla wszystkich instancji. */
	public static array $log = array();

	/**
	 * @param string[]                     $requires
	 * @param array<int, callable(): void> $migrations
	 */
	public function __construct(
		private string $slug,
		private array $requires = array(),
		private int $data_version = 1,
		private array $migrations = array()
	) {}

	public function slug(): string {
		return $this->slug;
	}

	public function name(): string {
		return 'Moduł ' . $this->slug;
	}

	public function description(): string {
		return 'Opis ' . $this->slug;
	}

	public function requires(): array {
		return $this->requires;
	}

	public function data_version(): int {
		return $this->data_version;
	}

	public function migrations(): array {
		return $this->migrations;
	}

	public function register(): void {
		self::$log[] = 'register:' . $this->slug;
	}

	public function uninstall(): void {
		self::$log[] = 'uninstall:' . $this->slug;
	}
}
