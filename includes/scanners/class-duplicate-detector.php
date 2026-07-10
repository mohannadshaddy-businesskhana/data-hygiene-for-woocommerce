<?php
namespace DataHygiene\Scanners;

use DataHygiene\Scan_Module;

defined( 'ABSPATH' ) || exit;

/**
 * Detects duplicate orders: same amount + customer + time window.
 */
class Duplicate_Detector extends Scan_Module {

    private $threshold_seconds;
    private $seen = array();

    public function __construct( \DataHygiene\Scanner $scanner ) {
        parent::__construct( $scanner );
        $this->threshold_seconds = intval( get_option( 'datahyg_duplicate_threshold', 60 ) );
    }

    public function scan_batch( array $orders ) {
        foreach ( $orders as $order ) {
            $total     = floatval( $order->total_amount ?? 0 );
            $customer  = $order->billing_email ?? ( $order->customer_id ?? '' );
            $timestamp = strtotime( $order->date_created_gmt ?? '' );

            if ( ! $customer || ! $timestamp || $total <= 0 ) {
                continue;
            }

            $key = strtolower( $customer ) . '|' . number_format( $total, 2, '.', '' );

            if ( isset( $this->seen[ $key ] ) ) {
                foreach ( $this->seen[ $key ] as $prev ) {
                    $diff = abs( $timestamp - $prev['timestamp'] );

                    if ( $diff <= $this->threshold_seconds && (int) $order->id !== $prev['id'] ) {
                        $this->add_issue( array(
                            'item_type'   => 'order',
                            'item_id'     => (int) $order->id,
                            'issue_type'  => 'duplicate',
                            'severity'    => $diff <= 10 ? 'critical' : 'high',
                            'description' => sprintf(
                                /* translators: 1: order ID, 2: duplicate order ID, 3: customer identifier, 4: formatted order total, 5: seconds apart */
                                __( 'Order #%1$d may be a duplicate of #%2$d — same customer (%3$s), same amount (%4$s), %5$d seconds apart.', 'data-hygiene-for-woocommerce' ),
                                $order->id,
                                $prev['id'],
                                $customer,
                                wc_price( $total ),
                                $diff
                            ),
                        ) );
                        break; // Only flag once per duplicate pair.
                    }
                }
            }

            $this->seen[ $key ][] = array(
                'id'        => (int) $order->id,
                'timestamp' => $timestamp,
            );
        }
    }
}
