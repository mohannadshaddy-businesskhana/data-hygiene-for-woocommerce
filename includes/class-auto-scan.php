<?php
namespace DataHygiene;

defined( 'ABSPATH' ) || exit;

class Auto_Scan {

    public static function init() {
        add_action( 'datahyg_weekly_scan', array( __CLASS__, 'run_scheduled_scan' ) );
        add_action( 'admin_init', array( __CLASS__, 'maybe_schedule' ) );
    }

    /**
     * Schedule the weekly scan if enabled and not already scheduled.
     */
    public static function maybe_schedule() {
        if ( get_option( 'datahyg_auto_scan_enabled', 'yes' ) !== 'yes' ) {
            wp_clear_scheduled_hook( 'datahyg_weekly_scan' );
            return;
        }

        if ( ! wp_next_scheduled( 'datahyg_weekly_scan' ) ) {
            $day  = get_option( 'datahyg_auto_scan_day', 'monday' );
            $next = strtotime( "next {$day} 03:00:00" );
            wp_schedule_event( $next, 'weekly', 'datahyg_weekly_scan' );
        }
    }

    /**
     * Reschedule after settings change.
     */
    public static function reschedule() {
        wp_clear_scheduled_hook( 'datahyg_weekly_scan' );
        self::maybe_schedule();
    }

    /**
     * Run the scheduled scan.
     */
    public static function run_scheduled_scan() {
        $scanner = new Scanner( 'scheduled' );
        $result  = $scanner->run();

        // Check if score dropped — send alert.
        if ( get_option( 'datahyg_email_alerts', 'yes' ) === 'yes' ) {
            self::maybe_send_alert( $result );
        }
    }

    /**
     * Send email alert if confidence score is low or dropped.
     */
    private static function maybe_send_alert( array $result ) {
        global $wpdb;

        $score = $result['confidence_score'];
        $email = get_option( 'datahyg_alert_email', get_option( 'admin_email' ) );

        if ( ! $email ) {
            return;
        }

        // Get previous score.
        $table      = $wpdb->prefix . 'wc_data_scan_log';
        $prev_score = $wpdb->get_var( $wpdb->prepare(
            "SELECT confidence_score FROM {$table} WHERE id != %s ORDER BY started_at DESC LIMIT 1",
            $result['scan_id']
        ) );

        $should_alert = false;
        $reason       = '';

        // Alert if score is below 70.
        if ( $score < 70 ) {
            $should_alert = true;
            $reason       = sprintf(
                /* translators: %.0f: data confidence score percentage */
                __( 'Your data confidence score is %.0f%% (below 70%%).', 'data-hygiene-for-woocommerce' ),
                $score
            );
        }

        // Alert if score dropped by more than 10 points.
        if ( $prev_score !== null && ( floatval( $prev_score ) - $score ) > 10 ) {
            $should_alert = true;
            $reason       = sprintf(
                /* translators: 1: previous confidence score, 2: current confidence score */
                __( 'Your data confidence score dropped from %1$.0f%% to %2$.0f%%.', 'data-hygiene-for-woocommerce' ),
                $prev_score,
                $score
            );
        }

        if ( ! $should_alert ) {
            return;
        }

        $subject = sprintf(
            /* translators: %s: site name */
            __( '[%s] WooCommerce Data Hygiene Alert', 'data-hygiene-for-woocommerce' ),
            get_bloginfo( 'name' )
        );

        $body = sprintf(
            "%s\n\n%s\n\n%s: %d\n%s: %d\n\n%s: %s\n\n%s",
            $reason,
            __( 'Scan Summary:', 'data-hygiene-for-woocommerce' ),
            __( 'Orders Scanned', 'data-hygiene-for-woocommerce' ),
            $result['total_scanned'],
            __( 'Issues Found', 'data-hygiene-for-woocommerce' ),
            $result['issues_found'],
            __( 'Confidence Score', 'data-hygiene-for-woocommerce' ),
            round( $score, 1 ) . '%',
            sprintf(
                /* translators: %s: admin page URL */
                __( 'View details: %s', 'data-hygiene-for-woocommerce' ),
                admin_url( 'admin.php?page=data-hygiene-for-woocommerce' )
            )
        );

        wp_mail( $email, $subject, $body );
    }
}
