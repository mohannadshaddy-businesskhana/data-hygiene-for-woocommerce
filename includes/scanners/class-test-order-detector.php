<?php
namespace DataHygiene\Scanners;

use DataHygiene\Scan_Module;

defined( 'ABSPATH' ) || exit;

/**
 * Detects test orders based on admin emails, zero amounts,
 * and test-related keywords.
 */
class Test_Order_Detector extends Scan_Module {

    private $admin_emails = array();
    private $test_keywords = array( 'test', 'testing', 'dummy', 'fake', 'sample', 'demo', 'تجريب', 'تجربة', 'اختبار' );

    public function __construct( \DataHygiene\Scanner $scanner ) {
        parent::__construct( $scanner );
        $this->load_admin_emails();
    }

    private function load_admin_emails() {
        // WordPress admin email.
        $this->admin_emails[] = strtolower( get_option( 'admin_email' ) );

        // All admin/shop_manager user emails.
        $admins = get_users( array(
            'role__in' => array( 'administrator', 'shop_manager' ),
            'fields'   => array( 'user_email' ),
        ) );

        foreach ( $admins as $admin ) {
            $this->admin_emails[] = strtolower( $admin->user_email );
        }

        // Custom test emails from settings.
        $custom = get_option( 'datahyg_test_order_emails', '' );
        if ( $custom ) {
            $emails = array_map( 'trim', explode( ',', $custom ) );
            $this->admin_emails = array_merge( $this->admin_emails, array_map( 'strtolower', $emails ) );
        }

        $this->admin_emails = array_unique( array_filter( $this->admin_emails ) );
    }

    public function scan_batch( array $orders ) {
        foreach ( $orders as $order ) {
            $reasons = array();

            // Check billing email against admin emails.
            $email = strtolower( $order->billing_email ?? '' );
            if ( $email && in_array( $email, $this->admin_emails, true ) ) {
                $reasons[] = __( 'admin email used', 'data-hygiene-for-woocommerce' );
            }

            // Check for zero amount on completed/processing orders.
            $total  = floatval( $order->total_amount ?? 0 );
            $status = $this->normalize_status( $order->status );
            if ( $total == 0 && in_array( $status, array( 'completed', 'processing' ), true ) ) {
                $reasons[] = __( '$0 completed order', 'data-hygiene-for-woocommerce' );
            }

            // Check for test keywords in billing name or order notes.
            if ( $this->has_test_keywords( $order ) ) {
                $reasons[] = __( 'test keyword found', 'data-hygiene-for-woocommerce' );
            }

            // Check for known test payment methods.
            $method = $order->payment_method ?? '';
            if ( in_array( $method, array( 'bacs_test', 'cod_test' ), true ) ) {
                $reasons[] = __( 'test payment method', 'data-hygiene-for-woocommerce' );
            }

            if ( ! empty( $reasons ) ) {
                $this->add_issue( array(
                    'item_type'   => 'order',
                    'item_id'     => (int) $order->id,
                    'issue_type'  => 'test_order',
                    'severity'    => count( $reasons ) > 1 ? 'high' : 'medium',
                    'description' => sprintf(
                        /* translators: 1: order ID, 2: detection reasons */
                        __( 'Order #%1$d appears to be a test order: %2$s', 'data-hygiene-for-woocommerce' ),
                        $order->id,
                        implode( ', ', $reasons )
                    ),
                ) );
            }
        }
    }

    private function has_test_keywords( $order ) {
        // Check if order has test-related content.
        $wc_order = wc_get_order( $order->id );
        if ( ! $wc_order ) {
            return false;
        }

        $searchable = strtolower( implode( ' ', array(
            $wc_order->get_billing_first_name(),
            $wc_order->get_billing_last_name(),
            $wc_order->get_billing_company(),
            $wc_order->get_customer_note(),
        ) ) );

        foreach ( $this->test_keywords as $keyword ) {
            if ( strpos( $searchable, $keyword ) !== false ) {
                return true;
            }
        }

        return false;
    }

    private function normalize_status( $status ) {
        return str_replace( 'wc-', '', $status );
    }
}
