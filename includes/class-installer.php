<?php
namespace DataHygiene;

defined( 'ABSPATH' ) || exit;

class Installer {

    public static function activate() {
        self::create_tables();
        self::set_default_options();
        update_option( 'datahyg_version', DATAHYG_VERSION );
    }

    private static function create_tables() {
        global $wpdb;
        $charset = $wpdb->get_charset_collate();

        $quarantine_table     = $wpdb->prefix . 'wc_data_quarantine';
        $scan_log_table       = $wpdb->prefix . 'wc_data_scan_log';
        $reconciliation_table = $wpdb->prefix . 'wc_reconciliation_cache';
        $audit_log_table      = $wpdb->prefix . 'wc_data_audit_log';

        $sql = "
CREATE TABLE {$quarantine_table} (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    scan_id VARCHAR(36) NOT NULL,
    item_type VARCHAR(50) NOT NULL,
    item_id BIGINT NOT NULL,
    issue_type VARCHAR(100) NOT NULL,
    severity VARCHAR(20) DEFAULT 'medium',
    description TEXT,
    original_data LONGTEXT,
    status VARCHAR(20) DEFAULT 'quarantined',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    resolved_at DATETIME NULL,
    INDEX idx_scan (scan_id),
    INDEX idx_type (issue_type),
    INDEX idx_status (status)
) {$charset};

CREATE TABLE {$scan_log_table} (
    id VARCHAR(36) PRIMARY KEY,
    scan_type VARCHAR(20) NOT NULL,
    started_at DATETIME NOT NULL,
    completed_at DATETIME NULL,
    total_orders_scanned INT DEFAULT 0,
    issues_found INT DEFAULT 0,
    issues_quarantined INT DEFAULT 0,
    confidence_score DECIMAL(5,2) NULL,
    summary LONGTEXT,
    INDEX idx_date (started_at)
) {$charset};

CREATE TABLE {$audit_log_table} (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT NOT NULL,
    user_login VARCHAR(60),
    action VARCHAR(50) NOT NULL,
    item_type VARCHAR(50),
    item_id BIGINT,
    quarantine_id BIGINT NULL,
    scan_id VARCHAR(36) NULL,
    dry_run TINYINT(1) DEFAULT 0,
    payload LONGTEXT,
    ip_address VARCHAR(45),
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_user (user_id),
    INDEX idx_action (action),
    INDEX idx_date (created_at)
) {$charset};

CREATE TABLE {$reconciliation_table} (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    order_id BIGINT NOT NULL,
    wc_total DECIMAL(15,4) NULL,
    gateway_total DECIMAL(15,4) NULL,
    gateway VARCHAR(50),
    gateway_txn_id VARCHAR(255),
    discrepancy DECIMAL(15,4) NULL,
    status VARCHAR(30),
    checked_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_order (order_id),
    INDEX idx_status (status)
) {$charset};
";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta( $sql );
    }

    private static function set_default_options() {
        $defaults = array(
            'datahyg_scan_batch_size'     => 50,
            'datahyg_auto_scan_enabled'   => 'yes',
            'datahyg_auto_scan_day'       => 'monday',
            'datahyg_email_alerts'        => 'yes',
            'datahyg_alert_email'         => get_option( 'admin_email' ),
            'datahyg_test_order_emails'   => '',
            'datahyg_stripe_enabled'      => 'no',
            'datahyg_paypal_enabled'      => 'no',
            'datahyg_store_start_date'    => '',
            'datahyg_duplicate_threshold' => 60,
        );

        foreach ( $defaults as $key => $value ) {
            if ( get_option( $key ) === false ) {
                update_option( $key, $value );
            }
        }
    }
}
