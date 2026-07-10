import { useState } from '@wordpress/element';
import { Button, CheckboxControl } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import IssueBadge from './IssueBadge';

export default function QuarantineTable({ items, onRestore, onDelete, onBulkRestore }) {
    const [selected, setSelected] = useState([]);

    const toggleSelect = (id) => {
        setSelected((prev) =>
            prev.includes(id) ? prev.filter((i) => i !== id) : [...prev, id]
        );
    };

    const toggleAll = () => {
        if (selected.length === items.length) {
            setSelected([]);
        } else {
            setSelected(items.map((i) => i.id));
        }
    };

    return (
        <div>
            {selected.length > 0 && (
                <div className="datahyg-actions">
                    <span>
                        {selected.length} {__('selected', 'data-hygiene-for-woocommerce')}
                    </span>
                    <Button
                        variant="secondary"
                        onClick={() => {
                            onBulkRestore(selected);
                            setSelected([]);
                        }}
                    >
                        {__('Restore Selected', 'data-hygiene-for-woocommerce')}
                    </Button>
                </div>
            )}
            <table className="datahyg-table">
                <thead>
                    <tr>
                        <th style={{ width: 30 }}>
                            <CheckboxControl
                                checked={selected.length === items.length && items.length > 0}
                                onChange={toggleAll}
                                __nextHasNoMarginBottom
                            />
                        </th>
                        <th>{__('Order', 'data-hygiene-for-woocommerce')}</th>
                        <th>{__('Issue', 'data-hygiene-for-woocommerce')}</th>
                        <th>{__('Severity', 'data-hygiene-for-woocommerce')}</th>
                        <th>{__('Description', 'data-hygiene-for-woocommerce')}</th>
                        <th>{__('Date', 'data-hygiene-for-woocommerce')}</th>
                        <th>{__('Actions', 'data-hygiene-for-woocommerce')}</th>
                    </tr>
                </thead>
                <tbody>
                    {items.map((item) => (
                        <tr key={item.id}>
                            <td>
                                <CheckboxControl
                                    checked={selected.includes(item.id)}
                                    onChange={() => toggleSelect(item.id)}
                                    __nextHasNoMarginBottom
                                />
                            </td>
                            <td>#{item.item_id}</td>
                            <td>
                                <IssueBadge type={item.issue_type} severity={item.severity} />
                            </td>
                            <td>
                                <span className={`datahyg-badge datahyg-badge--${item.severity}`}>
                                    {item.severity}
                                </span>
                            </td>
                            <td>{item.description}</td>
                            <td>{item.created_at}</td>
                            <td>
                                <Button
                                    variant="secondary"
                                    isSmall
                                    onClick={() => onRestore(item.id)}
                                >
                                    {__('Restore', 'data-hygiene-for-woocommerce')}
                                </Button>{' '}
                                <Button
                                    variant="link"
                                    isDestructive
                                    isSmall
                                    onClick={() => {
                                        if (window.confirm(__('Permanently delete this item?', 'data-hygiene-for-woocommerce'))) {
                                            onDelete(item.id);
                                        }
                                    }}
                                >
                                    {__('Delete', 'data-hygiene-for-woocommerce')}
                                </Button>
                            </td>
                        </tr>
                    ))}
                </tbody>
            </table>
        </div>
    );
}
