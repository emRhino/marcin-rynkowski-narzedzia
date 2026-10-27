<?php
/**
 * Odinstalowanie wtyczki: usuwa dane tylko za zgodą właściciela sklepu.
 *
 * @package Rynkowski\Narzedzia
 */

namespace Rynkowski\Narzedzia;

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

require_once __DIR__ . '/vendor/autoload.php';

Uninstaller::run( __DIR__ . '/moduly' );
