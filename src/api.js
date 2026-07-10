import apiFetch from '@wordpress/api-fetch';

const BASE = 'wdh/v1';

export const api = {
    // Dashboard
    getDashboard: () => apiFetch({ path: `${BASE}/dashboard` }),

    // Scans
    runScan: (type = 'full') =>
        apiFetch({ path: `${BASE}/scan`, method: 'POST', data: { type } }),
    getScan: (id) => apiFetch({ path: `${BASE}/scan/${id}` }),
    listScans: () => apiFetch({ path: `${BASE}/scans` }),

    // Quarantine
    listQuarantine: (params = {}) =>
        apiFetch({ path: `${BASE}/quarantine`, data: params }),
    restoreItem: (id) =>
        apiFetch({ path: `${BASE}/quarantine/${id}/restore`, method: 'POST' }),
    deleteItem: (id) =>
        apiFetch({ path: `${BASE}/quarantine/${id}/delete`, method: 'DELETE' }),
    bulkQuarantine: (scanId, issueTypes = []) =>
        apiFetch({
            path: `${BASE}/quarantine/bulk`,
            method: 'POST',
            data: { scan_id: scanId, issue_types: issueTypes },
        }),
    bulkRestore: (ids) =>
        apiFetch({
            path: `${BASE}/quarantine/bulk-restore`,
            method: 'POST',
            data: { ids },
        }),

    // Reconciliation
    runReconciliation: (gateway, from = '', to = '') =>
        apiFetch({
            path: `${BASE}/reconcile`,
            method: 'POST',
            data: { gateway, from, to },
        }),
    getReconciliation: (params = {}) =>
        apiFetch({ path: `${BASE}/reconciliation`, data: params }),

    // Settings
    getSettings: () => apiFetch({ path: `${BASE}/settings` }),
    updateSettings: (settings) =>
        apiFetch({ path: `${BASE}/settings`, method: 'POST', data: settings }),
};
