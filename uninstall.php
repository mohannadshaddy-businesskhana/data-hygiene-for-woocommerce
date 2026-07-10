<?php
/**
 * Uninstall handler for WooCommerce Data Hygiene.
 *
 * Removes plugin tables, options and scheduled hooks only when the user has
 * explicitly opted in via the `datahyg_delete_data_on_uninstall` option.
 * Quarantined data is intentionally preserved by default to protect the user.
 *
 * @package DataHygiene
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

// Always clear scheduled hook (harmless).
wp_clear_scheduled_hook( 'datahyg_weekly_scan' );

$delete = get_option( 'datahyg_delete_data_on_uninstall' );
if ( 'yes' !== $delete ) {
	return;
}

global $wpdb;

$tables = array(
	$wpdb->prefix . 'wc_data_quarantine',
	$wpdb->prefix . 'wc_data_scan_log',
	$wpdb->prefix . 'wc_reconciliation_cache',
	$wpdb->prefix . 'wc_data_audit_log',
);

foreach ( $tables as $table ) {
	// Table names are built from $wpdb->prefix + a hard-coded suffix, so this is safe.
	$wpdb->query( "DROP TABLE IF EXISTS {$table}" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
}

$options = array(
	'datahyg_version',
	'datahyg_scan_batch_size',
	'datahyg_auto_scan_enabled',
	'datahyg_auto_scan_day',
	'datahyg_email_alerts',
	'datahyg_alert_email',
	'datahyg_test_order_emails',
	'datahyg_stripe_enabled',
	'datahyg_paypal_enabled',
	'datahyg_store_start_date',
	'datahyg_duplicate_threshold',
	'datahyg_delete_data_on_uninstall',
);

foreach ( $options as $option ) {
	delete_option( $option );
}
