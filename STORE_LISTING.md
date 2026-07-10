# Store Listing — WooCommerce Data Hygiene

Use this document as the source of truth for the WordPress.org listing, marketing site and PR notes.

---

## English (EN)

### Plugin name
**WooCommerce Data Hygiene**

### Tagline
Clean WooCommerce analytics safely — with dry-run preview and a full undo log.

### Short description (≤ 150 chars)
Detect and clean WooCommerce analytics corruption — orphan, test, duplicate orders. Dry-run preview, quarantine, full undo log. (148)

### Detailed description
WooCommerce Data Hygiene is the safest way to fix corrupted WooCommerce Analytics data.

If your reports show inflated revenue, duplicate orders or numbers that just don't add up, this plugin finds out why and helps you fix it without ever losing data.

**What it does**
- Six specialized scan modules detect orphan orders, test orders, duplicates, status mismatches, invalid dates and amount anomalies.
- **Dry-run mode** runs the full scan and shows exactly what would change — without touching a single row.
- A **confirmation modal** is shown before any destructive action.
- Suspicious data goes to **quarantine**, not the trash. Originals are backed up to JSON and can be restored with one click.
- A **full audit log** records every destructive op with user, IP, timestamp and payload.
- **Confidence score** (0–100%) tells you at a glance how trustworthy your analytics are.
- **Payment reconciliation** matches WooCommerce totals against Stripe / PayPal.
- **Weekly auto-scan** with email alerts when your score drops.
- **HPOS-ready**.

**Built for store owners who refuse to lose data**
Every destructive operation has a dry-run preview, a confirmation step and an audit trail. You can always undo.

### Tags (max 5)
`woocommerce, analytics, data-cleaning, reconciliation, quarantine`

---

## Arabic (AR)

### اسم الإضافة
**WooCommerce Data Hygiene — تنظيف بيانات ووكومرس**

### السطر التسويقي
نظّف بيانات تحليلات ووكومرس بأمان — معاينة قبل التنفيذ وسجل تراجع كامل.

### وصف قصير (≤ 150 حرف)
اكتشف وعالج تلف بيانات تحليلات ووكومرس — أوردرات يتيمة وتجريبية ومكرّرة. معاينة Dry-run، حجر صحي، تراجع كامل. (110)

### وصف تفصيلي
WooCommerce Data Hygiene هي الإضافة الأأمن لإصلاح تلف بيانات تحليلات ووكومرس.

لو تقاريرك بتظهر أرباح مضخّمة أو أوردرات مكرّرة أو أرقام مش منطقية، الإضافة دي بتكتشف السبب وبتساعدك تصلحه من غير ما تفقد أي بيانات.

**إيه اللي بتعمله؟**
- 6 موديولات فحص متخصّصة: orphan / test / duplicate / status / date / amount.
- **وضع Dry-run** بيشغّل الفحص الكامل ويوريك بالظبط هيتغيّر إيه — من غير ما يلمس سطر واحد.
- **مودال تأكيد** قبل أي عملية تدميرية.
- البيانات المشبوهة تروح **حجر صحي** مش سلة المحذوفات. النسخة الأصلية بتتحفظ JSON وتقدر ترجّعها بضغطة زرار.
- **سجل تدقيق كامل** بيسجّل كل عملية: المستخدم، الـ IP، الوقت، والـ payload.
- **مؤشر ثقة** من 0 لـ 100% بيقولك على طول قد إيه بياناتك موثوقة.
- **مطابقة المدفوعات** بين ووكومرس وStripe / PayPal.
- **فحص أسبوعي تلقائي** + تنبيهات إيميل لما المؤشر ينخفض.
- متوافق مع **HPOS**.

**معمول لأصحاب المتاجر اللي مش هيقبلوا يفقدوا داتا**
كل عملية تدميرية ليها معاينة + تأكيد + سجل. تقدر تتراجع في أي وقت.

### الكلمات المفتاحية (5 كحد أقصى)
`woocommerce, analytics, data-cleaning, reconciliation, quarantine`

---

## Visual assets specifications

### Banner
- **Large**: `assets/banner-1544x500.png` — 1544 × 500 px, PNG or JPG, ≤ 1 MB.
- **Small**: `assets/banner-772x250.png` — 772 × 250 px, PNG or JPG, ≤ 500 KB.
- Suggested copy (EN): *"Clean WooCommerce analytics. Safely."*
- Suggested copy (AR): *"نظّف تحليلات ووكومرس. بأمان."*

### Icon
- **Retina**: `assets/icon-256x256.png` — 256 × 256 px PNG, transparent background.
- **Standard**: `assets/icon-128x128.png` — 128 × 128 px PNG, transparent background.
- Concept: a magnifying glass over a small bar chart, with a green check mark badge.

### Screenshots (PNG, RGB, ≤ 1280 px wide, < 1 MB each)
1. `assets/screenshot-1.png` — **Dashboard** with confidence score gauge + last scan summary.
2. `assets/screenshot-2.png` — **Scan results** showing issue breakdown by module.
3. `assets/screenshot-3.png` — **Dry-run preview** of items about to be quarantined.
4. `assets/screenshot-4.png` — **Confirmation modal** before destructive commit.
5. `assets/screenshot-5.png` — **Quarantine table** with restore + bulk actions.
6. `assets/screenshot-6.png` — **Audit log** showing user, action, time, dry-run flag.

Screenshot filenames must match the order they appear in `readme.txt`.
