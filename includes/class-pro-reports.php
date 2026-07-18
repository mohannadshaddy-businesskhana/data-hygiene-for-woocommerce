<?php
/**
 * Pro feature: scheduled data-health report by email. Gated behind an active Pro license.
 *
 * @package DataHygiene
 */

namespace DataHygiene;

defined( 'ABSPATH' ) || exit;

class Pro_Reports {

	const CRON_HOOK = 'datahyg_pro_report';
	const OPT_FREQ  = 'datahyg_report_freq';   // 'off' | 'weekly' | 'monthly'.
	const OPT_EMAIL = 'datahyg_report_email';

	public static function init() {
		add_filter( 'cron_schedules', array( __CLASS__, 'add_monthly_schedule' ) );
		add_action( 'admin_init', array( __CLASS__, 'handle_settings' ) );
		add_action( 'admin_init', array( __CLASS__, 'sync_schedule' ) );
		add_action( self::CRON_HOOK, array( __CLASS__, 'send_report' ) );
	}

	/**
	 * Add a monthly cron interval (WP core has no 'monthly').
	 *
	 * @param array $schedules Existing schedules.
	 * @return array
	 */
	public static function add_monthly_schedule( $schedules ) {
		if ( ! isset( $schedules['monthly'] ) ) {
			$schedules['monthly'] = array(
				'interval' => 30 * DAY_IN_SECONDS,
				'display'  => __( 'Once Monthly', 'data-hygiene-for-woocommerce' ),
			);
		}
		return $schedules;
	}

	/**
	 * Current report frequency (validated).
	 *
	 * @return string
	 */
	public static function get_freq() {
		$f = (string) get_option( self::OPT_FREQ, 'off' );
		return in_array( $f, array( 'off', 'weekly', 'monthly' ), true ) ? $f : 'off';
	}

	/**
	 * Keep the cron event in sync with the settings and the license state.
	 */
	public static function sync_schedule() {
		$freq   = self::get_freq();
		$active = License::is_pro();
		$next   = wp_next_scheduled( self::CRON_HOOK );

		if ( ! $active || 'off' === $freq ) {
			if ( $next ) {
				wp_unschedule_event( $next, self::CRON_HOOK );
			}
			return;
		}

		$current = $next ? wp_get_schedule( self::CRON_HOOK ) : false;
		if ( $current !== $freq ) {
			if ( $next ) {
				wp_unschedule_event( $next, self::CRON_HOOK );
			}
			wp_schedule_event( time() + HOUR_IN_SECONDS, $freq, self::CRON_HOOK );
		}
	}

	/**
	 * Handle the report settings form (rendered on the Pro page).
	 */
	public static function handle_settings() {
		if ( ! isset( $_POST['datahyg_report_nonce'] ) ) {
			return;
		}
		if ( ! wp_verify_nonce( sanitize_key( wp_unslash( $_POST['datahyg_report_nonce'] ) ), 'datahyg_save_report' ) ) {
			return;
		}
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}

		$freq  = isset( $_POST['datahyg_report_freq'] ) ? sanitize_key( wp_unslash( $_POST['datahyg_report_freq'] ) ) : 'off';
		$freq  = in_array( $freq, array( 'off', 'weekly', 'monthly' ), true ) ? $freq : 'off';
		$email = isset( $_POST['datahyg_report_email'] ) ? sanitize_email( wp_unslash( $_POST['datahyg_report_email'] ) ) : '';

		update_option( self::OPT_FREQ, $freq );
		update_option( self::OPT_EMAIL, $email );
		self::sync_schedule();
	}

	/**
	 * Render the report settings (Pro) or an upsell (free). Called from the Pro page.
	 */
	public static function render_settings() {
		if ( ! License::is_pro() ) {
			echo '<p><em>' . esc_html__( 'Activate Pro to schedule automatic data-health reports by email.', 'data-hygiene-for-woocommerce' ) . '</em></p>';
			return;
		}
		$freq  = self::get_freq();
		$email = (string) get_option( self::OPT_EMAIL, get_option( 'admin_email' ) );
		?>
		<form method="post">
			<?php wp_nonce_field( 'datahyg_save_report', 'datahyg_report_nonce' ); ?>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><label for="datahyg_report_freq"><?php esc_html_e( 'Report frequency', 'data-hygiene-for-woocommerce' ); ?></label></th>
					<td>
						<select name="datahyg_report_freq" id="datahyg_report_freq">
							<option value="off" <?php selected( $freq, 'off' ); ?>><?php esc_html_e( 'Off', 'data-hygiene-for-woocommerce' ); ?></option>
							<option value="weekly" <?php selected( $freq, 'weekly' ); ?>><?php esc_html_e( 'Weekly', 'data-hygiene-for-woocommerce' ); ?></option>
							<option value="monthly" <?php selected( $freq, 'monthly' ); ?>><?php esc_html_e( 'Monthly', 'data-hygiene-for-woocommerce' ); ?></option>
						</select>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="datahyg_report_email"><?php esc_html_e( 'Send to email', 'data-hygiene-for-woocommerce' ); ?></label></th>
					<td><input type="email" name="datahyg_report_email" id="datahyg_report_email" class="regular-text" value="<?php echo esc_attr( $email ); ?>" /></td>
				</tr>
			</table>
			<?php submit_button( __( 'Save report settings', 'data-hygiene-for-woocommerce' ) ); ?>
		</form>
		<?php
	}

	/**
	 * Cron callback: build and email the data-health report (Pro only).
	 */
	public static function send_report() {
		if ( ! License::is_pro() ) {
			return;
		}

		$email = (string) get_option( self::OPT_EMAIL, get_option( 'admin_email' ) );
		if ( ! is_email( $email ) ) {
			$email = (string) get_option( 'admin_email' );
		}

		global $wpdb;
		$table = $wpdb->prefix . 'wc_data_scan_log';
		$rows  = $wpdb->get_results( "SELECT started_at, confidence_score, issues_found FROM {$table} ORDER BY started_at DESC LIMIT 10", ARRAY_A );

		$site = wp_specialchars_decode( get_option( 'blogname' ), ENT_QUOTES );

		if ( empty( $rows ) ) {
			$body = '<p>' . esc_html__( 'No scans have been recorded yet. Run a scan to populate your report.', 'data-hygiene-for-woocommerce' ) . '</p>';
		} else {
			$latest = $rows[0];
			$body   = '<h2>' . esc_html(
				/* translators: %s: site name */
				sprintf( __( 'Data health report — %s', 'data-hygiene-for-woocommerce' ), $site )
			) . '</h2>';
			$body  .= '<p>' . esc_html(
				/* translators: 1: confidence score, 2: number of issues */
				sprintf( __( 'Latest confidence score: %1$s%% — %2$d issues found.', 'data-hygiene-for-woocommerce' ), $latest['confidence_score'], (int) $latest['issues_found'] )
			) . '</p>';
			$body  .= '<table cellpadding="6" border="1" style="border-collapse:collapse;"><tr><th>'
				. esc_html__( 'Date', 'data-hygiene-for-woocommerce' ) . '</th><th>'
				. esc_html__( 'Score', 'data-hygiene-for-woocommerce' ) . '</th><th>'
				. esc_html__( 'Issues', 'data-hygiene-for-woocommerce' ) . '</th></tr>';
			foreach ( $rows as $r ) {
				$body .= '<tr><td>' . esc_html( $r['started_at'] ) . '</td><td>' . esc_html( $r['confidence_score'] ) . '%</td><td>' . esc_html( (string) (int) $r['issues_found'] ) . '</td></tr>';
			}
			$body .= '</table>';
		}

		$subject = sprintf(
			/* translators: %s: site name */
			__( '[%s] Data Hygiene report', 'data-hygiene-for-woocommerce' ),
			$site
		);
		$headers = array( 'Content-Type: text/html; charset=UTF-8' );
		wp_mail( $email, $subject, $body, $headers );
	}
}
