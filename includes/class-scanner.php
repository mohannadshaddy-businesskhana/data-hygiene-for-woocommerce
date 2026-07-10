<?php
namespace DataHygiene;

defined( 'ABSPATH' ) || exit;

class Scanner {

    private $scan_id;
    private $scan_type;
    private $modules = array();
    private $issues  = array();
    private $batch_size;

    public function __construct( $scan_type = 'full' ) {
        $this->scan_id    = wp_generate_uuid4();
        $this->scan_type  = $scan_type;
        $this->batch_size = intval( get_option( 'datahyg_scan_batch_size', 50 ) );
        $this->register_modules();
    }

    private function register_modules() {
        $all_modules = array(
            'orphan'    => new Scanners\Orphan_Detector( $this ),
            'test'      => new Scanners\Test_Order_Detector( $this ),
            'duplicate' => new Scanners\Duplicate_Detector( $this ),
            'status'    => new Scanners\Status_Validator( $this ),
            'date'      => new Scanners\Date_Validator( $this ),
            'amount'    => new Scanners\Amount_Validator( $this ),
        );

        if ( 'quick' === $this->scan_type ) {
            // Quick scan: only orphan + test + amount.
            $this->modules = array(
                'orphan' => $all_modules['orphan'],
                'test'   => $all_modules['test'],
                'amount' => $all_modules['amount'],
            );
        } else {
            $this->modules = $all_modules;
        }
    }

    public function get_scan_id() {
        return $this->scan_id;
    }

    public function get_batch_size() {
        return $this->batch_size;
    }

    /**
     * Run the scan.
     *
     * @param callable|null $progress_callback Called with (int $processed, int $total).
     * @return array Scan summary.
     */
    public function run( $progress_callback = null ) {
        global $wpdb;

        $log_table = $wpdb->prefix . 'wc_data_scan_log';

        // Log scan start.
        $wpdb->insert( $log_table, array(
            'id'         => $this->scan_id,
            'scan_type'  => $this->scan_type,
            'started_at' => current_time( 'mysql' ),
        ) );

        $total_orders = $this->get_total_orders();
        $processed    = 0;
        $offset       = 0;

        while ( $offset < $total_orders ) {
            $orders = $this->get_order_batch( $offset );

            foreach ( $this->modules as $module ) {
                $module->scan_batch( $orders );
            }

            $offset    += $this->batch_size;
            $processed += count( $orders );

            if ( is_callable( $progress_callback ) ) {
                call_user_func( $progress_callback, $processed, $total_orders );
            }
        }

        // Collect issues from all modules.
        foreach ( $this->modules as $module ) {
            $this->issues = array_merge( $this->issues, $module->get_issues() );
        }

        // Calculate confidence score.
        $scorer = new Confidence_Scorer();
        $score  = $scorer->calculate( $total_orders, $this->issues );

        // Update scan log.
        $wpdb->update(
            $log_table,
            array(
                'completed_at'        => current_time( 'mysql' ),
                'total_orders_scanned' => $total_orders,
                'issues_found'        => count( $this->issues ),
                'confidence_score'    => $score,
                'summary'             => wp_json_encode( $this->get_summary() ),
            ),
            array( 'id' => $this->scan_id )
        );

        return array(
            'scan_id'        => $this->scan_id,
            'total_scanned'  => $total_orders,
            'issues_found'   => count( $this->issues ),
            'confidence_score' => $score,
            'issues'         => $this->issues,
            'summary'        => $this->get_summary(),
        );
    }

    private function get_total_orders() {
        global $wpdb;

        if ( $this->is_hpos_enabled() ) {
            return (int) $wpdb->get_var(
                "SELECT COUNT(*) FROM {$wpdb->prefix}wc_orders"
            );
        }

        return (int) $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = %s",
            'shop_order'
        ) );
    }

    public function get_order_batch( $offset ) {
        global $wpdb;

        if ( $this->is_hpos_enabled() ) {
            return $wpdb->get_results( $wpdb->prepare(
                "SELECT o.id, o.status, o.total_amount, o.date_created_gmt,
                        o.customer_id, o.payment_method, o.billing_email,
                        o.currency, o.transaction_id
                 FROM {$wpdb->prefix}wc_orders o
                 WHERE o.type = 'shop_order'
                 ORDER BY o.id ASC
                 LIMIT %d OFFSET %d",
                $this->batch_size,
                $offset
            ) );
        }

        // Legacy posts table.
        return $wpdb->get_results( $wpdb->prepare(
            "SELECT p.ID as id, p.post_status as status, p.post_date_gmt as date_created_gmt,
                    MAX(CASE WHEN pm.meta_key = '_order_total' THEN pm.meta_value END) as total_amount,
                    MAX(CASE WHEN pm.meta_key = '_customer_user' THEN pm.meta_value END) as customer_id,
                    MAX(CASE WHEN pm.meta_key = '_payment_method' THEN pm.meta_value END) as payment_method,
                    MAX(CASE WHEN pm.meta_key = '_billing_email' THEN pm.meta_value END) as billing_email,
                    MAX(CASE WHEN pm.meta_key = '_order_currency' THEN pm.meta_value END) as currency,
                    MAX(CASE WHEN pm.meta_key = '_transaction_id' THEN pm.meta_value END) as transaction_id
             FROM {$wpdb->posts} p
             LEFT JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
             WHERE p.post_type = 'shop_order'
             GROUP BY p.ID
             ORDER BY p.ID ASC
             LIMIT %d OFFSET %d",
            $this->batch_size,
            $offset
        ) );
    }

    public function is_hpos_enabled() {
        if ( class_exists( \Automattic\WooCommerce\Utilities\OrderUtil::class ) ) {
            return \Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled();
        }
        return false;
    }

    private function get_summary() {
        $by_type = array();
        $by_severity = array();

        foreach ( $this->issues as $issue ) {
            $type = $issue['issue_type'];
            $sev  = $issue['severity'];

            $by_type[ $type ]     = ( $by_type[ $type ] ?? 0 ) + 1;
            $by_severity[ $sev ] = ( $by_severity[ $sev ] ?? 0 ) + 1;
        }

        return array(
            'by_type'     => $by_type,
            'by_severity' => $by_severity,
        );
    }

    public function get_issues() {
        return $this->issues;
    }
}
