import { useState } from '@wordpress/element';
import { TabPanel } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import Dashboard from './pages/Dashboard';
import ScanResults from './pages/ScanResults';
import Quarantine from './pages/Quarantine';
import Reconciliation from './pages/Reconciliation';
import Settings from './pages/Settings';

const tabs = [
    { name: 'dashboard', title: __('Dashboard', 'data-hygiene-for-woocommerce'), Component: Dashboard },
    { name: 'scan', title: __('Scan Results', 'data-hygiene-for-woocommerce'), Component: ScanResults },
    { name: 'quarantine', title: __('Quarantine', 'data-hygiene-for-woocommerce'), Component: Quarantine },
    { name: 'reconciliation', title: __('Reconciliation', 'data-hygiene-for-woocommerce'), Component: Reconciliation },
    { name: 'settings', title: __('Settings', 'data-hygiene-for-woocommerce'), Component: Settings },
];

export default function App() {
    const [refreshKey, setRefreshKey] = useState(0);

    const refresh = () => setRefreshKey((k) => k + 1);

    return (
        <div className="datahyg-app">
            <h1 className="datahyg-title">
                {__('WooCommerce Data Hygiene', 'data-hygiene-for-woocommerce')}
            </h1>
            <TabPanel
                className="datahyg-tabs"
                tabs={tabs}
                initialTabName="dashboard"
            >
                {(tab) => {
                    const { Component } = tab;
                    return <Component key={refreshKey} onRefresh={refresh} />;
                }}
            </TabPanel>
        </div>
    );
}
