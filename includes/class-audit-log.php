<?php
/**
 * Audit log for every destructive operation.
 *
 * @package DataHygiene
 */

namespace DataHygiene;

defined( 'ABSPATH' ) || exit;

class Audit_Log {

	/**
	 * Record a destructive (or dry-run) action.
	 *
	 * @param string $action     Action key, e.g. 'quarantine', 'restore', 'delete', 'bulk_quarantine'.
	 * @param array  $context    Optional context: item_type, item_id, quarantine_id, scan_id, payload.
	 * @param bool   $dry_run    Whether this was a dry-run (no destructive write).
	 * @return int Inserted row ID (0 on failure).
	 */
	public static function record( string $action, array $context = array(), bool $dry_run = false ) : int {
		global $wpdb;

		$user    = wp_get_current_user();
		$user_id = $user instanceof \WP_User ? (int) $user->ID : 0;
		$login   = $user instanceof \WP_User ? $user->user_login : '';
		$ip      = self::get_client_ip();

		$wpdb->insert(
			$wpdb->prefix . 'wc_data_audit_log',
			array(
				'user_id'       => $user_id,
				'user_login'    => substr( $login, 0, 60 ),
				'action'        => substr( sanitize_key( $action ), 0, 50 ),
				'item_type'     => isset( $context['item_type'] ) ? substr( sanitize_key( $context['item_type'] ), 0, 50 ) : '',
				'item_id'       => isset( $context['item_id'] ) ? (int) $context['item_id'] : 0,
				'quarantine_id' => isset( $context['quarantine_id'] ) ? (int) $context['quarantine_id'] : null,
				'scan_id'       => isset( $context['scan_id'] ) ? sanitize_text_field( $context['scan_id'] ) : null,
				'dry_run'       => $dry_run ? 1 : 0,
				'payload'       => isset( $context['payload'] ) ? wp_json_encode( $context['payload'] ) : null,
				'ip_address'    => $ip,
				'created_at'    => current_time( 'mysql' ),
			),
			array( '%d', '%s', '%s', '%s', '%d', '%d', '%s', '%d', '%s', '%s', '%s' )
		);

		return (int) $wpdb->insert_id;
	}

	/**
	 * List recent audit entries.
	 *
	 * @param int $limit Max rows.
	 * @return array
	 */
	public static function recent( int $limit = 100 ) : array {
		global $wpdb;
		$limit = min( 500, max( 1, $limit ) );
		$table = $wpdb->prefix . 'wc_data_audit_log';

		return (array) $wpdb->get_results(
			$wpdb->prepare( "SELECT * FROM {$table} ORDER BY created_at DESC LIMIT %d", $limit )
		);
	}

	/**
	 * Best-effort client IP, sanitized.
	 */
	private static function get_client_ip() : string {
		$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
		return filter_var( $ip, FILTER_VALIDATE_IP ) ? $ip : '';
	}
}
