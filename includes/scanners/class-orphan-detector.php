<?php
namespace DataHygiene\Scanners;

use DataHygiene\Scan_Module;

defined( 'ABSPATH' ) || exit;

/**
 * Detects orphan orders in WooCommerce analytics tables
 * that no longer have a corresponding order record.
 */
class Orphan_Detector extends Scan_Module {

    public function scan_batch( array $orders ) {
        global $wpdb;

        if ( empty( $orders ) ) {
            return;
        }

        $order_ids = wp_list_pluck( $orders, 'id' );
        $placeholders = implode( ',', array_fill( 0, count( $order_ids ), '%d' ) );

        // Check for order_stats entries without matching orders.
        $stats_table = $wpdb->prefix . 'wc_order_stats';

        $orphan_stats = $wpdb->get_results( $wpdb->prepare(
            "SELECT os.order_id, os.net_total, os.status, os.date_created
             FROM {$stats_table} os
             WHERE os.order_id IN ({$placeholders})
             AND NOT EXISTS (
                 SELECT 1 FROM {$wpdb->prefix}wc_orders o WHERE o.id = os.order_id
             )
             AND NOT EXISTS (
                 SELECT 1 FROM {$wpdb->posts} p WHERE p.ID = os.order_id AND p.post_type = 'shop_order'
             )",
            ...$order_ids
        ) );

        foreach ( $orphan_stats as $orphan ) {
            $this->add_issue( array(
                'item_type'   => 'order_stats',
                'item_id'     => (int) $orphan->order_id,
                'issue_type'  => 'orphan_order',
                'severity'    => 'high',
                'description' => sprintf(
                    __( 'Order #%d exists in analytics (total: %s) but the order record is missing.', 'data-hygiene-for-woocommerce' ),
                    $orphan->order_id,
                    wc_price( $orphan->net_total )
                ),
            ) );
        }

        // Check for product lookup entries without matching orders.
        $product_table = $wpdb->prefix . 'wc_order_product_lookup';

        // Only check if table exists.
        if ( $wpdb->get_var( "SHOW TABLES LIKE '{$product_table}'" ) === $product_table ) {
            $orphan_products = $wpdb->get_results( $wpdb->prepare(
                "SELECT DISTINCT opl.order_id
                 FROM {$product_table} opl
                 WHERE opl.order_id IN ({$placeholders})
                 AND NOT EXISTS (
                     SELECT 1 FROM {$stats_table} os WHERE os.order_id = opl.order_id
                 )",
                ...$order_ids
            ) );

            foreach ( $orphan_products as $orphan ) {
                $this->add_issue( array(
                    'item_type'   => 'product_lookup',
                    'item_id'     => (int) $orphan->order_id,
                    'issue_type'  => 'orphan_lookup',
                    'severity'    => 'medium',
                    'description' => sprintf(
                        __( 'Product lookup for order #%d has no matching order stats entry.', 'data-hygiene-for-woocommerce' ),
                        $orphan->order_id
                    ),
                ) );
            }
        }
    }
}
