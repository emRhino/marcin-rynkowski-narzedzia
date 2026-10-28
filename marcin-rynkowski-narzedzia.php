<?php
/**
 * Plugin Name:       Marcin Rynkowski – narzędzia
 * Description:       Moduły dla sklepów WooCommerce na Blocksy, Stackable i ACF. Włączasz je w Narzędzia → Ustawienia.
 * Version:           0.6.1
 * Requires at least: 6.5
 * Requires PHP:      8.1
 * Requires Plugins:  woocommerce
 * Author:            Marcin Rynkowski
 * Text Domain:       marcin-rynkowski-narzedzia
 * Domain Path:       /languages
 * License:           GPL-2.0-or-later
 * WC requires at least: 8.0
 *
 * @package Rynkowski\Narzedzia
 */

namespace Rynkowski\Narzedzia;

defined( 'ABSPATH' ) || exit;

require_once __DIR__ . '/vendor/autoload.php';

Plugin::boot( __FILE__ );
