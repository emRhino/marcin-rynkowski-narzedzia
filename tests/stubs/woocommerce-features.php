<?php
// phpcs:ignoreFile -- zaślepka testowa: FeaturesUtil z WooCommerce, zapamiętuje wywołania.

namespace Automattic\WooCommerce\Utilities;

class FeaturesUtil {
	/** @var array<int, array{0: string, 1: string, 2: bool}> */
	public static array $calls = array();

	public static function declare_compatibility( string $feature, string $file, bool $positive ): bool {
		self::$calls[] = array( $feature, $file, $positive );
		return true;
	}
}
