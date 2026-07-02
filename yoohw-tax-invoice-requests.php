<?php
/**
 * Plugin Name: Tax Invoice Requests for WooCommerce
 * Description: Allows customers to request Tax invoices from WooCommerce orders and automatically generate PDF invoices.
 * Version: 1.0.1
 * Author: YoOhw.com
 * Author URI: https://yoohw.com
 * Text Domain: yoohw-tax-invoice-requests
 * Requires Plugins: woocommerce
 * Requires at least: 6.0
 * Tested up to: 7.0
 * Requires PHP: 7.4
 * WC requires at least: 7.0
 * WC tested up to: 10.7
 * License: GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'YOOHW_TIR_PATH', plugin_dir_path( __FILE__ ) );
define( 'YOOHW_TIR_URL', plugin_dir_url( __FILE__ ) );

$yoohw_tir_plugin_data = get_file_data(
	__FILE__,
	[
		'Version' => 'Version',
	]
);

define( 'YOOHW_TIR_VERSION', $yoohw_tir_plugin_data['Version'] );

if ( file_exists( YOOHW_TIR_PATH . 'vendor/autoload.php' ) ) {
	require_once YOOHW_TIR_PATH . 'vendor/autoload.php';
}

add_action( 'before_woocommerce_init', function() {
	if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {

		// HPOS compatibility
		\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility(
			'custom_order_tables',
			__FILE__,
			true
		);

		// Cart & Checkout Blocks compatibility
		\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility(
			'cart_checkout_blocks',
			__FILE__,
			true
		);
	}
} );

require_once YOOHW_TIR_PATH . 'includes/class-plugin.php';

add_action( 'plugins_loaded', function() {
	if ( class_exists( 'WooCommerce' ) ) {
		new YoOhw_Tir_Plugin();
	}
} );
