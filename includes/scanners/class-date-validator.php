<?php
namespace DataHygiene\Scanners;

use DataHygiene\Scan_Module;

defined( 'ABSPATH' ) || exit;

/**
 * Validates order dates — future dates, dates before store creation.
 */
class Date_Validator extends Scan_Module {

    private $store_start_timestamp;

    public function __construct( \DataHygiene\Scanner $scanner ) {
        parent::__construct( $scanner );

        $start_date = get_option( 'datahyg_store_start_date', '' );
        if ( $start_date ) {
            $this->store_start_timestamp = strtotime( $start_date );
        } else {
            // Fallback: use the oldest order or WooCommerce installation date.
            $this->store_start_timestamp = $this->detect_store_start();
        }
    }

    public function scan_batch( array $orders ) {
        $now = time();

        foreach ( $orders as $order ) {
            $date_str  = $order->date_created_gmt ?? '';
            $timestamp = strtotime( $date_str );

            if ( ! $timestamp ) {
                $this->add_issue( array(
                    'item_type'   => 'order',
                    'item_id'     => (int) $order->id,
                    'issue_type'  => 'invalid_date',
                    'severity'    => 'high',
                    'description' => sprintf(
                        /* translators: %d: order ID */
                        __( 'Order #%d has an invalid or empty date.', 'data-hygiene-for-woocommerce' ),
                        $order->id
                    ),
                ) );
                continue;
            }

            // Future date.
            if ( $timestamp > $now + 86400 ) { // Allow 1 day tolerance for timezone issues.
                $this->add_issue( array(
                    'item_type'   => 'order',
                    'item_id'     => (int) $order->id,
                    'issue_type'  => 'future_date',
                    'severity'    => 'critical',
                    'description' => sprintf(
                        /* translators: 1: order ID, 2: order date */
                        __( 'Order #%1$d has a future date: %2$s', 'data-hygiene-for-woocommerce' ),
                        $order->id,
                        $date_str
                    ),
                ) );
            }

            // Before store start date.
            if ( $this->store_start_timestamp && $timestamp < $this->store_start_timestamp ) {
                $this->add_issue( array(
                    'item_type'   => 'order',
                    'item_id'     => (int) $order->id,
                    'issue_type'  => 'pre_store_date',
                    'severity'    => 'medium',
                    'description' => sprintf(
                        /* translators: 1: order ID, 2: order date */
                        __( 'Order #%1$d date (%2$s) is before the store start date.', 'data-hygiene-for-woocommerce' ),
                        $order->id,
                        $date_str
                    ),
                ) );
            }

            // Very old date (before 2010 — before WooCommerce existed).
            if ( $timestamp < strtotime( '2010-01-01' ) ) {
                $this->add_issue( array(
                    'item_type'   => 'order',
                    'item_id'     => (int) $order->id,
                    'issue_type'  => 'suspicious_date',
                    'severity'    => 'high',
                    'description' => sprintf(
                        /* translators: 1: order ID, 2: order date */
                        __( 'Order #%1$d has an unrealistic date: %2$s (before WooCommerce existed).', 'data-hygiene-for-woocommerce' ),
                        $order->id,
                        $date_str
                    ),
                ) );
            }
        }
    }

    private function detect_store_start() {
        global $wpdb;

        // Try WooCommerce install date.
        $wc_install = get_option( 'woocommerce_store_id' );
        if ( $wc_install ) {
            $oldest_order = $wpdb->get_var(
                "SELECT MIN(date_created_gmt) FROM {$wpdb->prefix}wc_order_stats"
            );
            if ( $oldest_order ) {
                return strtotime( $oldest_order );
            }
        }

        // Fallback: oldest post of type shop_order.
        $oldest = $wpdb->get_var(
            "SELECT MIN(post_date_gmt) FROM {$wpdb->posts} WHERE post_type = 'shop_order'"
        );

        return $oldest ? strtotime( $oldest ) : 0;
    }
}
