import { __ } from '@wordpress/i18n';

function getColor(score) {
    if (score >= 80) return '#00a32a';
    if (score >= 50) return '#dba617';
    return '#d63638';
}

function getLabel(score) {
    if (score >= 90) return __('Excellent', 'data-hygiene-for-woocommerce');
    if (score >= 75) return __('Good', 'data-hygiene-for-woocommerce');
    if (score >= 50) return __('Fair', 'data-hygiene-for-woocommerce');
    if (score >= 25) return __('Poor', 'data-hygiene-for-woocommerce');
    return __('Critical', 'data-hygiene-for-woocommerce');
}

export default function ConfidenceGauge({ score }) {
    const color = getColor(score);
    const label = getLabel(score);

    // SVG gauge arc
    const radius = 80;
    const circumference = Math.PI * radius;
    const progress = (score / 100) * circumference;

    return (
        <div className="datahyg-gauge">
            <svg width="200" height="120" viewBox="0 0 200 120">
                {/* Background arc */}
                <path
                    d="M 20 100 A 80 80 0 0 1 180 100"
                    fill="none"
                    stroke="#e0e0e0"
                    strokeWidth="12"
                    strokeLinecap="round"
                />
                {/* Progress arc */}
                <path
                    d="M 20 100 A 80 80 0 0 1 180 100"
                    fill="none"
                    stroke={color}
                    strokeWidth="12"
                    strokeLinecap="round"
                    strokeDasharray={`${progress} ${circumference}`}
                />
            </svg>
            <div className="datahyg-gauge-score" style={{ color, marginTop: '-30px' }}>
                {Math.round(score)}%
            </div>
            <div className="datahyg-gauge-label">{label}</div>
        </div>
    );
}
