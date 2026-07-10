# WooCommerce Data Hygiene

[![License: GPL v2+](https://img.shields.io/badge/License-GPL%20v2%2B-blue.svg)](https://www.gnu.org/licenses/old-licenses/gpl-2.0.html)
[![Requires PHP](https://img.shields.io/badge/PHP-7.4%2B-777bb4.svg)]()
[![Requires WP](https://img.shields.io/badge/WordPress-5.8%2B-21759b.svg)]()
[![Requires WC](https://img.shields.io/badge/WooCommerce-5.0%2B-96588a.svg)]()

Detect, quarantine and clean WooCommerce Analytics data corruption — safely, with **dry-run preview**, **confirmation modal** and a **full undo audit log** for every destructive action.

## Why

WooCommerce stores commonly suffer from analytics data corruption that inflates revenue numbers and breaks reports:

- Orphan orders in analytics tables with no matching order record
- Test orders polluting revenue reports
- Duplicate orders from double-clicks or payment retries
- Status mismatches — completed orders with no payment method
- Invalid dates and amount anomalies

## Features

- 6 specialized scan modules (orphan, test, duplicate, status, date, amount)
- **Dry-run mode** with confirmation modal — preview every change before it lands
- **Quarantine, never delete** — original data is backed up to JSON
- **Full audit log** — every destructive op recorded with user, IP, time, payload
- Data confidence score (0–100%)
- Stripe / PayPal reconciliation
- Weekly auto-scan + email alerts
- WP dashboard widget
- HPOS-ready

## Install (dev)

```bash
npm install
npm run build
```

Then symlink or copy `data-hygiene-for-woocommerce/` into `wp-content/plugins/` and activate via WooCommerce → Data Hygiene.

## Stack

- PHP 7.4+ / WordPress 5.8+ / WooCommerce 5.0+
- React + `@wordpress/components` + `@wordpress/api-fetch` (`wp-scripts` build)
- WP REST API namespace `wdh/v1`

## Safety model

| Operation         | Capability         | Nonce (`X-WP-Nonce`) | Dry-run | Audit log |
|-------------------|--------------------|----------------------|---------|-----------|
| Run scan          | `manage_woocommerce` | required           | n/a     | yes       |
| Quarantine bulk   | `manage_woocommerce` | required           | yes     | yes       |
| Restore item      | `manage_woocommerce` | required           | yes     | yes       |
| Delete item       | `manage_woocommerce` | required           | yes     | yes       |
| Update settings   | `manage_woocommerce` | required           | n/a     | yes       |

## License

GPL-2.0-or-later. See [LICENSE](LICENSE).
