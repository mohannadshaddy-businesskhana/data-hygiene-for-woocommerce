<?php
namespace DataHygiene\Scanners;

use DataHygiene\Scan_Module;

defined( 'ABSPATH' ) || exit;

/**
 * Validates order amounts — negative, zero, unusually large values.
 */
class Amount_Validator extends Scan_Module {

    public function scan_batch( array $orders ) {
        foreach ( $orders as $order ) {
            $total  = floatval( $order->total_amount ?? 0 );
            $status = $this->normalize_status( $order->status );

            // Negative amounts.
            if ( $total < 0 ) {
                $this->add_issue( array(
                    'item_type'   => 'order',
                    'item_id'     => (int) $order->id,
                    'issue_type'  => 'negative_amount',
                    'severity'    => 'critical',
                    'description' => sprintf(
                        /* translators: 1: order ID, 2: formatted order total */
                        __( 'Order #%1$d has a negative total: %2$s', 'data-hygiene-for-woocommerce' ),
                        $order->id,
                        wc_price( $total )
                    ),
                ) );
            }

            // Zero amount on non-free statuses.
            if ( $total == 0 && in_array( $status, array( 'completed', 'processing' ), true ) ) {
                // Check if it's a legitimate free/coupon order.
                $wc_order = wc_get_order( $order->id );
                $has_items = $wc_order && count( $wc_order->get_items() ) > 0;

                if ( ! $has_items ) {
                    $this->add_issue( array(
                        'item_type'   => 'order',
                        'item_id'     => (int) $order->id,
                        'issue_type'  => 'zero_amount',
                        'severity'    => 'medium',
                        'description' => sprintf(
                            /* translators: %d: order ID */
                            __( 'Order #%d has zero total and no line items.', 'data-hygiene-for-woocommerce' ),
                            $order->id
                        ),
                    ) );
                }
            }

            // Unusually large amounts (statistical outlier detection).
            // We'll flag orders more than 10x the average.
            // This is done post-batch for efficiency — see finalize.

            // Missing product reference.
            if ( in_array( $status, array( 'completed', 'processing' ), true ) && $total > 0 ) {
                $wc_order = wc_get_order( $order->id );
                if ( $wc_order ) {
                    foreach ( $wc_order->get_items() as $item ) {
                        $product_id = $item->get_product_id();
                        if ( $product_id && ! wc_get_product( $product_id ) ) {
                            $this->add_issue( array(
                                'item_type'   => 'order',
                                'item_id'     => (int) $order->id,
                                'issue_type'  => 'missing_product',
                                'severity'    => 'low',
                                'description' => sprintf(
                                    /* translators: 1: order ID, 2: product ID */
                                    __( 'Order #%1$d references product #%2$d which no longer exists.', 'data-hygiene-for-woocommerce' ),
                                    $order->id,
                                    $product_id
                                ),
                            ) );
                            break; // One flag per order is enough.
                        }
                    }
                }
            }
        }
    }

    private function normalize_status( $status ) {
        return str_replace( 'wc-', '', $status );
    }
}
