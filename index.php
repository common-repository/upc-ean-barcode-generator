<?php
/**
 * Plugin Name: UPC/EAN codes importer/generator - (basic)
 * Description: Allows to generate EAN & UPC codes for your WooCommerce plugins.
 * Text Domain: upc-ean-generator
 * Version: 2.0.1
 * Author: UkrSolution
 * Plugin URI: https://www.ukrsolution.com
 * Author URI: http://www.ukrsolution.com
 * License: GPL2
 * WC requires at least: 4.0.0
 * WC tested up to: 5.*
 */

if (!defined('ABSPATH')) {
    exit;
}



require_once __DIR__ . '/vendor/autoload.php';

try {
    UkrSolution\UpcEanGenerator\Database::checkTables();
} catch (\Throwable $th) {
}

register_activation_hook(__FILE__, function ($network_wide) {
    UkrSolution\UpcEanGenerator\Database::setupTables($network_wide);
});

add_action('wpmu_new_blog', function ($blog_id, $user_id, $domain, $path, $site_id, $meta) {
    if (is_plugin_active_for_network(plugin_basename(__FILE__))) {
        switch_to_blog($blog_id);
        UkrSolution\UpcEanGenerator\Database::createTables();
        restore_current_blog();
    }
}, 10, 6);

add_action('plugins_loaded', function () {
    $lastVersion = get_option("active-{%plugin%}-barcodes-version", "");
    if ($lastVersion !== "2.0.1") {
        UkrSolution\UpcEanGenerator\Database::createTables();
        update_option("active-{%plugin%}-barcodes-version", "2.0.1");
    }
});

add_action('before_woocommerce_init', function(){
    if (class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class)) {
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('custom_order_tables', __FILE__, true);
    }
});

new UkrSolution\UpcEanGenerator\Core();

add_filter('plugin_action_links_' . plugin_basename(__FILE__), 'plugin_action_links_upc_ean_generator');

function plugin_action_links_upc_ean_generator($links) {
    $links[] = '<a href="' . admin_url( 'admin.php?page=upc-ean-generator' ) . '">' . __('Settings', 'upc-ean-generator') . '</a>';
    return $links;
}
