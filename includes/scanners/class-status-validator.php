<?php
namespace DataHygiene\Scanners;

use DataHygiene\Scan_Module;

defined( 'ABSPATH' ) || exit;

/**
 * Validates order statuses for logical inconsistencies.
 */
class Status_Validator extends Scan_Module {

    public function scan_batch( array $orders ) {
        foreach ( $orders as $order ) {
            $status  = $this->normalize_status( $order->status );
            $total   = floatval( $order->total_amount ?? 0 );
            $method  = $order->payment_method ?? '';
            $txn_id  = $order->transaction_id ?? '';

            // Completed order without payment method.
            if ( 'completed' === $status && empty( $method ) && $total > 0 ) {
                $this->add_issue( array(
                    'item_type'   => 'order',
                    'item_id'     => (int) $order->id,
                    'issue_type'  => 'status_mismatch',
                    'severity'    => 'medium',
                    'description' => sprintf(
                        /* translators: 1: order ID, 2: formatted order total */
                        __( 'Order #%1$d is completed (%2$s) but has no payment method.', 'data-hygiene-for-woocommerce' ),
                        $order->id,
                        wc_price( $total )
                    ),
                ) );
            }

            // Processing order without transaction ID for payment gateways that provide one.
            $gateways_with_txn = array( 'stripe', 'paypal', 'ppec_paypal', 'stripe_cc' );
            if ( 'processing' === $status && in_array( $method, $gateways_with_txn, true ) && empty( $txn_id ) ) {
                $this->add_issue( array(
                    'item_type'   => 'order',
                    'item_id'     => (int) $order->id,
                    'issue_type'  => 'missing_transaction',
                    'severity'    => 'high',
                    'description' => sprintf(
                        /* translators: 1: order ID, 2: payment method */
                        __( 'Order #%1$d is processing via %2$s but has no transaction ID.', 'data-hygiene-for-woocommerce' ),
                        $order->id,
                        $method
                    ),
                ) );
            }

            // Refunded order with no refund records.
            if ( 'refunded' === $status ) {
                $wc_order = wc_get_order( $order->id );
                if ( $wc_order && empty( $wc_order->get_refunds() ) ) {
                    $this->add_issue( array(
                        'item_type'   => 'order',
                        'item_id'     => (int) $order->id,
                        'issue_type'  => 'status_mismatch',
                        'severity'    => 'high',
                        'description' => sprintf(
                            /* translators: %d: order ID */
                            __( 'Order #%d is marked as refunded but has no refund records.', 'data-hygiene-for-woocommerce' ),
                            $order->id
                        ),
                    ) );
                }
            }

            // On-hold for too long (more than 7 days) with a payment gateway.
            if ( 'on-hold' === $status && ! empty( $method ) && $method !== 'bacs' && $method !== 'cheque' ) {
                $created = strtotime( $order->date_created_gmt ?? '' );
                if ( $created && ( time() - $created ) > 7 * DAY_IN_SECONDS ) {
                    $this->add_issue( array(
                        'item_type'   => 'order',
                        'item_id'     => (int) $order->id,
                        'issue_type'  => 'stale_order',
                        'severity'    => 'low',
                        'description' => sprintf(
                            /* translators: 1: order ID, 2: payment method */
                            __( 'Order #%1$d has been on-hold for over 7 days with %2$s payment.', 'data-hygiene-for-woocommerce' ),
                            $order->id,
                            $method
                        ),
                    ) );
                }
            }
        }
    }

    private function normalize_status( $status ) {
        return str_replace( 'wc-', '', $status );
    }
}
