import { useState, useEffect } from '@wordpress/element';
import { Button, Notice, TextControl, SelectControl, ToggleControl } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { api } from '../api';
import ScanProgress from '../components/ScanProgress';

export default function Settings() {
    const [settings, setSettings] = useState({});
    const [loading, setLoading] = useState(true);
    const [saving, setSaving] = useState(false);
    const [message, setMessage] = useState('');

    useEffect(() => {
        loadSettings();
    }, []);

    const loadSettings = async () => {
        try {
            setLoading(true);
            const result = await api.getSettings();
            setSettings(result);
        } catch (err) {
            setMessage(err.message);
        } finally {
            setLoading(false);
        }
    };

    const saveSettings = async () => {
        try {
            setSaving(true);
            await api.updateSettings(settings);
            setMessage(__('Settings saved successfully.', 'data-hygiene-for-woocommerce'));
        } catch (err) {
            setMessage(err.message);
        } finally {
            setSaving(false);
        }
    };

    const update = (key, value) => {
        setSettings((prev) => ({ ...prev, [key]: value }));
    };

    if (loading) {
        return <ScanProgress message={__('Loading settings...', 'data-hygiene-for-woocommerce')} />;
    }

    return (
        <div>
            {message && (
                <Notice status="success" isDismissible onDismiss={() => setMessage('')}>
                    {message}
                </Notice>
            )}

            <div className="datahyg-settings-form">
                {/* Scan Settings */}
                <div className="datahyg-card">
                    <h2>{__('Scan Settings', 'data-hygiene-for-woocommerce')}</h2>

                    <TextControl
                        label={__('Batch Size', 'data-hygiene-for-woocommerce')}
                        help={__('Number of orders to process per batch. Lower values for shared hosting.', 'data-hygiene-for-woocommerce')}
                        type="number"
                        value={settings.datahyg_scan_batch_size || 50}
                        onChange={(val) => update('datahyg_scan_batch_size', val)}
                        __nextHasNoMarginBottom
                    />

                    <TextControl
                        label={__('Duplicate Threshold (seconds)', 'data-hygiene-for-woocommerce')}
                        help={__('Orders within this time window with same amount and customer are flagged as duplicates.', 'data-hygiene-for-woocommerce')}
                        type="number"
                        value={settings.datahyg_duplicate_threshold || 60}
                        onChange={(val) => update('datahyg_duplicate_threshold', val)}
                        __nextHasNoMarginBottom
                    />

                    <TextControl
                        label={__('Store Start Date', 'data-hygiene-for-woocommerce')}
                        help={__('Orders before this date will be flagged. Leave empty for auto-detection.', 'data-hygiene-for-woocommerce')}
                        type="date"
                        value={settings.datahyg_store_start_date || ''}
                        onChange={(val) => update('datahyg_store_start_date', val)}
                        __nextHasNoMarginBottom
                    />

                    <TextControl
                        label={__('Additional Test Emails', 'data-hygiene-for-woocommerce')}
                        help={__('Comma-separated. Orders from these emails will be flagged as test orders.', 'data-hygiene-for-woocommerce')}
                        value={settings.datahyg_test_order_emails || ''}
                        onChange={(val) => update('datahyg_test_order_emails', val)}
                        __nextHasNoMarginBottom
                    />
                </div>

                {/* Auto Scan Settings */}
                <div className="datahyg-card">
                    <h2>{__('Automatic Scanning', 'data-hygiene-for-woocommerce')}</h2>

                    <ToggleControl
                        label={__('Enable Weekly Auto-Scan', 'data-hygiene-for-woocommerce')}
                        checked={settings.datahyg_auto_scan_enabled === 'yes'}
                        onChange={(val) => update('datahyg_auto_scan_enabled', val ? 'yes' : 'no')}
                        __nextHasNoMarginBottom
                    />

                    {settings.datahyg_auto_scan_enabled === 'yes' && (
                        <SelectControl
                            label={__('Scan Day', 'data-hygiene-for-woocommerce')}
                            value={settings.datahyg_auto_scan_day || 'monday'}
                            options={[
                                { label: __('Monday', 'data-hygiene-for-woocommerce'), value: 'monday' },
                                { label: __('Tuesday', 'data-hygiene-for-woocommerce'), value: 'tuesday' },
                                { label: __('Wednesday', 'data-hygiene-for-woocommerce'), value: 'wednesday' },
                                { label: __('Thursday', 'data-hygiene-for-woocommerce'), value: 'thursday' },
                                { label: __('Friday', 'data-hygiene-for-woocommerce'), value: 'friday' },
                                { label: __('Saturday', 'data-hygiene-for-woocommerce'), value: 'saturday' },
                                { label: __('Sunday', 'data-hygiene-for-woocommerce'), value: 'sunday' },
                            ]}
                            onChange={(val) => update('datahyg_auto_scan_day', val)}
                            __nextHasNoMarginBottom
                        />
                    )}
                </div>

                {/* Email Alerts */}
                <div className="datahyg-card">
                    <h2>{__('Email Alerts', 'data-hygiene-for-woocommerce')}</h2>

                    <ToggleControl
                        label={__('Send Alert When Score Drops', 'data-hygiene-for-woocommerce')}
                        checked={settings.datahyg_email_alerts === 'yes'}
                        onChange={(val) => update('datahyg_email_alerts', val ? 'yes' : 'no')}
                        __nextHasNoMarginBottom
                    />

                    {settings.datahyg_email_alerts === 'yes' && (
                        <TextControl
                            label={__('Alert Email', 'data-hygiene-for-woocommerce')}
                            type="email"
                            value={settings.datahyg_alert_email || ''}
                            onChange={(val) => update('datahyg_alert_email', val)}
                            __nextHasNoMarginBottom
                        />
                    )}
                </div>

                {/* Gateway Settings */}
                <div className="datahyg-card">
                    <h2>{__('Payment Gateway Reconciliation', 'data-hygiene-for-woocommerce')}</h2>
                    <p style={{ color: '#646970', marginTop: 0 }}>
                        {__('Gateway credentials are read from WooCommerce payment gateway settings automatically.', 'data-hygiene-for-woocommerce')}
                    </p>

                    <ToggleControl
                        label={__('Enable Stripe Reconciliation', 'data-hygiene-for-woocommerce')}
                        checked={settings.datahyg_stripe_enabled === 'yes'}
                        onChange={(val) => update('datahyg_stripe_enabled', val ? 'yes' : 'no')}
                        __nextHasNoMarginBottom
                    />

                    <ToggleControl
                        label={__('Enable PayPal Reconciliation', 'data-hygiene-for-woocommerce')}
                        checked={settings.datahyg_paypal_enabled === 'yes'}
                        onChange={(val) => update('datahyg_paypal_enabled', val ? 'yes' : 'no')}
                        __nextHasNoMarginBottom
                    />
                </div>

                <Button variant="primary" isBusy={saving} onClick={saveSettings}>
                    {__('Save Settings', 'data-hygiene-for-woocommerce')}
                </Button>
            </div>
        </div>
    );
}
