import { useState, useEffect } from '@wordpress/element';
import { Button, Notice, SelectControl } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { api } from '../api';
import IssueBadge from '../components/IssueBadge';
import ScanProgress from '../components/ScanProgress';

export default function ScanResults() {
    const [scans, setScans] = useState([]);
    const [selectedScan, setSelectedScan] = useState(null);
    const [scanDetail, setScanDetail] = useState(null);
    const [loading, setLoading] = useState(true);
    const [quarantining, setQuarantining] = useState(false);
    const [message, setMessage] = useState('');

    useEffect(() => {
        loadScans();
    }, []);

    const loadScans = async () => {
        try {
            setLoading(true);
            const result = await api.listScans();
            setScans(result);
            if (result.length > 0) {
                await loadScanDetail(result[0].id);
            }
        } catch (err) {
            setMessage(err.message);
        } finally {
            setLoading(false);
        }
    };

    const loadScanDetail = async (id) => {
        try {
            setSelectedScan(id);
            const detail = await api.getScan(id);
            setScanDetail(detail);
        } catch (err) {
            setMessage(err.message);
        }
    };

    const handleBulkQuarantine = async (issueTypes = []) => {
        if (!selectedScan) return;
        try {
            setQuarantining(true);
            const result = await api.bulkQuarantine(selectedScan, issueTypes);
            setMessage(`${result.quarantined} items quarantined.`);
        } catch (err) {
            setMessage(err.message);
        } finally {
            setQuarantining(false);
        }
    };

    if (loading) {
        return <ScanProgress message={__('Loading scan results...', 'data-hygiene-for-woocommerce')} />;
    }

    if (scans.length === 0) {
        return (
            <div className="datahyg-empty">
                <p>{__('No scans found. Run a scan from the Dashboard tab.', 'data-hygiene-for-woocommerce')}</p>
            </div>
        );
    }

    const issues = scanDetail?.issues || [];
    const issueTypes = [...new Set(issues.map((i) => i.issue_type))];

    return (
        <div>
            {message && (
                <Notice status="info" isDismissible onDismiss={() => setMessage('')}>
                    {message}
                </Notice>
            )}

            <div className="datahyg-actions">
                <SelectControl
                    value={selectedScan}
                    options={scans.map((s) => ({
                        label: `${s.started_at} — ${s.scan_type} (${s.issues_found} issues)`,
                        value: s.id,
                    }))}
                    onChange={loadScanDetail}
                    __nextHasNoMarginBottom
                />
            </div>

            {scanDetail && (
                <>
                    <div className="datahyg-grid">
                        <div className="datahyg-card">
                            <div className="datahyg-stats">
                                <div className="datahyg-stat">
                                    <div className="datahyg-stat-value">{scanDetail.total_orders_scanned}</div>
                                    <div className="datahyg-stat-label">{__('Scanned', 'data-hygiene-for-woocommerce')}</div>
                                </div>
                                <div className="datahyg-stat">
                                    <div className="datahyg-stat-value">{scanDetail.issues_found}</div>
                                    <div className="datahyg-stat-label">{__('Issues', 'data-hygiene-for-woocommerce')}</div>
                                </div>
                                <div className="datahyg-stat">
                                    <div className="datahyg-stat-value">
                                        {Math.round(parseFloat(scanDetail.confidence_score))}%
                                    </div>
                                    <div className="datahyg-stat-label">{__('Score', 'data-hygiene-for-woocommerce')}</div>
                                </div>
                            </div>
                        </div>
                    </div>

                    {issues.length > 0 && (
                        <div className="datahyg-card">
                            <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: 15 }}>
                                <h2 style={{ margin: 0 }}>{__('Issues Found', 'data-hygiene-for-woocommerce')}</h2>
                                <Button
                                    variant="primary"
                                    isBusy={quarantining}
                                    onClick={() => handleBulkQuarantine()}
                                >
                                    {__('Quarantine All', 'data-hygiene-for-woocommerce')}
                                </Button>
                            </div>

                            {issueTypes.length > 1 && (
                                <div className="datahyg-actions" style={{ flexWrap: 'wrap' }}>
                                    {issueTypes.map((type) => (
                                        <Button
                                            key={type}
                                            variant="secondary"
                                            isSmall
                                            onClick={() => handleBulkQuarantine([type])}
                                        >
                                            {__('Quarantine', 'data-hygiene-for-woocommerce')} {type.replace(/_/g, ' ')}
                                        </Button>
                                    ))}
                                </div>
                            )}

                            <table className="datahyg-table">
                                <thead>
                                    <tr>
                                        <th>{__('Order', 'data-hygiene-for-woocommerce')}</th>
                                        <th>{__('Type', 'data-hygiene-for-woocommerce')}</th>
                                        <th>{__('Issue', 'data-hygiene-for-woocommerce')}</th>
                                        <th>{__('Severity', 'data-hygiene-for-woocommerce')}</th>
                                        <th>{__('Description', 'data-hygiene-for-woocommerce')}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {issues.map((issue, idx) => (
                                        <tr key={idx}>
                                            <td>#{issue.item_id}</td>
                                            <td>{issue.item_type}</td>
                                            <td>
                                                <IssueBadge type={issue.issue_type} severity={issue.severity} />
                                            </td>
                                            <td>
                                                <span className={`datahyg-badge datahyg-badge--${issue.severity}`}>
                                                    {issue.severity}
                                                </span>
                                            </td>
                                            <td>{issue.description}</td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    )}
                </>
            )}
        </div>
    );
}
