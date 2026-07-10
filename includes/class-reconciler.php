<?php
namespace DataHygiene;

defined( 'ABSPATH' ) || exit;

class Reconciler {

    private $cache_table;

    public function __construct() {
        global $wpdb;
        $this->cache_table = $wpdb->prefix . 'wc_reconciliation_cache';
    }

    /**
     * Run reconciliation for a specific gateway.
     *
     * @param string $gateway 'stripe' or 'paypal'.
     * @param string $from    Start date (Y-m-d).
     * @param string $to      End date (Y-m-d).
     * @return array|\WP_Error
     */
    public function run( string $gateway, string $from = '', string $to = '' ) {
        if ( ! in_array( $gateway, array( 'stripe', 'paypal' ), true ) ) {
            return new \WP_Error( 'invalid_gateway', __( 'Unsupported gateway.', 'data-hygiene-for-woocommerce' ) );
        }

        // Default: last 30 days.
        if ( ! $from ) {
            $from = wp_date( 'Y-m-d', strtotime( '-30 days' ) );
        }
        if ( ! $to ) {
            $to = wp_date( 'Y-m-d' );
        }

        // Get gateway reconciler.
        $reconciler = $this->get_gateway_reconciler( $gateway );
        if ( is_wp_error( $reconciler ) ) {
            return $reconciler;
        }

        // Get WooCommerce orders for this gateway.
        $wc_orders = $this->get_wc_orders( $gateway, $from, $to );

        // Get gateway transactions.
        $gateway_txns = $reconciler->get_transactions( $from, $to );
        if ( is_wp_error( $gateway_txns ) ) {
            return $gateway_txns;
        }

        // Clear previous cache for this gateway.
        global $wpdb;
        $wpdb->query( $wpdb->prepare(
            "DELETE FROM {$this->cache_table} WHERE gateway = %s",
            $gateway
        ) );

        // Match orders to transactions.
        $results = $this->match( $wc_orders, $gateway_txns, $gateway );

        return $results;
    }

    /**
     * Match WC orders to gateway transactions.
     */
    private function match( array $wc_orders, array $gateway_txns, string $gateway ) {
        global $wpdb;

        $matched         = 0;
        $mismatch        = 0;
        $missing_gateway = 0;
        $missing_wc      = 0;
        $total_discrepancy = 0;

        // Index gateway transactions by transaction ID.
        $txn_index = array();
        foreach ( $gateway_txns as $txn ) {
            $txn_index[ $txn['id'] ] = $txn;
        }

        // Match WC orders.
        $matched_txn_ids = array();
        foreach ( $wc_orders as $order ) {
            $txn_id   = $order->transaction_id;
            $wc_total = floatval( $order->total );

            if ( $txn_id && isset( $txn_index[ $txn_id ] ) ) {
                $gateway_total = floatval( $txn_index[ $txn_id ]['amount'] );
                $discrepancy   = $wc_total - $gateway_total;
                $status        = abs( $discrepancy ) < 0.01 ? 'matched' : 'mismatch';

                if ( 'matched' === $status ) {
                    $matched++;
                } else {
                    $mismatch++;
                    $total_discrepancy += abs( $discrepancy );
                }

                $matched_txn_ids[] = $txn_id;
            } else {
                $status          = 'missing_gateway';
                $gateway_total   = null;
                $discrepancy     = null;
                $missing_gateway++;
            }

            $wpdb->insert( $this->cache_table, array(
                'order_id'       => $order->id,
                'wc_total'       => $wc_total,
                'gateway_total'  => $gateway_total,
                'gateway'        => $gateway,
                'gateway_txn_id' => $txn_id ?: '',
                'discrepancy'    => $discrepancy,
                'status'         => $status,
                'checked_at'     => current_time( 'mysql' ),
            ) );
        }

        // Find gateway transactions without WC orders.
        foreach ( $gateway_txns as $txn ) {
            if ( ! in_array( $txn['id'], $matched_txn_ids, true ) ) {
                $missing_wc++;

                $wpdb->insert( $this->cache_table, array(
                    'order_id'       => 0,
                    'wc_total'       => null,
                    'gateway_total'  => floatval( $txn['amount'] ),
                    'gateway'        => $gateway,
                    'gateway_txn_id' => $txn['id'],
                    'discrepancy'    => null,
                    'status'         => 'missing_wc',
                    'checked_at'     => current_time( 'mysql' ),
                ) );
            }
        }

        return array(
            'gateway'           => $gateway,
            'total_wc_orders'   => count( $wc_orders ),
            'total_gateway_txns' => count( $gateway_txns ),
            'matched'           => $matched,
            'mismatch'          => $mismatch,
            'missing_gateway'   => $missing_gateway,
            'missing_wc'        => $missing_wc,
            'total_discrepancy' => round( $total_discrepancy, 2 ),
        );
    }

    /**
     * Get WooCommerce orders for a specific gateway.
     */
    private function get_wc_orders( string $gateway, string $from, string $to ) {
        global $wpdb;

        $payment_methods = array();
        switch ( $gateway ) {
            case 'stripe':
                $payment_methods = array( 'stripe', 'stripe_cc', 'stripe_sepa' );
                break;
            case 'paypal':
                $payment_methods = array( 'paypal', 'ppec_paypal', 'ppcp-gateway' );
                break;
        }

        $placeholders = implode( ',', array_fill( 0, count( $payment_methods ), '%s' ) );

        $scanner = new Scanner();
        if ( $scanner->is_hpos_enabled() ) {
            $args = array_merge( $payment_methods, array( $from, $to ) );
            return $wpdb->get_results( $wpdb->prepare(
                "SELECT id, total_amount as total, transaction_id, date_created_gmt
                 FROM {$wpdb->prefix}wc_orders
                 WHERE payment_method IN ({$placeholders})
                 AND type = 'shop_order'
                 AND date_created_gmt >= %s
                 AND date_created_gmt <= %s
                 AND status IN ('wc-completed', 'wc-processing', 'wc-refunded')
                 ORDER BY id ASC",
                ...$args
            ) );
        }

        $args = array_merge( $payment_methods, array( $from, $to ) );
        return $wpdb->get_results( $wpdb->prepare(
            "SELECT p.ID as id,
                    MAX(CASE WHEN pm.meta_key = '_order_total' THEN pm.meta_value END) as total,
                    MAX(CASE WHEN pm.meta_key = '_transaction_id' THEN pm.meta_value END) as transaction_id,
                    p.post_date_gmt as date_created_gmt
             FROM {$wpdb->posts} p
             JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
             WHERE p.post_type = 'shop_order'
             AND p.post_status IN ('wc-completed', 'wc-processing', 'wc-refunded')
             AND EXISTS (
                 SELECT 1 FROM {$wpdb->postmeta} pm2
                 WHERE pm2.post_id = p.ID AND pm2.meta_key = '_payment_method'
                 AND pm2.meta_value IN ({$placeholders})
             )
             AND p.post_date_gmt >= %s
             AND p.post_date_gmt <= %s
             GROUP BY p.ID
             ORDER BY p.ID ASC",
            ...$args
        ) );
    }

    /**
     * Get the gateway-specific reconciler.
     */
    private function get_gateway_reconciler( string $gateway ) {
        switch ( $gateway ) {
            case 'stripe':
                return new Gateways\Stripe_Reconciler();
            case 'paypal':
                return new Gateways\Paypal_Reconciler();
            default:
                return new \WP_Error( 'unknown_gateway', 'Unknown gateway: ' . $gateway );
        }
    }
}
