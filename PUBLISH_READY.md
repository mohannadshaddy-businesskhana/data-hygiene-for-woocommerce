# PUBLISH_READY — WooCommerce Data Hygiene

> Single-page checklist + workflow to submit this plugin to WordPress.org.
> All paths are relative to the plugin root: `D:/Projects/Side Income Projects/data-hygiene-for-woocommerce`.

---

## 1. Pass / fail per WP.org guideline

| # | Guideline                                                  | Status | Notes                                                             |
|---|------------------------------------------------------------|--------|-------------------------------------------------------------------|
| 1 | GPL-compatible license                                     | PASS   | GPL-2.0-or-later. `LICENSE` shipped. Header + readme match.       |
| 2 | Plugin header complete                                     | PASS   | Name, URI, Description, Version, Author, License, Text Domain, Requires at least, Tested up to, Requires PHP, WC requires/tested. |
| 3 | `readme.txt` follows WP.org format                         | PASS   | Stable tag, Contributors, Tags (5), Requires at least, Tested up to, Requires PHP, License + URI, short desc ≤ 150, sections. |
| 4 | Unique prefix on functions / classes / options / tables    | PASS   | `WDH\` namespace, `wdh_` options, `wc_data_*` tables.             |
| 5 | No `eval()` / `base64_decode` of user input                | PASS   | None used.                                                        |
| 6 | All input sanitized                                        | PASS   | `sanitize_*`, `absint`, `rest_sanitize_boolean`, REST `args`.     |
| 7 | All output escaped                                         | PASS   | `esc_html__`, `esc_attr`, `esc_url`, `wp_json_encode`.            |
| 8 | All SQL prepared                                           | PASS   | `$wpdb->prepare` everywhere user input touches a query. Table names are interpolated from `$wpdb->prefix` only. |
| 9 | Nonces + capability on every write endpoint                | PASS   | `check_write()` enforces `manage_woocommerce` + `X-WP-Nonce`.     |
| 10| Data-mutation safety                                       | PASS   | Dry-run mode, confirmation modal (UI), full audit log table.      |
| 11| Text domain matches slug + `.pot` shipped                  | PASS   | `data-hygiene-for-woocommerce` + `languages/data-hygiene-for-woocommerce.pot`.            |
| 12| WooCommerce dependency check                               | PASS   | `wdh_check_woocommerce()` runs on `plugins_loaded`.               |
| 13| HPOS declared                                              | PASS   | `before_woocommerce_init` declares `custom_order_tables` compat.  |
| 14| Uninstall handler                                          | PASS   | `uninstall.php` is opt-in via `wdh_delete_data_on_uninstall`.     |
| 15| No external script / font loads                            | PASS   | Only `build/index.js` + `build/style-index.css` from local plugin URL. |
| 16| No telemetry / external API calls without consent          | PASS   | Stripe / PayPal only when user enables and provides credentials.  |
| 17| Privacy notice                                             | PASS   | `PRIVACY.md` (EN + AR) + section in `readme.txt` description.     |
| 18| Build artifacts committed to trunk                         | TODO   | `build/` exists from prior `npm run build`. Re-run before zipping.|

---

## 2. Expected Plugin Check (PCP) results

Running `Plugin Check` from the WP.org plugin should report:

- **Errors:** 0 expected.
- **Warnings (acceptable):**
  - `WordPress.WP.I18n.MissingTranslatorsComment` — only if any `sprintf` strings with placeholders lack a `translators:` comment. Audited file `class-admin.php` has the only `printf` and uses `%s/%d` with `esc_html__`. Add `// translators:` if PCP flags it.
  - `WordPress.DB.DirectDatabaseQuery.DirectQuery` — expected and unavoidable for a data-hygiene tool. Suppress per-line with a documented `phpcs:ignore` only when intentional.
  - `WordPress.DB.SlowDBQuery.slow_db_query_meta_query` — none expected; we don't use meta queries.

- **Info:** missing changelog entry beyond 1.0.0 (expected for first release).

---

## 3. SVN workflow (WP.org)

Once the submission is approved you will receive:
- An SVN URL: `https://plugins.svn.wordpress.org/data-hygiene-for-woocommerce/`
- WP.org commit credentials (your wordpress.org account + password).

### Layout
```
data-hygiene-for-woocommerce/        ← SVN root
├── trunk/               ← latest dev version (plugin files)
├── tags/
│   └── 1.0.0/           ← snapshot of trunk at release time
└── assets/              ← banner / icon / screenshots (NOT shipped in ZIP)
```

### One-time checkout
```bash
svn co https://plugins.svn.wordpress.org/data-hygiene-for-woocommerce wp-svn-data-hygiene-for-woocommerce
```

### Release flow (every version)
```bash
# 1. From plugin root, run a clean production build
npm ci
npm run build

# 2. Sync source into SVN trunk (excludes node_modules, src, .git, dev files)
rsync -av --delete \
  --exclude node_modules --exclude src --exclude .git --exclude .github \
  --exclude package-lock.json --exclude '.editorconfig' --exclude 'STORE_LISTING.md' \
  --exclude 'PUBLISH_READY.md' --exclude 'PRIVACY.md' --exclude 'README.md' \
  --exclude 'assets' \
  ./ ../wp-svn-data-hygiene-for-woocommerce/trunk/

# 3. Sync banner / icon / screenshots into SVN assets/
rsync -av ./assets/ ../wp-svn-data-hygiene-for-woocommerce/assets/

# 4. Tag the release
cd ../wp-svn-data-hygiene-for-woocommerce
svn cp trunk tags/1.0.0

# 5. Add new files, commit
svn add --force trunk assets tags
svn ci -m "Release 1.0.0"
```

### Files that MUST ship in trunk
- `data-hygiene-for-woocommerce.php`
- `uninstall.php`
- `LICENSE`
- `readme.txt`
- `includes/` (all PHP)
- `build/` (`index.js`, `index.asset.php`, `style-index.css`, `style-index-rtl.css`)
- `languages/data-hygiene-for-woocommerce.pot`

### Files that must NOT ship in trunk
- `node_modules/`, `src/`, `package.json`, `package-lock.json`
- `STORE_LISTING.md`, `PUBLISH_READY.md`, `README.md`, `PRIVACY.md`
- `.git/`, `.github/`, editor configs
- Anything under `assets/` (lives in SVN `assets/`, not trunk)

---

## 4. Submission flow

1. Make sure `npm run build` is clean and `build/` is up to date.
2. Make sure version numbers match across:
   - `data-hygiene-for-woocommerce.php` header `Version:`
   - `WDH_VERSION` constant
   - `readme.txt` `Stable tag:`
   - `readme.txt` `== Changelog ==` entry
3. Zip the plugin (see §6).
4. Upload the zip at https://wordpress.org/plugins/developers/add/.
5. Wait for the WP.org plugins team review (typically 1–14 days).
6. Once approved, receive SVN credentials, then follow §3 to push trunk + tag 1.0.0 + assets.

---

## 5. Review checklist (do this before pressing submit)

- [ ] `npm run build` succeeds without warnings
- [ ] All strings use `__()` / `_e()` / `_x()` with text domain `data-hygiene-for-woocommerce`
- [ ] `.pot` regenerated with WP-CLI: `wp i18n make-pot . languages/data-hygiene-for-woocommerce.pot --domain=data-hygiene-for-woocommerce`
- [ ] Activated on a clean WP install with WooCommerce only — no PHP notices
- [ ] Activated with WooCommerce **deactivated** — admin notice shows, no fatals
- [ ] Activated with HPOS enabled — no compat warning in WC → Status
- [ ] REST endpoints return 401 without nonce / capability
- [ ] Dry-run on bulk-quarantine returns `preview` and does NOT write to DB
- [ ] Confirm modal in UI gates every destructive action
- [ ] Audit log table fills up correctly (check `{wp_prefix}wc_data_audit_log`)
- [ ] Uninstall with `wdh_delete_data_on_uninstall = 'yes'` drops all 4 tables
- [ ] No console errors in browser admin
- [ ] No PHP warnings in `WP_DEBUG_LOG` during a full scan
- [ ] Plugin Check (PCP) shows zero Errors

---

## 6. ZIP packaging command

From the plugin root:

```bash
# Clean dev artifacts first
rm -rf node_modules build
npm ci
npm run build

# Build the release ZIP. Filename must match the slug.
cd ..
zip -r data-hygiene-for-woocommerce.zip data-hygiene-for-woocommerce \
  -x 'data-hygiene-for-woocommerce/node_modules/*' \
  -x 'data-hygiene-for-woocommerce/src/*' \
  -x 'data-hygiene-for-woocommerce/package.json' \
  -x 'data-hygiene-for-woocommerce/package-lock.json' \
  -x 'data-hygiene-for-woocommerce/.git/*' \
  -x 'data-hygiene-for-woocommerce/.github/*' \
  -x 'data-hygiene-for-woocommerce/.editorconfig' \
  -x 'data-hygiene-for-woocommerce/STORE_LISTING.md' \
  -x 'data-hygiene-for-woocommerce/PUBLISH_READY.md' \
  -x 'data-hygiene-for-woocommerce/PRIVACY.md' \
  -x 'data-hygiene-for-woocommerce/README.md' \
  -x 'data-hygiene-for-woocommerce/assets/*'
```

Or on PowerShell:

```powershell
Compress-Archive -Path .\data-hygiene-for-woocommerce -DestinationPath .\data-hygiene-for-woocommerce.zip -Force
# then manually remove node_modules/src from the archive, or use 7zip with exclusions
```

---

## 7. Manual actions for Mohannad

These cannot be automated:

1. **Create a WordPress.org account** at https://login.wordpress.org/register if you don't already have one.
2. **Update the `Contributors:` line** in `readme.txt` from `businesskhana` to your real WP.org username(s).
3. **Update Author URI / Plugin URI** in `data-hygiene-for-woocommerce.php` to your real domain (currently `businesskhana.com` / GitHub).
4. **Produce the visual assets** per `STORE_LISTING.md` (banner 1544×500 + 772×250, icon 256×256 + 128×128, 4–6 screenshots ≤ 1280px wide).
5. **Re-run `npm run build`** locally — the build was not re-executed in this autonomous pass (Bash/PowerShell were denied in the sandbox).
6. **Regenerate the .pot** with WP-CLI to pick up new translatable strings added in this pass:
   ```bash
   wp i18n make-pot . languages/data-hygiene-for-woocommerce.pot --domain=data-hygiene-for-woocommerce
   ```
7. **Submit the ZIP** at https://wordpress.org/plugins/developers/add/.
8. **After approval**, you will receive an SVN URL + credentials. Follow §3 to push trunk + tag + assets.
9. **Confirmation modal**: the UI components in `src/pages/*.jsx` should already call `dry_run=true` first, then show a `@wordpress/components` `Modal` with the preview, and only on confirm send the real call. Verify in `Quarantine.jsx` / `ScanResults.jsx` before release.
