<?php
namespace DataHygiene\Gateways;

defined( 'ABSPATH' ) || exit;

class Paypal_Reconciler {

    private $client_id;
    private $client_secret;
    private $api_base;

    public function __construct() {
        // Try WooCommerce PayPal Payments (PPCP) settings first.
        $ppcp_settings = get_option( 'woocommerce-ppcp-settings', array() );

        if ( ! empty( $ppcp_settings ) ) {
            $sandbox         = ! empty( $ppcp_settings['sandbox_on'] );
            $this->client_id     = $sandbox
                ? ( $ppcp_settings['client_id_sandbox'] ?? '' )
                : ( $ppcp_settings['client_id_production'] ?? '' );
            $this->client_secret = $sandbox
                ? ( $ppcp_settings['client_secret_sandbox'] ?? '' )
                : ( $ppcp_settings['client_secret_production'] ?? '' );
            $this->api_base      = $sandbox
                ? 'https://api-m.sandbox.paypal.com'
                : 'https://api-m.paypal.com';
            return;
        }

        // Fallback: legacy PayPal Standard / Express Checkout.
        $paypal_settings = get_option( 'woocommerce_paypal_settings', array() );
        $testmode        = ( $paypal_settings['testmode'] ?? 'no' ) === 'yes';

        $this->client_id     = $paypal_settings['api_username'] ?? '';
        $this->client_secret = $paypal_settings['api_password'] ?? '';
        $this->api_base      = $testmode
            ? 'https://api-m.sandbox.paypal.com'
            : 'https://api-m.paypal.com';
    }

    /**
     * Get PayPal transactions for a date range.
     */
    public function get_transactions( string $from, string $to ) {
        if ( empty( $this->client_id ) || empty( $this->client_secret ) ) {
            return new \WP_Error(
                'paypal_not_configured',
                __( 'PayPal API credentials not found. Make sure WooCommerce PayPal is configured.', 'data-hygiene-for-woocommerce' )
            );
        }

        // Get access token.
        $token = $this->get_access_token();
        if ( is_wp_error( $token ) ) {
            return $token;
        }

        $transactions = array();
        $page         = 1;
        $has_more     = true;

        while ( $has_more ) {
            $response = $this->api_request( $token, '/v1/reporting/transactions', array(
                'start_date'       => $from . 'T00:00:00-0000',
                'end_date'         => $to . 'T23:59:59-0000',
                'fields'           => 'transaction_info,payer_info',
                'page_size'        => 100,
                'page'             => $page,
                'transaction_type' => 'T0006', // Payment received.
            ) );

            if ( is_wp_error( $response ) ) {
                return $response;
            }

            $details = $response['transaction_details'] ?? array();
            foreach ( $details as $txn ) {
                $info   = $txn['transaction_info'] ?? array();
                $amount = floatval( $info['transaction_amount']['value'] ?? 0 );

                // Only include completed payments.
                $status = $info['transaction_status'] ?? '';
                if ( ! in_array( $status, array( 'S', 'V' ), true ) ) {
                    continue;
                }

                $transactions[] = array(
                    'id'       => $info['transaction_id'] ?? '',
                    'amount'   => abs( $amount ),
                    'currency' => $info['transaction_amount']['currency_code'] ?? 'USD',
                    'date'     => $info['transaction_initiation_date'] ?? '',
                    'status'   => $status,
                    'fee'      => abs( floatval( $info['fee_amount']['value'] ?? 0 ) ),
                );
            }

            $total_pages = $response['total_pages'] ?? 1;
            $has_more    = $page < $total_pages;
            $page++;
        }

        return $transactions;
    }

    private function get_access_token() {
        $response = wp_remote_post( $this->api_base . '/v1/oauth2/token', array(
            'headers' => array(
                'Authorization' => 'Basic ' . base64_encode( $this->client_id . ':' . $this->client_secret ),
                'Content-Type'  => 'application/x-www-form-urlencoded',
            ),
            'body'    => 'grant_type=client_credentials',
            'timeout' => 15,
        ) );

        if ( is_wp_error( $response ) ) {
            return $response;
        }

        $body = json_decode( wp_remote_retrieve_body( $response ), true );
        if ( empty( $body['access_token'] ) ) {
            return new \WP_Error( 'paypal_auth_failed', $body['error_description'] ?? 'PayPal authentication failed.' );
        }

        return $body['access_token'];
    }

    private function api_request( string $token, string $endpoint, array $params = array() ) {
        $url = $this->api_base . $endpoint . '?' . http_build_query( $params );

        $response = wp_remote_get( $url, array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $token,
                'Content-Type'  => 'application/json',
            ),
            'timeout' => 30,
        ) );

        if ( is_wp_error( $response ) ) {
            return $response;
        }

        $code = wp_remote_retrieve_response_code( $response );
        $body = json_decode( wp_remote_retrieve_body( $response ), true );

        if ( $code !== 200 ) {
            $message = $body['message'] ?? 'Unknown PayPal API error';
            return new \WP_Error( 'paypal_api_error', $message );
        }

        return $body;
    }
}
