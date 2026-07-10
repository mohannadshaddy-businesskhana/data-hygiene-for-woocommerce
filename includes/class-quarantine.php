<?php
namespace DataHygiene;

defined( 'ABSPATH' ) || exit;

class Quarantine {

    private $table;

    public function __construct() {
        global $wpdb;
        $this->table = $wpdb->prefix . 'wc_data_quarantine';
    }

    /**
     * Add an item to quarantine.
     *
     * @param array $issue   Issue payload from a scan module.
     * @param bool  $dry_run When true, no rows are written / removed. Returns a preview row id of 0.
     * @return int Inserted quarantine id (0 in dry-run).
     */
    public function add( array $issue, bool $dry_run = false ) {
        global $wpdb;

        // Whitelist + sanitize keys we will use downstream.
        $item_type  = isset( $issue['item_type'] ) ? sanitize_key( $issue['item_type'] ) : '';
        $item_id    = isset( $issue['item_id'] ) ? (int) $issue['item_id'] : 0;
        $issue_type = isset( $issue['issue_type'] ) ? sanitize_key( $issue['issue_type'] ) : '';
        $scan_id    = isset( $issue['scan_id'] ) ? sanitize_text_field( $issue['scan_id'] ) : '';
        $severity   = isset( $issue['severity'] ) ? sanitize_key( $issue['severity'] ) : 'medium';
        $desc       = isset( $issue['description'] ) ? wp_kses_post( $issue['description'] ) : '';

        if ( ! $item_type || ! $item_id || ! $issue_type ) {
            return 0;
        }

        // Store original data before quarantining (read-only, safe to do in dry-run too).
        $original_data = $this->get_original_data( $item_type, $item_id );

        if ( $dry_run ) {
            Audit_Log::record( 'quarantine', array(
                'item_type' => $item_type,
                'item_id'   => $item_id,
                'scan_id'   => $scan_id,
                'payload'   => array( 'issue_type' => $issue_type, 'preview' => $original_data ),
            ), true );
            return 0;
        }

        $wpdb->insert( $this->table, array(
            'scan_id'       => $scan_id,
            'item_type'     => $item_type,
            'item_id'       => $item_id,
            'issue_type'    => $issue_type,
            'severity'      => $severity,
            'description'   => $desc,
            'original_data' => wp_json_encode( $original_data ),
            'status'        => 'quarantined',
            'created_at'    => current_time( 'mysql' ),
        ), array( '%s', '%s', '%d', '%s', '%s', '%s', '%s', '%s', '%s' ) );

        $quarantine_id = (int) $wpdb->insert_id;

        // Remove from analytics lookup tables.
        $this->remove_from_analytics( $item_type, $item_id );

        Audit_Log::record( 'quarantine', array(
            'item_type'     => $item_type,
            'item_id'       => $item_id,
            'quarantine_id' => $quarantine_id,
            'scan_id'       => $scan_id,
            'payload'       => array( 'issue_type' => $issue_type ),
        ), false );

        return $quarantine_id;
    }

    /**
     * Restore a quarantined item.
     */
    public function restore( int $quarantine_id, bool $dry_run = false ) {
        global $wpdb;

        $item = $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM {$this->table} WHERE id = %d AND status = 'quarantined'",
            $quarantine_id
        ) );

        if ( ! $item ) {
            return new \WP_Error( 'not_found', __( 'Quarantine item not found or already restored.', 'data-hygiene-for-woocommerce' ) );
        }

        $original_data = json_decode( $item->original_data, true );

        if ( $dry_run ) {
            Audit_Log::record( 'restore', array(
                'item_type'     => $item->item_type,
                'item_id'       => (int) $item->item_id,
                'quarantine_id' => $quarantine_id,
                'payload'       => array( 'preview' => $original_data ),
            ), true );
            return true;
        }

        if ( ! empty( $original_data ) ) {
            $this->restore_to_analytics( $item->item_type, (int) $item->item_id, $original_data );
        }

        $wpdb->update(
            $this->table,
            array(
                'status'      => 'restored',
                'resolved_at' => current_time( 'mysql' ),
            ),
            array( 'id' => $quarantine_id ),
            array( '%s', '%s' ),
            array( '%d' )
        );

        Audit_Log::record( 'restore', array(
            'item_type'     => $item->item_type,
            'item_id'       => (int) $item->item_id,
            'quarantine_id' => $quarantine_id,
        ), false );

        return true;
    }

    /**
     * Permanently delete a quarantined item.
     *
     * @param int  $quarantine_id Row id in quarantine.
     * @param bool $dry_run       When true, no rows are removed.
     */
    public function delete( int $quarantine_id, bool $dry_run = false ) {
        global $wpdb;

        $item = $wpdb->get_row( $wpdb->prepare(
            "SELECT id, item_type, item_id FROM {$this->table} WHERE id = %d",
            $quarantine_id
        ) );

        if ( ! $item ) {
            return false;
        }

        if ( $dry_run ) {
            Audit_Log::record( 'delete', array(
                'item_type'     => $item->item_type,
                'item_id'       => (int) $item->item_id,
                'quarantine_id' => $quarantine_id,
            ), true );
            return true;
        }

        $deleted = (bool) $wpdb->delete( $this->table, array( 'id' => $quarantine_id ), array( '%d' ) );

        if ( $deleted ) {
            Audit_Log::record( 'delete', array(
                'item_type'     => $item->item_type,
                'item_id'       => (int) $item->item_id,
                'quarantine_id' => $quarantine_id,
            ), false );
        }

        return $deleted;
    }

    /**
     * Bulk quarantine issues from a scan.
     */
    public function bulk_quarantine_from_scan( string $scan_id, array $issue_types = array(), bool $dry_run = false ) {
        global $wpdb;

        $scan_table = $wpdb->prefix . 'wc_data_scan_log';
        $scan       = $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM {$scan_table} WHERE id = %s", $scan_id
        ) );

        if ( ! $scan || ! $scan->summary ) {
            return new \WP_Error( 'not_found', __( 'Scan not found.', 'data-hygiene-for-woocommerce' ) );
        }

        // Re-run scan to get issues (since issues aren't stored permanently yet).
        $scanner = new Scanner( $scan->scan_type );
        $result  = $scanner->run();
        $count   = 0;
        $preview = array();

        foreach ( $result['issues'] as $issue ) {
            if ( ! empty( $issue_types ) && ! in_array( $issue['issue_type'], $issue_types, true ) ) {
                continue;
            }

            // Check if already quarantined.
            $exists = $wpdb->get_var( $wpdb->prepare(
                "SELECT COUNT(*) FROM {$this->table} WHERE item_type = %s AND item_id = %d AND status = 'quarantined'",
                $issue['item_type'],
                (int) $issue['item_id']
            ) );

            if ( $exists ) {
                continue;
            }

            $this->add( $issue, $dry_run );
            $count++;

            if ( $dry_run ) {
                $preview[] = array(
                    'item_type'  => $issue['item_type'],
                    'item_id'    => (int) $issue['item_id'],
                    'issue_type' => $issue['issue_type'],
                    'severity'   => $issue['severity'] ?? 'medium',
                );
            }
        }

        if ( ! $dry_run ) {
            $wpdb->update(
                $scan_table,
                array( 'issues_quarantined' => $count ),
                array( 'id' => $scan_id ),
                array( '%d' ),
                array( '%s' )
            );
        }

        Audit_Log::record( 'bulk_quarantine', array(
            'scan_id' => $scan_id,
            'payload' => array( 'count' => $count, 'issue_types' => $issue_types ),
        ), $dry_run );

        return array(
            'quarantined' => $count,
            'dry_run'     => $dry_run,
            'preview'     => $dry_run ? $preview : null,
        );
    }

    /**
     * Get original data for backup.
     */
    private function get_original_data( string $item_type, int $item_id ) {
        global $wpdb;

        switch ( $item_type ) {
            case 'order':
                $order = wc_get_order( $item_id );
                if ( ! $order ) {
                    return array();
                }
                return array(
                    'id'             => $order->get_id(),
                    'status'         => $order->get_status(),
                    'total'          => $order->get_total(),
                    'date_created'   => $order->get_date_created() ? $order->get_date_created()->format( 'Y-m-d H:i:s' ) : '',
                    'billing_email'  => $order->get_billing_email(),
                    'payment_method' => $order->get_payment_method(),
                    'customer_id'    => $order->get_customer_id(),
                );

            case 'order_stats':
                $stats = $wpdb->get_row( $wpdb->prepare(
                    "SELECT * FROM {$wpdb->prefix}wc_order_stats WHERE order_id = %d",
                    $item_id
                ), ARRAY_A );
                return $stats ?: array();

            default:
                return array();
        }
    }

    /**
     * Remove order from WooCommerce analytics lookup tables.
     */
    private function remove_from_analytics( string $item_type, int $item_id ) {
        global $wpdb;

        if ( 'order' === $item_type || 'order_stats' === $item_type ) {
            $wpdb->delete( $wpdb->prefix . 'wc_order_stats', array( 'order_id' => $item_id ) );
            $wpdb->delete( $wpdb->prefix . 'wc_order_product_lookup', array( 'order_id' => $item_id ) );
            $wpdb->delete( $wpdb->prefix . 'wc_order_coupon_lookup', array( 'order_id' => $item_id ) );
            $wpdb->delete( $wpdb->prefix . 'wc_order_tax_lookup', array( 'order_id' => $item_id ) );
        }
    }

    /**
     * Restore order to WooCommerce analytics lookup tables.
     */
    private function restore_to_analytics( string $item_type, int $item_id, array $original_data ) {
        if ( 'order' === $item_type || 'order_stats' === $item_type ) {
            // Trigger WooCommerce to rebuild analytics for this order.
            $order = wc_get_order( $item_id );
            if ( $order ) {
                // This will re-insert into wc_order_stats and lookup tables.
                do_action( 'woocommerce_analytics_update_order_stats', $item_id );

                // Fallback: manually rebuild if the action doesn't work.
                if ( class_exists( \Automattic\WooCommerce\Admin\API\Reports\Orders\Stats\DataStore::class ) ) {
                    \Automattic\WooCommerce\Admin\API\Reports\Orders\Stats\DataStore::update( $order );
                }
            }
        }
    }
}
