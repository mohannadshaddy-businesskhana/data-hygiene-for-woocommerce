<?php
namespace DataHygiene;

defined( 'ABSPATH' ) || exit;

class Rest_Api {

    public static function init() {
        add_action( 'rest_api_init', array( __CLASS__, 'register_routes' ) );
    }

    public static function register_routes() {
        $ns = 'wdh/v1';

        // Scan endpoints.
        register_rest_route( $ns, '/scan', array(
            'methods'             => 'POST',
            'callback'            => array( __CLASS__, 'run_scan' ),
            'permission_callback' => array( __CLASS__, 'check_write' ),
            'args'                => array(
                'type' => array(
                    'default'           => 'full',
                    'sanitize_callback' => 'sanitize_text_field',
                ),
            ),
        ) );

        register_rest_route( $ns, '/scan/(?P<id>[a-f0-9-]+)', array(
            'methods'             => 'GET',
            'callback'            => array( __CLASS__, 'get_scan' ),
            'permission_callback' => array( __CLASS__, 'check_admin' ),
        ) );

        register_rest_route( $ns, '/scans', array(
            'methods'             => 'GET',
            'callback'            => array( __CLASS__, 'list_scans' ),
            'permission_callback' => array( __CLASS__, 'check_admin' ),
        ) );

        // Quarantine endpoints.
        register_rest_route( $ns, '/quarantine', array(
            'methods'             => 'GET',
            'callback'            => array( __CLASS__, 'list_quarantine' ),
            'permission_callback' => array( __CLASS__, 'check_admin' ),
            'args'                => array(
                'status'     => array( 'default' => 'quarantined' ),
                'issue_type' => array( 'default' => '' ),
                'page'       => array( 'default' => 1 ),
                'per_page'   => array( 'default' => 20 ),
            ),
        ) );

        register_rest_route( $ns, '/quarantine/(?P<id>\d+)/restore', array(
            'methods'             => 'POST',
            'callback'            => array( __CLASS__, 'restore_item' ),
            'permission_callback' => array( __CLASS__, 'check_write' ),
            'args'                => array(
                'dry_run' => array( 'default' => false, 'sanitize_callback' => 'rest_sanitize_boolean' ),
            ),
        ) );

        register_rest_route( $ns, '/quarantine/(?P<id>\d+)/delete', array(
            'methods'             => 'DELETE',
            'callback'            => array( __CLASS__, 'delete_item' ),
            'permission_callback' => array( __CLASS__, 'check_write' ),
            'args'                => array(
                'dry_run' => array( 'default' => false, 'sanitize_callback' => 'rest_sanitize_boolean' ),
            ),
        ) );

        register_rest_route( $ns, '/quarantine/bulk', array(
            'methods'             => 'POST',
            'callback'            => array( __CLASS__, 'bulk_quarantine' ),
            'permission_callback' => array( __CLASS__, 'check_write' ),
            'args'                => array(
                'scan_id'     => array( 'required' => true, 'sanitize_callback' => 'sanitize_text_field' ),
                'issue_types' => array( 'default' => array() ),
                'dry_run'     => array( 'default' => false, 'sanitize_callback' => 'rest_sanitize_boolean' ),
            ),
        ) );

        register_rest_route( $ns, '/quarantine/bulk-restore', array(
            'methods'             => 'POST',
            'callback'            => array( __CLASS__, 'bulk_restore' ),
            'permission_callback' => array( __CLASS__, 'check_write' ),
            'args'                => array(
                'ids'     => array( 'required' => true ),
                'dry_run' => array( 'default' => false, 'sanitize_callback' => 'rest_sanitize_boolean' ),
            ),
        ) );

        // Reconciliation endpoints.
        register_rest_route( $ns, '/reconcile', array(
            'methods'             => 'POST',
            'callback'            => array( __CLASS__, 'run_reconciliation' ),
            'permission_callback' => array( __CLASS__, 'check_write' ),
            'args'                => array(
                'gateway' => array( 'required' => true ),
                'from'    => array( 'default' => '' ),
                'to'      => array( 'default' => '' ),
            ),
        ) );

        register_rest_route( $ns, '/reconciliation', array(
            'methods'             => 'GET',
            'callback'            => array( __CLASS__, 'get_reconciliation' ),
            'permission_callback' => array( __CLASS__, 'check_admin' ),
            'args'                => array(
                'status'  => array( 'default' => '' ),
                'gateway' => array( 'default' => '' ),
            ),
        ) );

        // Settings endpoints.
        register_rest_route( $ns, '/settings', array(
            array(
                'methods'             => 'GET',
                'callback'            => array( __CLASS__, 'get_settings' ),
                'permission_callback' => array( __CLASS__, 'check_admin' ),
            ),
            array(
                'methods'             => 'POST',
                'callback'            => array( __CLASS__, 'update_settings' ),
                'permission_callback' => array( __CLASS__, 'check_write' ),
            ),
        ) );

        // Dashboard summary.
        register_rest_route( $ns, '/dashboard', array(
            'methods'             => 'GET',
            'callback'            => array( __CLASS__, 'get_dashboard' ),
            'permission_callback' => array( __CLASS__, 'check_admin' ),
        ) );

        // Audit log.
        register_rest_route( $ns, '/audit-log', array(
            'methods'             => 'GET',
            'callback'            => array( __CLASS__, 'get_audit_log' ),
            'permission_callback' => array( __CLASS__, 'check_admin' ),
            'args'                => array(
                'limit' => array( 'default' => 100, 'sanitize_callback' => 'absint' ),
            ),
        ) );
    }

    /**
     * Read capability check (GET endpoints).
     */
    public static function check_admin() {
        return current_user_can( 'manage_woocommerce' );
    }

    /**
     * Write capability check (destructive endpoints). Requires capability AND a valid REST nonce.
     */
    public static function check_write() {
        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            return false;
        }
        $nonce = '';
        if ( isset( $_SERVER['HTTP_X_WP_NONCE'] ) ) {
            $nonce = sanitize_text_field( wp_unslash( $_SERVER['HTTP_X_WP_NONCE'] ) );
        }
        return $nonce && wp_verify_nonce( $nonce, 'wp_rest' );
    }

    // ── Scan ──

    public static function run_scan( $request ) {
        $type    = $request->get_param( 'type' );
        $scanner = new Scanner( $type );
        $result  = $scanner->run();

        return rest_ensure_response( $result );
    }

    public static function get_scan( $request ) {
        global $wpdb;
        $id    = $request['id'];
        $table = $wpdb->prefix . 'wc_data_scan_log';
        $scan  = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %s", $id ) );

        if ( ! $scan ) {
            return new \WP_Error( 'not_found', 'Scan not found', array( 'status' => 404 ) );
        }

        $scan->summary = json_decode( $scan->summary, true );

        // Get issues for this scan.
        $q_table = $wpdb->prefix . 'wc_data_quarantine';
        $issues  = $wpdb->get_results( $wpdb->prepare(
            "SELECT * FROM {$q_table} WHERE scan_id = %s ORDER BY severity DESC, id ASC",
            $id
        ) );

        $scan->issues = $issues;

        return rest_ensure_response( $scan );
    }

    public static function list_scans( $request ) {
        global $wpdb;
        $table = $wpdb->prefix . 'wc_data_scan_log';
        $scans = $wpdb->get_results( "SELECT * FROM {$table} ORDER BY started_at DESC LIMIT 50" );

        foreach ( $scans as &$scan ) {
            $scan->summary = json_decode( $scan->summary, true );
        }

        return rest_ensure_response( $scans );
    }

    // ── Quarantine ──

    public static function list_quarantine( $request ) {
        global $wpdb;
        $table    = $wpdb->prefix . 'wc_data_quarantine';
        $status   = sanitize_text_field( $request->get_param( 'status' ) );
        $type     = sanitize_text_field( $request->get_param( 'issue_type' ) );
        $page     = max( 1, intval( $request->get_param( 'page' ) ) );
        $per_page = min( 100, max( 1, intval( $request->get_param( 'per_page' ) ) ) );
        $offset   = ( $page - 1 ) * $per_page;

        $where = array( '1=1' );
        $args  = array();

        if ( $status ) {
            $where[] = 'status = %s';
            $args[]  = $status;
        }
        if ( $type ) {
            $where[] = 'issue_type = %s';
            $args[]  = $type;
        }

        $where_sql = implode( ' AND ', $where );

        $total = (int) $wpdb->get_var(
            $args
                ? $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE {$where_sql}", ...$args )
                : "SELECT COUNT(*) FROM {$table} WHERE {$where_sql}"
        );

        $args[] = $per_page;
        $args[] = $offset;

        $items = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$table} WHERE {$where_sql} ORDER BY created_at DESC LIMIT %d OFFSET %d",
                ...$args
            )
        );

        foreach ( $items as &$item ) {
            $item->original_data = json_decode( $item->original_data, true );
        }

        return rest_ensure_response( array(
            'items'    => $items,
            'total'    => $total,
            'page'     => $page,
            'per_page' => $per_page,
            'pages'    => ceil( $total / $per_page ),
        ) );
    }

    public static function restore_item( $request ) {
        $quarantine = new Quarantine();
        $dry_run    = (bool) $request->get_param( 'dry_run' );
        $result     = $quarantine->restore( intval( $request['id'] ), $dry_run );

        if ( is_wp_error( $result ) ) {
            return $result;
        }

        return rest_ensure_response( array( 'restored' => true, 'dry_run' => $dry_run ) );
    }

    public static function delete_item( $request ) {
        $quarantine = new Quarantine();
        $dry_run    = (bool) $request->get_param( 'dry_run' );
        $result     = $quarantine->delete( intval( $request['id'] ), $dry_run );

        return rest_ensure_response( array( 'deleted' => $result, 'dry_run' => $dry_run ) );
    }

    public static function bulk_quarantine( $request ) {
        $quarantine  = new Quarantine();
        $scan_id     = sanitize_text_field( $request->get_param( 'scan_id' ) );
        $raw_types   = (array) $request->get_param( 'issue_types' );
        $issue_types = array_filter( array_map( 'sanitize_key', $raw_types ) );
        $dry_run     = (bool) $request->get_param( 'dry_run' );
        $result      = $quarantine->bulk_quarantine_from_scan( $scan_id, $issue_types, $dry_run );

        if ( is_wp_error( $result ) ) {
            return $result;
        }

        return rest_ensure_response( $result );
    }

    public static function bulk_restore( $request ) {
        $quarantine = new Quarantine();
        $ids        = array_map( 'intval', (array) $request->get_param( 'ids' ) );
        $dry_run    = (bool) $request->get_param( 'dry_run' );
        $restored   = 0;

        foreach ( $ids as $id ) {
            if ( $id <= 0 ) {
                continue;
            }
            $r = $quarantine->restore( $id, $dry_run );
            if ( ! is_wp_error( $r ) ) {
                $restored++;
            }
        }

        return rest_ensure_response( array( 'restored' => $restored, 'dry_run' => $dry_run ) );
    }

    /**
     * Return recent audit-log entries.
     */
    public static function get_audit_log( $request ) {
        $limit = absint( $request->get_param( 'limit' ) );
        if ( ! $limit ) {
            $limit = 100;
        }
        return rest_ensure_response( array(
            'items' => Audit_Log::recent( $limit ),
        ) );
    }

    // ── Reconciliation ──

    public static function run_reconciliation( $request ) {
        $gateway = sanitize_text_field( $request->get_param( 'gateway' ) );
        $from    = sanitize_text_field( $request->get_param( 'from' ) );
        $to      = sanitize_text_field( $request->get_param( 'to' ) );

        $reconciler = new Reconciler();
        $result     = $reconciler->run( $gateway, $from, $to );

        if ( is_wp_error( $result ) ) {
            return $result;
        }

        return rest_ensure_response( $result );
    }

    public static function get_reconciliation( $request ) {
        global $wpdb;
        $table   = $wpdb->prefix . 'wc_reconciliation_cache';
        $status  = sanitize_text_field( $request->get_param( 'status' ) );
        $gateway = sanitize_text_field( $request->get_param( 'gateway' ) );

        $where = array( '1=1' );
        $args  = array();

        if ( $status ) {
            $where[] = 'status = %s';
            $args[]  = $status;
        }
        if ( $gateway ) {
            $where[] = 'gateway = %s';
            $args[]  = $gateway;
        }

        $where_sql = implode( ' AND ', $where );

        $items = $args
            ? $wpdb->get_results( $wpdb->prepare(
                "SELECT * FROM {$table} WHERE {$where_sql} ORDER BY checked_at DESC LIMIT 500",
                ...$args
            ) )
            : $wpdb->get_results(
                "SELECT * FROM {$table} WHERE {$where_sql} ORDER BY checked_at DESC LIMIT 500"
            );

        // Summary.
        $summary = array(
            'matched'         => 0,
            'mismatch'        => 0,
            'missing_gateway' => 0,
            'missing_wc'      => 0,
            'total_discrepancy' => 0,
        );

        foreach ( $items as $item ) {
            if ( isset( $summary[ $item->status ] ) ) {
                $summary[ $item->status ]++;
            }
            $summary['total_discrepancy'] += abs( floatval( $item->discrepancy ) );
        }

        return rest_ensure_response( array(
            'items'   => $items,
            'summary' => $summary,
        ) );
    }

    // ── Settings ──

    public static function get_settings() {
        $keys = array(
            'datahyg_scan_batch_size', 'datahyg_auto_scan_enabled', 'datahyg_auto_scan_day',
            'datahyg_email_alerts', 'datahyg_alert_email', 'datahyg_test_order_emails',
            'datahyg_stripe_enabled', 'datahyg_paypal_enabled', 'datahyg_store_start_date',
            'datahyg_duplicate_threshold',
        );

        $settings = array();
        foreach ( $keys as $key ) {
            $settings[ $key ] = get_option( $key, '' );
        }

        return rest_ensure_response( $settings );
    }

    public static function update_settings( $request ) {
        $allowed = array(
            'datahyg_scan_batch_size', 'datahyg_auto_scan_enabled', 'datahyg_auto_scan_day',
            'datahyg_email_alerts', 'datahyg_alert_email', 'datahyg_test_order_emails',
            'datahyg_stripe_enabled', 'datahyg_paypal_enabled', 'datahyg_store_start_date',
            'datahyg_duplicate_threshold',
        );

        $params = (array) $request->get_json_params();

        foreach ( $allowed as $key ) {
            if ( ! array_key_exists( $key, $params ) ) {
                continue;
            }
            $value = $params[ $key ];

            switch ( $key ) {
                case 'datahyg_alert_email':
                    $value = sanitize_email( $value );
                    if ( $value && ! is_email( $value ) ) {
                        continue 2;
                    }
                    break;
                case 'datahyg_scan_batch_size':
                case 'datahyg_duplicate_threshold':
                    $value = absint( $value );
                    break;
                case 'datahyg_auto_scan_enabled':
                case 'datahyg_email_alerts':
                case 'datahyg_stripe_enabled':
                case 'datahyg_paypal_enabled':
                    $value = in_array( $value, array( 'yes', 'no' ), true ) ? $value : 'no';
                    break;
                case 'datahyg_auto_scan_day':
                    $days  = array( 'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday' );
                    $value = in_array( $value, $days, true ) ? $value : 'monday';
                    break;
                case 'datahyg_store_start_date':
                    $value = sanitize_text_field( $value );
                    break;
                case 'datahyg_test_order_emails':
                    // Comma-separated emails list.
                    $emails = array_filter( array_map( 'sanitize_email', array_map( 'trim', explode( ',', (string) $value ) ) ) );
                    $value  = implode( ',', $emails );
                    break;
                default:
                    $value = sanitize_text_field( $value );
            }

            update_option( $key, $value );
        }

        // Reschedule auto scan.
        Auto_Scan::reschedule();

        Audit_Log::record( 'update_settings', array( 'payload' => array( 'keys' => array_keys( $params ) ) ), false );

        return rest_ensure_response( array( 'updated' => true ) );
    }

    // ── Dashboard ──

    public static function get_dashboard() {
        global $wpdb;

        $scan_table = $wpdb->prefix . 'wc_data_scan_log';
        $q_table    = $wpdb->prefix . 'wc_data_quarantine';

        $last_scan = $wpdb->get_row(
            "SELECT * FROM {$scan_table} ORDER BY started_at DESC LIMIT 1"
        );

        if ( $last_scan ) {
            $last_scan->summary = json_decode( $last_scan->summary, true );
        }

        $quarantine_counts = $wpdb->get_results(
            "SELECT issue_type, COUNT(*) as count FROM {$q_table} WHERE status = 'quarantined' GROUP BY issue_type"
        );

        $total_quarantined = $wpdb->get_var(
            "SELECT COUNT(*) FROM {$q_table} WHERE status = 'quarantined'"
        );

        $scan_history = $wpdb->get_results(
            "SELECT id, scan_type, started_at, confidence_score, issues_found
             FROM {$scan_table} ORDER BY started_at DESC LIMIT 10"
        );

        return rest_ensure_response( array(
            'last_scan'         => $last_scan,
            'quarantine_counts' => $quarantine_counts,
            'total_quarantined' => intval( $total_quarantined ),
            'scan_history'      => $scan_history,
        ) );
    }
}
