import { useState, useEffect } from '@wordpress/element';
import { Button, Notice, SelectControl } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { api } from '../api';
import QuarantineTable from '../components/QuarantineTable';
import ScanProgress from '../components/ScanProgress';

export default function Quarantine() {
    const [data, setData] = useState(null);
    const [loading, setLoading] = useState(true);
    const [filter, setFilter] = useState('quarantined');
    const [page, setPage] = useState(1);
    const [message, setMessage] = useState('');

    useEffect(() => {
        loadItems();
    }, [filter, page]);

    const loadItems = async () => {
        try {
            setLoading(true);
            const result = await api.listQuarantine({ status: filter, page, per_page: 20 });
            setData(result);
        } catch (err) {
            setMessage(err.message);
        } finally {
            setLoading(false);
        }
    };

    const handleRestore = async (id) => {
        try {
            await api.restoreItem(id);
            setMessage(__('Item restored successfully.', 'data-hygiene-for-woocommerce'));
            loadItems();
        } catch (err) {
            setMessage(err.message);
        }
    };

    const handleDelete = async (id) => {
        try {
            await api.deleteItem(id);
            setMessage(__('Item deleted.', 'data-hygiene-for-woocommerce'));
            loadItems();
        } catch (err) {
            setMessage(err.message);
        }
    };

    const handleBulkRestore = async (ids) => {
        try {
            const result = await api.bulkRestore(ids);
            setMessage(`${result.restored} items restored.`);
            loadItems();
        } catch (err) {
            setMessage(err.message);
        }
    };

    if (loading) {
        return <ScanProgress message={__('Loading quarantine...', 'data-hygiene-for-woocommerce')} />;
    }

    const items = data?.items || [];

    return (
        <div>
            {message && (
                <Notice status="info" isDismissible onDismiss={() => setMessage('')}>
                    {message}
                </Notice>
            )}

            <div className="datahyg-actions">
                <SelectControl
                    value={filter}
                    options={[
                        { label: __('Quarantined', 'data-hygiene-for-woocommerce'), value: 'quarantined' },
                        { label: __('Restored', 'data-hygiene-for-woocommerce'), value: 'restored' },
                        { label: __('Deleted', 'data-hygiene-for-woocommerce'), value: 'deleted' },
                    ]}
                    onChange={(val) => {
                        setFilter(val);
                        setPage(1);
                    }}
                    __nextHasNoMarginBottom
                />
                <span style={{ color: '#646970' }}>
                    {data?.total || 0} {__('items', 'data-hygiene-for-woocommerce')}
                </span>
            </div>

            {items.length === 0 ? (
                <div className="datahyg-empty">
                    <p>{__('No items in quarantine.', 'data-hygiene-for-woocommerce')}</p>
                </div>
            ) : (
                <>
                    <div className="datahyg-card">
                        <QuarantineTable
                            items={items}
                            onRestore={handleRestore}
                            onDelete={handleDelete}
                            onBulkRestore={handleBulkRestore}
                        />
                    </div>

                    {data.pages > 1 && (
                        <div className="datahyg-pagination">
                            <Button
                                variant="secondary"
                                disabled={page <= 1}
                                onClick={() => setPage((p) => p - 1)}
                            >
                                {__('Previous', 'data-hygiene-for-woocommerce')}
                            </Button>
                            <span>
                                {page} / {data.pages}
                            </span>
                            <Button
                                variant="secondary"
                                disabled={page >= data.pages}
                                onClick={() => setPage((p) => p + 1)}
                            >
                                {__('Next', 'data-hygiene-for-woocommerce')}
                            </Button>
                        </div>
                    )}
                </>
            )}
        </div>
    );
}
