# Privacy Notice — WooCommerce Data Hygiene

## English

WooCommerce Data Hygiene is a **local-only** plugin. It is designed around the principle that your order, customer and payment data must never leave your WordPress server.

**Data the plugin reads (local only):**
- WooCommerce orders, order items, order meta (via `wc_get_order()` and the standard WooCommerce data store).
- WooCommerce Analytics lookup tables (`wc_order_stats`, `wc_order_product_lookup`, `wc_order_coupon_lookup`, `wc_order_tax_lookup`).
- WordPress users with the `manage_woocommerce` capability (for the audit log).

**Data the plugin writes (local only):**
- Custom tables prefixed with `{wp_prefix}wc_data_*` and `{wp_prefix}wc_reconciliation_cache`.
- WordPress options prefixed with `wdh_`.

**Outbound network calls:**
- **None by default.**
- If you enable Stripe or PayPal reconciliation, the plugin calls the respective gateway API directly from your server, authenticating with the credentials you provide in settings. No third party sits in the middle.

**Cookies / tracking:** none.

**Telemetry / analytics:** none.

**Data export / deletion:**
- Export: query the `wc_data_*` tables directly via WP-CLI or phpMyAdmin.
- Deletion: set the option `wdh_delete_data_on_uninstall` to `yes` and deactivate + delete the plugin to drop all plugin tables and options.

**GDPR / personal data:** the audit log records the WordPress user id, login and IP of the person who performed each destructive action. This is required for accountability. If you must purge it, truncate `{wp_prefix}wc_data_audit_log`.

---

## العربية

إضافة WooCommerce Data Hygiene إضافة **محلّية بالكامل**. مبدأها إن بيانات الأوردرات والعملاء والمدفوعات **مفروض ما تخرجش من سيرفر ووردبريس عندك أبدًا**.

**البيانات اللي الإضافة بتقرأها (محلّيًا فقط):**
- أوردرات ووكومرس والميتاداتا الخاصة بيها (عن طريق `wc_get_order()` وداتا ستور ووكومرس الرسمي).
- جداول التحليلات: `wc_order_stats`, `wc_order_product_lookup`, `wc_order_coupon_lookup`, `wc_order_tax_lookup`.
- مستخدمي ووردبريس اللي عندهم صلاحية `manage_woocommerce` (لسجل التدقيق).

**البيانات اللي الإضافة بتكتبها (محلّيًا فقط):**
- جداول مخصّصة ببادئة `{wp_prefix}wc_data_*` و `{wp_prefix}wc_reconciliation_cache`.
- خيارات ووردبريس ببادئة `wdh_`.

**الاتصالات الخارجية:**
- **مفيش أي اتصال خارجي بشكل افتراضي.**
- لو فعّلت مطابقة Stripe أو PayPal، الإضافة بتنادي API الـ gateway مباشرة من سيرفرك بالـ credentials اللي إنت دخّلتها في الإعدادات. مفيش طرف تالت بيتوسّط.

**كوكيز / تتبّع:** ولا واحد.

**Telemetry / تحليلات:** ولا واحدة.

**تصدير / حذف البيانات:**
- التصدير: استعلام مباشر على جداول `wc_data_*` عبر WP-CLI أو phpMyAdmin.
- الحذف: فعّل الخيار `wdh_delete_data_on_uninstall` بقيمة `yes` ثم احذف الإضافة، هتتشال كل الجداول والخيارات.

**GDPR / بيانات شخصية:** سجل التدقيق بيخزّن user id و login و IP لكل عملية تدميرية للمساءلة. لو محتاج تمسحه، اعمل `TRUNCATE {wp_prefix}wc_data_audit_log`.
