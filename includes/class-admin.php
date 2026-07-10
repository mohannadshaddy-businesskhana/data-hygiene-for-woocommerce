<?php
namespace DataHygiene;

defined( 'ABSPATH' ) || exit;

class Admin {

    public static function init() {
        add_action( 'admin_menu', array( __CLASS__, 'register_menu' ) );
        add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_assets' ) );
        add_action( 'wp_dashboard_setup', array( __CLASS__, 'add_dashboard_widget' ) );
    }

    public static function register_menu() {
        add_submenu_page(
            'woocommerce',
            __( 'Data Hygiene', 'data-hygiene-for-woocommerce' ),
            __( 'Data Hygiene', 'data-hygiene-for-woocommerce' ),
            'manage_woocommerce',
            'data-hygiene-for-woocommerce',
            array( __CLASS__, 'render_page' )
        );
    }

    public static function render_page() {
        echo '<div id="datahyg-app" class="wrap"></div>';
    }

    public static function enqueue_assets( $hook ) {
        if ( 'woocommerce_page_data-hygiene-for-woocommerce' !== $hook ) {
            return;
        }

        $asset_file = DATAHYG_PLUGIN_DIR . 'build/index.asset.php';
        $assets     = file_exists( $asset_file )
            ? require $asset_file
            : array( 'dependencies' => array(), 'version' => DATAHYG_VERSION );

        wp_enqueue_script(
            'datahyg-app',
            DATAHYG_PLUGIN_URL . 'build/index.js',
            $assets['dependencies'],
            $assets['version'],
            true
        );

        wp_enqueue_style(
            'datahyg-app',
            DATAHYG_PLUGIN_URL . 'build/style-index.css',
            array( 'wp-components' ),
            $assets['version']
        );

        wp_localize_script( 'datahyg-app', 'wdhData', array(
            'restUrl'  => rest_url( 'wdh/v1/' ),
            'nonce'    => wp_create_nonce( 'wp_rest' ),
            'adminUrl' => admin_url(),
        ) );
    }

    public static function add_dashboard_widget() {
        wp_add_dashboard_widget(
            'datahyg_confidence_widget',
            __( 'Data Confidence Score', 'data-hygiene-for-woocommerce' ),
            array( __CLASS__, 'render_dashboard_widget' )
        );
    }

    public static function render_dashboard_widget() {
        global $wpdb;
        $table = $wpdb->prefix . 'wc_data_scan_log';
        $last  = $wpdb->get_row( "SELECT confidence_score, started_at, issues_found FROM {$table} ORDER BY started_at DESC LIMIT 1" );

        if ( ! $last ) {
            echo '<p>' . esc_html__( 'No scans have been run yet.', 'data-hygiene-for-woocommerce' ) . '</p>';
            echo '<a href="' . esc_url( admin_url( 'admin.php?page=data-hygiene-for-woocommerce' ) ) . '" class="button button-primary">'
                . esc_html__( 'Run First Scan', 'data-hygiene-for-woocommerce' ) . '</a>';
            return;
        }

        $score = floatval( $last->confidence_score );
        $color = $score >= 80 ? '#00a32a' : ( $score >= 50 ? '#dba617' : '#d63638' );

        printf(
            '<div style="text-align:center;padding:10px;">
                <div style="font-size:48px;font-weight:bold;color:%s;">%.0f%%</div>
                <p>%s: %s</p>
                <p>%s: %d</p>
                <a href="%s" class="button">%s</a>
            </div>',
            esc_attr( $color ),
            esc_html( $score ),
            esc_html__( 'Last scan', 'data-hygiene-for-woocommerce' ),
            esc_html( wp_date( get_option( 'date_format' ), strtotime( $last->started_at ) ) ),
            esc_html__( 'Issues found', 'data-hygiene-for-woocommerce' ),
            intval( $last->issues_found ),
            esc_url( admin_url( 'admin.php?page=data-hygiene-for-woocommerce' ) ),
            esc_html__( 'View Details', 'data-hygiene-for-woocommerce' )
        );
    }
}
