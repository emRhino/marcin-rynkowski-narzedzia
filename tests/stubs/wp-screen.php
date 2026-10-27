<?php
// phpcs:ignoreFile -- zaślepka testowa.

class WP_Screen {
	public string $id = '';
	public string $base = '';
	public string $post_type = '';

	public function __construct( string $id = '', string $base = '', string $post_type = '' ) {
		$this->id        = $id;
		$this->base      = $base;
		$this->post_type = $post_type;
	}
}
