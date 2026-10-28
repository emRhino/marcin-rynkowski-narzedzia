<?php
/**
 * Start testów jednostkowych: autoload i stałe WordPressa.
 *
 * @package Rynkowski\Narzedzia
 */

// phpcs:ignoreFile -- plik testowy.

// Przed autoloadem: pliki ładowane przez Composera kończą działanie bez ABSPATH.
define( 'ABSPATH', '/tmp/wordpress/' );

// Stałe czasu z WordPressa (wp-includes/default-constants.php).
define( 'MINUTE_IN_SECONDS', 60 );
define( 'HOUR_IN_SECONDS', 60 * MINUTE_IN_SECONDS );
define( 'DAY_IN_SECONDS', 24 * HOUR_IN_SECONDS );

require_once dirname( __DIR__ ) . '/vendor/autoload.php';
require_once __DIR__ . '/class-test-case.php';
