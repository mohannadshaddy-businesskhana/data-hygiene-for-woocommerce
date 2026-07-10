<?php
namespace DataHygiene;

defined( 'ABSPATH' ) || exit;

/**
 * Base class for individual scan modules.
 */
abstract class Scan_Module {

    protected $scanner;
    protected $issues = array();

    public function __construct( Scanner $scanner ) {
        $this->scanner = $scanner;
    }

    /**
     * Scan a batch of orders.
     *
     * @param array $orders Array of order objects.
     */
    abstract public function scan_batch( array $orders );

    /**
     * Get all issues found by this module.
     *
     * @return array
     */
    public function get_issues() {
        return $this->issues;
    }

    /**
     * Add an issue.
     *
     * @param array $issue Issue data.
     */
    protected function add_issue( array $issue ) {
        $issue['scan_id'] = $this->scanner->get_scan_id();
        $this->issues[]   = $issue;
    }
}
