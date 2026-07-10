import { __ } from '@wordpress/i18n';

export default function ScanProgress({ message }) {
    return (
        <div className="datahyg-progress">
            <div className="datahyg-progress-spinner" />
            <p>{message || __('Scanning...', 'data-hygiene-for-woocommerce')}</p>
        </div>
    );
}
