<?php
/**
 * Start testów jednostkowych: autoload i stałe WordPressa.
 *
 * @package Rynkowski\Narzedzia
 */

// phpcs:ignoreFile -- plik testowy.

// Przed autoloadem: pliki ładowane przez Composera kończą działanie bez ABSPATH.
define( 'ABSPATH', '/tmp/wordpress/' );

require_once dirname( __DIR__ ) . '/vendor/autoload.php';
require_once __DIR__ . '/class-test-case.php';
