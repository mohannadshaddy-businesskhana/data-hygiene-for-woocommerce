import { useState, useEffect } from '@wordpress/element';
import { Button, Notice } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { api } from '../api';
import ConfidenceGauge from '../components/ConfidenceGauge';
import ScanProgress from '../components/ScanProgress';

export default function Dashboard({ onRefresh }) {
    const [data, setData] = useState(null);
    const [loading, setLoading] = useState(true);
    const [scanning, setScanning] = useState(false);
    const [error, setError] = useState('');

    useEffect(() => {
        loadDashboard();
    }, []);

    const loadDashboard = async () => {
        try {
            setLoading(true);
            const result = await api.getDashboard();
            setData(result);
        } catch (err) {
            setError(err.message);
        } finally {
            setLoading(false);
        }
    };

    const runScan = async (type) => {
        try {
            setScanning(true);
            setError('');
            await api.runScan(type);
            await loadDashboard();
            onRefresh();
        } catch (err) {
            setError(err.message);
        } finally {
            setScanning(false);
        }
    };

    if (loading) {
        return <ScanProgress message={__('Loading dashboard...', 'data-hygiene-for-woocommerce')} />;
    }

    if (scanning) {
        return <ScanProgress message={__('Running scan... This may take a few minutes for large stores.', 'data-hygiene-for-woocommerce')} />;
    }

    const lastScan = data?.last_scan;
    const score = lastScan ? parseFloat(lastScan.confidence_score) : null;

    return (
        <div>
            {error && (
                <Notice status="error" isDismissible onDismiss={() => setError('')}>
                    {error}
                </Notice>
            )}

            <div className="datahyg-actions">
                <Button variant="primary" onClick={() => runScan('full')}>
                    {__('Run Full Scan', 'data-hygiene-for-woocommerce')}
                </Button>
                <Button variant="secondary" onClick={() => runScan('quick')}>
                    {__('Quick Scan', 'data-hygiene-for-woocommerce')}
                </Button>
            </div>

            {!lastScan ? (
                <div className="datahyg-empty">
                    <p>{__('No scans have been run yet. Run your first scan to check data integrity.', 'data-hygiene-for-woocommerce')}</p>
                </div>
            ) : (
                <>
                    <div className="datahyg-grid">
                        <div className="datahyg-card">
                            <h2>{__('Data Confidence Score', 'data-hygiene-for-woocommerce')}</h2>
                            <ConfidenceGauge score={score} />
                        </div>

                        <div className="datahyg-card">
                            <h2>{__('Last Scan Summary', 'data-hygiene-for-woocommerce')}</h2>
                            <div className="datahyg-stats">
                                <div className="datahyg-stat">
                                    <div className="datahyg-stat-value">{lastScan.total_orders_scanned}</div>
                                    <div className="datahyg-stat-label">{__('Orders Scanned', 'data-hygiene-for-woocommerce')}</div>
                                </div>
                                <div className="datahyg-stat">
                                    <div className="datahyg-stat-value">{lastScan.issues_found}</div>
                                    <div className="datahyg-stat-label">{__('Issues Found', 'data-hygiene-for-woocommerce')}</div>
                                </div>
                                <div className="datahyg-stat">
                                    <div className="datahyg-stat-value">{data.total_quarantined}</div>
                                    <div className="datahyg-stat-label">{__('Quarantined', 'data-hygiene-for-woocommerce')}</div>
                                </div>
                            </div>
                            <p style={{ marginTop: 16, color: '#646970', fontSize: 13 }}>
                                {__('Last scan:', 'data-hygiene-for-woocommerce')} {lastScan.started_at}
                            </p>
                        </div>
                    </div>

                    {/* Issues by type */}
                    {lastScan.summary?.by_type && Object.keys(lastScan.summary.by_type).length > 0 && (
                        <div className="datahyg-card">
                            <h2>{__('Issues by Type', 'data-hygiene-for-woocommerce')}</h2>
                            <table className="datahyg-table">
                                <thead>
                                    <tr>
                                        <th>{__('Issue Type', 'data-hygiene-for-woocommerce')}</th>
                                        <th>{__('Count', 'data-hygiene-for-woocommerce')}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {Object.entries(lastScan.summary.by_type).map(([type, count]) => (
                                        <tr key={type}>
                                            <td>{type.replace(/_/g, ' ')}</td>
                                            <td>{count}</td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    )}

                    {/* Scan History */}
                    {data.scan_history?.length > 1 && (
                        <div className="datahyg-card">
                            <h2>{__('Score History', 'data-hygiene-for-woocommerce')}</h2>
                            <div className="datahyg-chart">
                                {data.scan_history.slice().reverse().map((scan) => {
                                    const s = parseFloat(scan.confidence_score) || 0;
                                    const color = s >= 80 ? '#00a32a' : s >= 50 ? '#dba617' : '#d63638';
                                    return (
                                        <div
                                            key={scan.id}
                                            className="datahyg-chart-bar"
                                            style={{
                                                height: `${s}%`,
                                                background: color,
                                            }}
                                            title={`${scan.started_at}: ${Math.round(s)}%`}
                                        />
                                    );
                                })}
                            </div>
                        </div>
                    )}
                </>
            )}
        </div>
    );
}
