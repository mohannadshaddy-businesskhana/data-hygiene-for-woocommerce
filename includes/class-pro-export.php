<?php
/**
 * Pro feature: full CSV export of scan history. Gated behind an active Pro license.
 *
 * @package DataHygiene
 */

namespace DataHygiene;

defined( 'ABSPATH' ) || exit;

class Pro_Export {

	const ACTION = 'datahyg_pro_export';

	public static function init() {
		add_action( 'admin_post_' . self::ACTION, array( __CLASS__, 'handle_export' ) );
	}

	/**
	 * Render an export button (Pro) or an upsell line (free). Called from the License page.
	 */
	public static function render_button() {
		if ( License::is_pro() ) {
			$url = wp_nonce_url( admin_url( 'admin-post.php?action=' . self::ACTION ), self::ACTION );
			printf(
				'<a href="%s" class="button button-primary">%s</a>',
				esc_url( $url ),
				esc_html__( 'Export scan history (CSV)', 'data-hygiene-for-woocommerce' )
			);
		} else {
			echo '<em>' . esc_html__( 'Activate Pro to export your full scan history as CSV.', 'data-hygiene-for-woocommerce' ) . '</em>';
		}
	}

	/**
	 * Stream the scan history as a CSV download (Pro only).
	 */
	public static function handle_export() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'Permission denied.', 'data-hygiene-for-woocommerce' ) );
		}
		check_admin_referer( self::ACTION );
		if ( ! License::is_pro() ) {
			wp_die( esc_html__( 'A Pro license is required for this feature.', 'data-hygiene-for-woocommerce' ) );
		}

		global $wpdb;
		$table = $wpdb->prefix . 'wc_data_scan_log';
		$rows  = $wpdb->get_results( "SELECT started_at, confidence_score, issues_found FROM {$table} ORDER BY started_at DESC", ARRAY_A );

		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename=data-hygiene-scan-history.csv' );

		$out = fopen( 'php://output', 'w' );
		fputcsv( $out, array( 'started_at', 'confidence_score', 'issues_found' ) );
		if ( $rows ) {
			foreach ( $rows as $row ) {
				fputcsv(
					$out,
					array(
						(string) $row['started_at'],
						(string) $row['confidence_score'],
						(string) $row['issues_found'],
					)
				);
			}
		}
		fclose( $out );
		exit;
	}
}
