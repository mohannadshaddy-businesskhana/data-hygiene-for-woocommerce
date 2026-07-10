=== Data Hygiene for WooCommerce ===
Contributors: businesskhana
Tags: woocommerce, analytics, data-cleaning, reconciliation, quarantine
Requires at least: 5.8
Tested up to: 7.0
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Detect, quarantine and clean WooCommerce Analytics data corruption with dry-run mode, full undo log and payment gateway reconciliation.

== Description ==

**Data Hygiene for WooCommerce** detects, quarantines, and fixes data corruption in WooCommerce Analytics — safely, with a dry-run preview and a full undo log for every destructive action.

If your WooCommerce reports show inflated revenue, duplicate orders, or numbers that just don't add up, this plugin finds out why and helps you fix it without ever losing data.

= The Problem =

WooCommerce stores commonly suffer from analytics data corruption:

* **Orphan orders** in analytics tables with no matching order record
* **Test orders** from admin emails polluting revenue reports
* **Duplicate orders** from double-clicks or payment retries
* **Status mismatches** — completed orders with no payment method
* **Invalid dates** — future dates or impossible timestamps
* **Amount anomalies** — negative totals or zero-amount completed orders

= What This Plugin Does =

1. **Smart Scanning** — 6 specialized scan modules analyze your orders for common data issues
2. **Dry-Run Mode** — preview every change before anything is touched
3. **Quarantine System** — suspicious data is moved to quarantine, not deleted. Full undo support.
4. **Audit Log** — every destructive operation is recorded with user, time, and reason
5. **Data Confidence Score** — a 0-100% score showing how trustworthy your analytics data is
6. **Payment Reconciliation** — match WooCommerce orders against Stripe / PayPal records
7. **Weekly Auto-Scan** — automatic weekly scans with email alerts when your score drops
8. **WP Dashboard Widget** — see your confidence score at a glance

= Key Differentiators =

* **Analytics-specific** — understands WooCommerce Analytics data structure
* **Safety-first** — dry-run + confirmation modal + audit log on every destructive op
* **Quarantine, not delete** — move data to review, never lose it
* **Full undo** — restore any quarantined item to its original state
* **Gateway reconciliation** — verify WooCommerce totals match Stripe / PayPal
* **HPOS-ready** — works with the new high-performance order storage

= Supported Payment Gateways =

* Stripe (WooCommerce Stripe Gateway)
* PayPal (WooCommerce PayPal Payments, PayPal Standard)

= Privacy =

All scanning and cleaning runs locally on your own server. No order, customer, or payment data ever leaves your WordPress install. Reconciliation calls are sent directly from your server to Stripe / PayPal using credentials you provide. See PRIVACY.md in the plugin folder for full details.

== External services ==

This plugin includes an **optional** Payment Reconciliation feature. When — and only when — you enable it and enter your own payment gateway API credentials, the plugin contacts the third-party services listed below to match your WooCommerce order totals against the payment processor's own records. If you do not enable reconciliation, the plugin makes no external calls and operates entirely on your own server.

= Stripe =

Used to retrieve your own Stripe charge and balance-transaction records so they can be reconciled against your WooCommerce orders.

* What is sent: your Stripe secret API key (as an Authorization header) and request parameters for the date range and pagination. No customer personal data is transmitted.
* When: only when you run a reconciliation that includes Stripe, or during the optional weekly auto-scan if Stripe reconciliation is enabled.
* Endpoint: https://api.stripe.com
* Terms of Service: https://stripe.com/legal/ssa
* Privacy Policy: https://stripe.com/privacy

= PayPal =

Used to retrieve your own PayPal transaction records so they can be reconciled against your WooCommerce orders.

* What is sent: your PayPal client ID and secret (to obtain a short-lived OAuth token) and request parameters for the date range. No customer personal data is transmitted.
* When: only when you run a reconciliation that includes PayPal, or during the optional weekly auto-scan if PayPal reconciliation is enabled.
* Endpoints: https://api-m.paypal.com (live) and https://api-m.sandbox.paypal.com (sandbox/testing)
* Terms of Service: https://www.paypal.com/us/legalhub/useragreement-full
* Privacy Policy: https://www.paypal.com/us/legalhub/privacy-full

== Installation ==

1. Upload the plugin files to `/wp-content/plugins/data-hygiene-for-woocommerce/`, or install the plugin through the WordPress plugins screen directly.
2. Activate the plugin through the 'Plugins' screen in WordPress.
3. Make sure WooCommerce is installed and active.
4. Go to WooCommerce → Data Hygiene.
5. Run a **Dry-Run scan** first to preview issues, then quarantine.

== Frequently Asked Questions ==

= Will this delete my orders? =

No. The plugin uses a quarantine system. Suspicious orders are moved to a quarantine table with a full backup of the original data. You can restore any quarantined item at any time. Every destructive operation is recorded in an audit log.

= What is dry-run mode? =

Dry-run runs the full scan and shows you exactly what would be quarantined — without touching a single row. You only commit when you click confirm.

= Does it work with HPOS (High-Performance Order Storage)? =

Yes. The plugin fully supports both the traditional posts-based storage and the new HPOS custom order tables.

= How does the confidence score work? =

The score starts at 100% and decreases based on the number and severity of issues found. More critical issues (like future dates or negative amounts) have a higher impact than minor issues.

= Can I scan only specific issue types? =

Yes. The Quick Scan checks for orphan orders, test orders, and amount issues. The Full Scan runs all 6 modules.

= Does any data leave my server? =

No. Scanning is 100% local. Only the optional payment gateway reconciliation makes outbound calls — directly from your server to Stripe / PayPal using your own credentials. See the "External services" section above.

== Screenshots ==

1. Dashboard with confidence score gauge and scan summary
2. Scan results with issue details and dry-run preview
3. Quarantine management with restore and bulk actions
4. Confirmation modal before any destructive action
5. Audit log showing every recorded change
6. Settings page with scan configuration

== Development ==

The admin interface is built with React using the official @wordpress/scripts (webpack) build tooling. The complete, un-minified, human-readable source is included in this plugin under the `src/` directory, and the compiled output it generates lives in `build/`.

The public development repository (full source and build tooling) is available at:
https://github.com/mohannadshaddy-businesskhana/data-hygiene-for-woocommerce

To rebuild the compiled assets from source:

`npm install`
`npm run build`

The build scripts are defined in `package.json` at the plugin root. The source in `src/` is what generates `build/index.js`.

== Changelog ==

= 1.0.0 =
* Initial release
* 6 scan modules: orphan, test, duplicate, status, date, amount
* Dry-run mode with confirmation modal
* Quarantine system with full undo
* Audit log for every destructive operation
* Stripe and PayPal reconciliation
* Data confidence score algorithm
* Weekly auto-scan with email alerts
* WP Dashboard widget
* HPOS compatibility

== Upgrade Notice ==

= 1.0.0 =
First public release. Always run a dry-run scan before quarantining on a production store.
