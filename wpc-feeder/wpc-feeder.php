<?php
/*
 * Plugin Name: WPC Feeder
 * Description: New generation of plugin, which creating XML feed for Google Merchant.
 * Version: 1.0.0
 * Author: Hlavně Lukáš, trošku David
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
include_once( 'includes/wpc-feeder.php' );
add_action( 'plugins_loaded', function () {
	WPCFeeder::get_instance();
} );
