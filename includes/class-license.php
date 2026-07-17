<?php
/**
 * Pro license client for Data Hygiene for WooCommerce.
 *
 * Stores the purchased key, verifies it against the AWF license service, and
 * exposes is_pro() for feature gating. Self-contained PHP admin page (no build step).
 *
 * @package DataHygiene
 */

namespace DataHygiene;

defined( 'ABSPATH' ) || exit;

class License {

	const OPTION_KEY = 'datahyg_license_key';
	const TRANSIENT  = 'datahyg_license_check';
	const PRODUCT_ID = 'woo-data-hygiene';
	const VERIFY_URL = 'https://telegram-bot-beta-eight.vercel.app/api/license-verify';

	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'register_menu' ), 20 );
		add_action( 'admin_init', array( __CLASS__, 'handle_form' ) );
	}

	public static function register_menu() {
		add_submenu_page(
			'woocommerce',
			__( 'Data Hygiene Pro', 'data-hygiene-for-woocommerce' ),
			__( 'Data Hygiene Pro', 'data-hygiene-for-woocommerce' ),
			'manage_woocommerce',
			'data-hygiene-license',
			array( __CLASS__, 'render_page' )
		);
	}

	/**
	 * Whether a valid Pro license is active. Cached for 12h to avoid per-request calls.
	 *
	 * @return bool
	 */
	public static function is_pro() {
		$cached = get_transient( self::TRANSIENT );
		if ( 'active' === $cached ) {
			return true;
		}
		if ( 'invalid' === $cached ) {
			return false;
		}

		$key = (string) get_option( self::OPTION_KEY, '' );
		if ( '' === $key ) {
			set_transient( self::TRANSIENT, 'invalid', 12 * HOUR_IN_SECONDS );
			return false;
		}

		$ok = self::verify( $key );
		set_transient( self::TRANSIENT, $ok ? 'active' : 'invalid', 12 * HOUR_IN_SECONDS );
		return $ok;
	}

	/**
	 * Verify a key against the license service.
	 *
	 * @param string $key License key.
	 * @return bool
	 */
	public static function verify( $key ) {
		$resp = wp_remote_post(
			self::VERIFY_URL,
			array(
				'timeout' => 15,
				'headers' => array( 'Content-Type' => 'application/json' ),
				'body'    => wp_json_encode(
					array(
						'license_key' => $key,
						'product_id'  => self::PRODUCT_ID,
						'site_url'    => home_url(),
					)
				),
			)
		);

		if ( is_wp_error( $resp ) ) {
			return false;
		}

		$data = json_decode( wp_remote_retrieve_body( $resp ), true );
		return ! empty( $data['valid'] );
	}

	/**
	 * Handle the license form submission (save + verify).
	 */
	public static function handle_form() {
		if ( ! isset( $_POST['datahyg_license_nonce'] ) ) {
			return;
		}
		if ( ! wp_verify_nonce( sanitize_key( wp_unslash( $_POST['datahyg_license_nonce'] ) ), 'datahyg_save_license' ) ) {
			return;
		}
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}

		$key = isset( $_POST['datahyg_license_key'] )
			? sanitize_text_field( wp_unslash( $_POST['datahyg_license_key'] ) )
			: '';

		update_option( self::OPTION_KEY, $key );
		delete_transient( self::TRANSIENT );
		$ok = ( '' !== $key ) ? self::verify( $key ) : false;
		set_transient( self::TRANSIENT, $ok ? 'active' : 'invalid', 12 * HOUR_IN_SECONDS );

		add_action(
			'admin_notices',
			function () use ( $ok ) {
				$class = $ok ? 'notice-success' : 'notice-error';
				$msg   = $ok
					? __( 'License activated — Pro features are now enabled.', 'data-hygiene-for-woocommerce' )
					: __( 'License could not be verified. Please check the key and try again.', 'data-hygiene-for-woocommerce' );
				printf( '<div class="notice %s is-dismissible"><p>%s</p></div>', esc_attr( $class ), esc_html( $msg ) );
			}
		);
	}

	/**
	 * Render the Pro / license admin page.
	 */
	public static function render_page() {
		$key    = (string) get_option( self::OPTION_KEY, '' );
		$active = self::is_pro();
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Data Hygiene Pro', 'data-hygiene-for-woocommerce' ); ?></h1>
			<p>
				<?php if ( $active ) : ?>
					<span style="color:#00a32a;font-weight:600;">&#10004; <?php esc_html_e( 'Pro is active', 'data-hygiene-for-woocommerce' ); ?></span>
				<?php else : ?>
					<span style="color:#d63638;font-weight:600;"><?php esc_html_e( 'Pro is not active', 'data-hygiene-for-woocommerce' ); ?></span>
				<?php endif; ?>
			</p>
			<form method="post">
				<?php wp_nonce_field( 'datahyg_save_license', 'datahyg_license_nonce' ); ?>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="datahyg_license_key"><?php esc_html_e( 'License key', 'data-hygiene-for-woocommerce' ); ?></label></th>
						<td>
							<input name="datahyg_license_key" id="datahyg_license_key" type="text" class="regular-text"
								value="<?php echo esc_attr( $key ); ?>" placeholder="PRO-XXXX-XXXX-XXXX-XXXX" />
							<p class="description"><?php esc_html_e( 'Enter the license key from your purchase to unlock Pro features.', 'data-hygiene-for-woocommerce' ); ?></p>
						</td>
					</tr>
				</table>
				<?php submit_button( __( 'Save & Verify', 'data-hygiene-for-woocommerce' ) ); ?>
			</form>
			<hr>
			<h2><?php esc_html_e( 'Pro features', 'data-hygiene-for-woocommerce' ); ?></h2>
			<ul style="list-style:disc;padding-left:20px;">
				<li><?php esc_html_e( 'Full CSV export of your scan history', 'data-hygiene-for-woocommerce' ); ?></li>
				<li><?php esc_html_e( 'Scheduled data-health reports by email (weekly / monthly)', 'data-hygiene-for-woocommerce' ); ?></li>
			</ul>
			<p><?php Pro_Export::render_button(); ?></p>
		</div>
		<?php
	}
}
