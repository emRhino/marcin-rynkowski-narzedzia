<?php
// phpcs:ignoreFile -- zaślepka testowa.

class WP_Term {
	public int $term_id = 0;
	public string $name = '';

	public function __construct( int $term_id = 0, string $name = '' ) {
		$this->term_id = $term_id;
		$this->name    = $name;
	}
}
