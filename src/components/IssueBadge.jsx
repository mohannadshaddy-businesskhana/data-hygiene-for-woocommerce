import { __ } from '@wordpress/i18n';

const ISSUE_LABELS = {
    orphan_order: __('Orphan Order', 'data-hygiene-for-woocommerce'),
    orphan_lookup: __('Orphan Lookup', 'data-hygiene-for-woocommerce'),
    test_order: __('Test Order', 'data-hygiene-for-woocommerce'),
    duplicate: __('Duplicate', 'data-hygiene-for-woocommerce'),
    status_mismatch: __('Status Mismatch', 'data-hygiene-for-woocommerce'),
    missing_transaction: __('Missing Txn', 'data-hygiene-for-woocommerce'),
    stale_order: __('Stale Order', 'data-hygiene-for-woocommerce'),
    future_date: __('Future Date', 'data-hygiene-for-woocommerce'),
    pre_store_date: __('Pre-Store Date', 'data-hygiene-for-woocommerce'),
    suspicious_date: __('Suspicious Date', 'data-hygiene-for-woocommerce'),
    invalid_date: __('Invalid Date', 'data-hygiene-for-woocommerce'),
    negative_amount: __('Negative Amount', 'data-hygiene-for-woocommerce'),
    zero_amount: __('Zero Amount', 'data-hygiene-for-woocommerce'),
    missing_product: __('Missing Product', 'data-hygiene-for-woocommerce'),
};

export default function IssueBadge({ type, severity }) {
    const label = ISSUE_LABELS[type] || type;

    return (
        <span className={`datahyg-badge datahyg-badge--${severity || 'medium'}`}>
            {label}
        </span>
    );
}
