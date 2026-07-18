<?php
/**
 * Plugin Name: Data Hygiene for WooCommerce
 * Description: Detect, quarantine and clean WooCommerce Analytics data corruption — orphan orders, test orders, duplicates, status mismatches — with payment gateway reconciliation. Dry-run mode and full undo log included.
 * Version: 1.1.0
 * Author: Business Khana
 * Text Domain: data-hygiene-for-woocommerce
 * Domain Path: /languages
 * Requires at least: 5.8
 * Tested up to:      7.0
 * Requires PHP: 7.4
 * Requires Plugins: woocommerce
 * WC requires at least: 5.0
 * WC tested up to: 9.4
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 *
 * @package DataHygiene
 */

defined( 'ABSPATH' ) || exit;

define( 'DATAHYG_VERSION', '1.1.0' );
define( 'DATAHYG_PLUGIN_FILE', __FILE__ );
define( 'DATAHYG_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'DATAHYG_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'DATAHYG_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

/**
 * Check if WooCommerce is active.
 */
function datahyg_check_woocommerce() {
    if ( ! class_exists( 'WooCommerce' ) ) {
        add_action( 'admin_notices', function () {
            echo '<div class="notice notice-error"><p>';
            esc_html_e( 'WooCommerce Data Hygiene requires WooCommerce to be installed and active.', 'data-hygiene-for-woocommerce' );
            echo '</p></div>';
        } );
        return false;
    }
    return true;
}

/**
 * Plugin activation.
 */
function datahyg_activate() {
    require_once DATAHYG_PLUGIN_DIR . 'includes/class-installer.php';
    DataHygiene\Installer::activate();
}
register_activation_hook( __FILE__, 'datahyg_activate' );

/**
 * Plugin deactivation.
 */
function datahyg_deactivate() {
    wp_clear_scheduled_hook( 'datahyg_weekly_scan' );
    wp_clear_scheduled_hook( 'datahyg_pro_report' );
}
register_deactivation_hook( __FILE__, 'datahyg_deactivate' );

/**
 * Initialize plugin.
 */
function datahyg_init() {
    if ( ! datahyg_check_woocommerce() ) {
        return;
    }

    // Autoload classes.
    spl_autoload_register( function ( $class ) {
        $prefix = 'DataHygiene\\';
        if ( strpos( $class, $prefix ) !== 0 ) {
            return;
        }

        $relative = substr( $class, strlen( $prefix ) );
        $parts    = explode( '\\', $relative );
        $filename = 'class-' . strtolower( str_replace( '_', '-', array_pop( $parts ) ) ) . '.php';

        $subdir = '';
        if ( ! empty( $parts ) ) {
            $subdir = strtolower( implode( '/', $parts ) ) . '/';
        }

        $file = DATAHYG_PLUGIN_DIR . 'includes/' . $subdir . $filename;
        if ( file_exists( $file ) ) {
            require_once $file;
        }
    } );

    // Boot core classes.
    DataHygiene\Admin::init();
    DataHygiene\Rest_Api::init();
    DataHygiene\Auto_Scan::init();

    // Pro layer (freemium): license client + gated features.
    DataHygiene\License::init();
    DataHygiene\Pro_Export::init();
    DataHygiene\Pro_Reports::init();
}
add_action( 'plugins_loaded', 'datahyg_init' );

/**
 * Declare HPOS compatibility.
 */
add_action( 'before_woocommerce_init', function () {
    if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
    }
} );
