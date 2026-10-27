<?php
// phpcs:ignoreFile -- zaślepka testowa.

class WP_Post {
	public int $ID = 0;
	public string $post_type = 'post';

	public function __construct( int $id = 0, string $post_type = 'post' ) {
		$this->ID        = $id;
		$this->post_type = $post_type;
	}
}
