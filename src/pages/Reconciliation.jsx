import { useState, useEffect } from '@wordpress/element';
import { Button, Notice, SelectControl, TextControl } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { api } from '../api';
import ScanProgress from '../components/ScanProgress';

export default function Reconciliation() {
    const [gateway, setGateway] = useState('stripe');
    const [from, setFrom] = useState('');
    const [to, setTo] = useState('');
    const [data, setData] = useState(null);
    const [loading, setLoading] = useState(false);
    const [reconciling, setReconciling] = useState(false);
    const [statusFilter, setStatusFilter] = useState('');
    const [error, setError] = useState('');

    useEffect(() => {
        loadData();
    }, [statusFilter]);

    const loadData = async () => {
        try {
            setLoading(true);
            const result = await api.getReconciliation({ status: statusFilter, gateway });
            setData(result);
        } catch (err) {
            // No cached data yet — that's fine.
            setData(null);
        } finally {
            setLoading(false);
        }
    };

    const runReconcile = async () => {
        try {
            setReconciling(true);
            setError('');
            await api.runReconciliation(gateway, from, to);
            await loadData();
        } catch (err) {
            setError(err.message);
        } finally {
            setReconciling(false);
        }
    };

    if (reconciling) {
        return <ScanProgress message={__('Reconciling with payment gateway...', 'data-hygiene-for-woocommerce')} />;
    }

    const items = data?.items || [];
    const summary = data?.summary;

    return (
        <div>
            {error && (
                <Notice status="error" isDismissible onDismiss={() => setError('')}>
                    {error}
                </Notice>
            )}

            <div className="datahyg-card">
                <h2>{__('Payment Gateway Reconciliation', 'data-hygiene-for-woocommerce')}</h2>
                <div className="datahyg-grid" style={{ gridTemplateColumns: 'repeat(4, 1fr)' }}>
                    <SelectControl
                        label={__('Gateway', 'data-hygiene-for-woocommerce')}
                        value={gateway}
                        options={[
                            { label: 'Stripe', value: 'stripe' },
                            { label: 'PayPal', value: 'paypal' },
                        ]}
                        onChange={setGateway}
                        __nextHasNoMarginBottom
                    />
                    <TextControl
                        label={__('From Date', 'data-hygiene-for-woocommerce')}
                        type="date"
                        value={from}
                        onChange={setFrom}
                        __nextHasNoMarginBottom
                    />
                    <TextControl
                        label={__('To Date', 'data-hygiene-for-woocommerce')}
                        type="date"
                        value={to}
                        onChange={setTo}
                        __nextHasNoMarginBottom
                    />
                    <div style={{ display: 'flex', alignItems: 'flex-end' }}>
                        <Button variant="primary" onClick={runReconcile}>
                            {__('Run Reconciliation', 'data-hygiene-for-woocommerce')}
                        </Button>
                    </div>
                </div>
            </div>

            {summary && (
                <div className="datahyg-card">
                    <h2>{__('Summary', 'data-hygiene-for-woocommerce')}</h2>
                    <div className="datahyg-stats">
                        <div className="datahyg-stat">
                            <div className="datahyg-stat-value" style={{ color: '#00a32a' }}>{summary.matched}</div>
                            <div className="datahyg-stat-label">{__('Matched', 'data-hygiene-for-woocommerce')}</div>
                        </div>
                        <div className="datahyg-stat">
                            <div className="datahyg-stat-value" style={{ color: '#d63638' }}>{summary.mismatch}</div>
                            <div className="datahyg-stat-label">{__('Mismatch', 'data-hygiene-for-woocommerce')}</div>
                        </div>
                        <div className="datahyg-stat">
                            <div className="datahyg-stat-value" style={{ color: '#dba617' }}>{summary.missing_gateway}</div>
                            <div className="datahyg-stat-label">{__('Missing in Gateway', 'data-hygiene-for-woocommerce')}</div>
                        </div>
                        <div className="datahyg-stat">
                            <div className="datahyg-stat-value" style={{ color: '#dba617' }}>{summary.missing_wc}</div>
                            <div className="datahyg-stat-label">{__('Missing in WC', 'data-hygiene-for-woocommerce')}</div>
                        </div>
                        <div className="datahyg-stat">
                            <div className="datahyg-stat-value" style={{ color: '#d63638' }}>
                                ${summary.total_discrepancy.toFixed(2)}
                            </div>
                            <div className="datahyg-stat-label">{__('Total Discrepancy', 'data-hygiene-for-woocommerce')}</div>
                        </div>
                    </div>
                </div>
            )}

            {items.length > 0 && (
                <div className="datahyg-card">
                    <div className="datahyg-actions">
                        <h2 style={{ margin: 0 }}>{__('Details', 'data-hygiene-for-woocommerce')}</h2>
                        <SelectControl
                            value={statusFilter}
                            options={[
                                { label: __('All', 'data-hygiene-for-woocommerce'), value: '' },
                                { label: __('Matched', 'data-hygiene-for-woocommerce'), value: 'matched' },
                                { label: __('Mismatch', 'data-hygiene-for-woocommerce'), value: 'mismatch' },
                                { label: __('Missing in Gateway', 'data-hygiene-for-woocommerce'), value: 'missing_gateway' },
                                { label: __('Missing in WC', 'data-hygiene-for-woocommerce'), value: 'missing_wc' },
                            ]}
                            onChange={setStatusFilter}
                            __nextHasNoMarginBottom
                        />
                    </div>
                    <table className="datahyg-table">
                        <thead>
                            <tr>
                                <th>{__('Order', 'data-hygiene-for-woocommerce')}</th>
                                <th>{__('WC Total', 'data-hygiene-for-woocommerce')}</th>
                                <th>{__('Gateway Total', 'data-hygiene-for-woocommerce')}</th>
                                <th>{__('Discrepancy', 'data-hygiene-for-woocommerce')}</th>
                                <th>{__('Transaction ID', 'data-hygiene-for-woocommerce')}</th>
                                <th>{__('Status', 'data-hygiene-for-woocommerce')}</th>
                            </tr>
                        </thead>
                        <tbody>
                            {items.map((item) => (
                                <tr key={item.id}>
                                    <td>{item.order_id > 0 ? `#${item.order_id}` : '—'}</td>
                                    <td>{item.wc_total !== null ? `$${parseFloat(item.wc_total).toFixed(2)}` : '—'}</td>
                                    <td>{item.gateway_total !== null ? `$${parseFloat(item.gateway_total).toFixed(2)}` : '—'}</td>
                                    <td style={{ color: item.discrepancy && parseFloat(item.discrepancy) !== 0 ? '#d63638' : 'inherit' }}>
                                        {item.discrepancy !== null ? `$${parseFloat(item.discrepancy).toFixed(2)}` : '—'}
                                    </td>
                                    <td style={{ fontSize: 12, fontFamily: 'monospace' }}>
                                        {item.gateway_txn_id || '—'}
                                    </td>
                                    <td>
                                        <span className={`datahyg-badge datahyg-badge--${item.status}`}>
                                            {item.status.replace(/_/g, ' ')}
                                        </span>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            )}

            {!summary && !loading && (
                <div className="datahyg-empty">
                    <p>{__('No reconciliation data. Run a reconciliation to compare WooCommerce orders with payment gateway records.', 'data-hygiene-for-woocommerce')}</p>
                </div>
            )}
        </div>
    );
}
