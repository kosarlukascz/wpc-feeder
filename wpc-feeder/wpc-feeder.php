<?php
/*
 * Plugin Name: WPC Feeder
 * Description: New generation of plugin, which creating XML feed for Google Merchant.
 * Version: 1.0.1
 * Author: Hlavně Lukáš, trošku David a hodně moc Ukáčka z Positive Studia
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
require_once 'includes/wpc-feeder.php';
require_once 'includes/wpc-woocommerce-hooks.php';

new WPC_Woocommerce_Hooks();

add_action(
    'plugins_loaded',
    function () {
		WPCFeeder::get_instance();
	}
);
