<?php
namespace DataHygiene\Gateways;

defined( 'ABSPATH' ) || exit;

class Stripe_Reconciler {

    private $secret_key;

    public function __construct() {
        // Try to get Stripe secret key from WooCommerce Stripe plugin settings.
        $stripe_settings = get_option( 'woocommerce_stripe_settings', array() );
        $testmode        = ( $stripe_settings['testmode'] ?? 'no' ) === 'yes';

        $this->secret_key = $testmode
            ? ( $stripe_settings['test_secret_key'] ?? '' )
            : ( $stripe_settings['secret_key'] ?? '' );
    }

    /**
     * Get Stripe transactions for a date range.
     *
     * @param string $from Y-m-d.
     * @param string $to   Y-m-d.
     * @return array|\WP_Error
     */
    public function get_transactions( string $from, string $to ) {
        if ( empty( $this->secret_key ) ) {
            return new \WP_Error(
                'stripe_not_configured',
                __( 'Stripe API key not found. Make sure WooCommerce Stripe Gateway is configured.', 'data-hygiene-for-woocommerce' )
            );
        }

        $transactions = array();
        $has_more     = true;
        $starting_after = null;

        while ( $has_more ) {
            $args = array(
                'limit'           => 100,
                'created[gte]'    => strtotime( $from . ' 00:00:00' ),
                'created[lte]'    => strtotime( $to . ' 23:59:59' ),
                'type'            => 'charge',
                'expand[]'        => 'data.source',
            );

            if ( $starting_after ) {
                $args['starting_after'] = $starting_after;
            }

            $response = $this->api_request( 'balance_transactions', $args );

            if ( is_wp_error( $response ) ) {
                return $response;
            }

            foreach ( $response['data'] as $txn ) {
                // Convert from cents to currency units.
                $amount = $txn['amount'] / 100;

                // Only include successful charges.
                if ( $txn['status'] !== 'available' && $txn['status'] !== 'pending' ) {
                    continue;
                }

                $transactions[] = array(
                    'id'       => $txn['source'] ?? $txn['id'],
                    'amount'   => $amount,
                    'currency' => strtoupper( $txn['currency'] ),
                    'date'     => wp_date( 'Y-m-d H:i:s', $txn['created'] ),
                    'status'   => $txn['status'],
                    'fee'      => ( $txn['fee'] ?? 0 ) / 100,
                    'net'      => ( $txn['net'] ?? 0 ) / 100,
                );
            }

            $has_more = $response['has_more'] ?? false;
            if ( $has_more && ! empty( $response['data'] ) ) {
                $last           = end( $response['data'] );
                $starting_after = $last['id'];
            }
        }

        return $transactions;
    }

    /**
     * Make a Stripe API request.
     */
    private function api_request( string $endpoint, array $params = array() ) {
        $url = 'https://api.stripe.com/v1/' . $endpoint;

        $response = wp_remote_get( add_query_arg( $params, $url ), array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $this->secret_key,
            ),
            'timeout' => 30,
        ) );

        if ( is_wp_error( $response ) ) {
            return $response;
        }

        $code = wp_remote_retrieve_response_code( $response );
        $body = json_decode( wp_remote_retrieve_body( $response ), true );

        if ( $code !== 200 ) {
            $message = $body['error']['message'] ?? 'Unknown Stripe API error';
            return new \WP_Error( 'stripe_api_error', $message );
        }

        return $body;
    }
}
