<?php
// phpcs:ignoreFile -- zaślepka testowa.

class WC_Product {
	public function __construct( private int $id = 0, private string $formatted_name = '' ) {}

	public function get_id(): int {
		return $this->id;
	}

	public function get_formatted_name(): string {
		return $this->formatted_name;
	}
}
