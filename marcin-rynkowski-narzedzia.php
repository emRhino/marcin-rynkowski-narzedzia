<?php
/**
 * Plugin Name:       Marcin Rynkowski – narzędzia
 * Description:       Zmiany wyglądu, opcje i funkcje dla sklepów WooCommerce na Blocksy, Stackable i ACF.
 * Version:           0.1.1
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

const VERSION = '0.1.1';
const FILE    = __FILE__;
define( 'Rynkowski\Narzedzia\DIR', plugin_dir_path( __FILE__ ) );
define( 'Rynkowski\Narzedzia\URL', plugin_dir_url( __FILE__ ) );

// Zgodność z HPOS (tabele zamówień WooCommerce).
add_action(
	'before_woocommerce_init',
	static function () {
		if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', FILE, true );
		}
	}
);

if ( is_readable( DIR . 'vendor/autoload.php' ) ) {
	require_once DIR . 'vendor/autoload.php';
}
require_once DIR . 'includes/class-plugin.php';
require_once DIR . 'includes/class-updater.php';

Updater::register();
add_action( 'plugins_loaded', array( Plugin::class, 'init' ) );
