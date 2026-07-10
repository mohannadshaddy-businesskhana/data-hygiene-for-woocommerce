<?php
namespace DataHygiene;

defined( 'ABSPATH' ) || exit;

class Confidence_Scorer {

    /**
     * Issue type weights — how much each type of issue affects the score.
     * Higher weight = more impact on score.
     */
    private $weights = array(
        'orphan_order'       => 5.0,
        'orphan_lookup'      => 2.0,
        'test_order'         => 3.0,
        'duplicate'          => 8.0,
        'status_mismatch'    => 4.0,
        'missing_transaction' => 3.0,
        'stale_order'        => 1.0,
        'future_date'        => 10.0,
        'pre_store_date'     => 3.0,
        'suspicious_date'    => 5.0,
        'invalid_date'       => 7.0,
        'negative_amount'    => 10.0,
        'zero_amount'        => 3.0,
        'missing_product'    => 1.0,
    );

    /**
     * Severity multipliers.
     */
    private $severity_multipliers = array(
        'critical' => 2.0,
        'high'     => 1.5,
        'medium'   => 1.0,
        'low'      => 0.5,
    );

    /**
     * Calculate the data confidence score.
     *
     * @param int   $total_orders Total number of orders scanned.
     * @param array $issues       Array of issues found.
     * @return float Score between 0 and 100.
     */
    public function calculate( int $total_orders, array $issues ) {
        if ( $total_orders === 0 ) {
            return 100.0;
        }

        $penalty = 0;

        foreach ( $issues as $issue ) {
            $type     = $issue['issue_type'] ?? '';
            $severity = $issue['severity'] ?? 'medium';

            $weight     = $this->weights[ $type ] ?? 2.0;
            $multiplier = $this->severity_multipliers[ $severity ] ?? 1.0;

            $penalty += $weight * $multiplier;
        }

        // Normalize penalty relative to total orders.
        // This means a store with 1000 orders and 10 issues scores higher
        // than a store with 50 orders and 10 issues.
        $issue_ratio    = count( $issues ) / $total_orders;
        $weighted_ratio = $penalty / ( $total_orders * 2 ); // Normalize.

        // Combine both ratios.
        $combined_penalty = ( $issue_ratio * 30 ) + ( $weighted_ratio * 70 );

        $score = max( 0, min( 100, 100 - $combined_penalty ) );

        return round( $score, 2 );
    }

    /**
     * Get a human-readable label for the score.
     */
    public static function get_label( float $score ) {
        if ( $score >= 90 ) {
            return __( 'Excellent', 'data-hygiene-for-woocommerce' );
        }
        if ( $score >= 75 ) {
            return __( 'Good', 'data-hygiene-for-woocommerce' );
        }
        if ( $score >= 50 ) {
            return __( 'Fair', 'data-hygiene-for-woocommerce' );
        }
        if ( $score >= 25 ) {
            return __( 'Poor', 'data-hygiene-for-woocommerce' );
        }
        return __( 'Critical', 'data-hygiene-for-woocommerce' );
    }

    /**
     * Get the color for a score.
     */
    public static function get_color( float $score ) {
        if ( $score >= 80 ) {
            return '#00a32a';
        }
        if ( $score >= 50 ) {
            return '#dba617';
        }
        return '#d63638';
    }
}
