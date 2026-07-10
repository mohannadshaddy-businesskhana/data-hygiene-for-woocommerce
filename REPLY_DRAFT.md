# WP.org reply — Data Hygiene review (DRAFT — awaiting Mohannad's approval; red gate, do not send)

**To:** reply on the 8 Jun review thread from `plugins@wordpress.org`
**From:** `businesskhana` account
**Send only after:** CI Plugin Check = 0 errors + updated ZIP uploaded.

---

Hi, and thank you for the review.

We've addressed the reported points and uploaded an updated version. For context that may help your review: all scanning and cleaning runs locally on the site owner's server. The only outbound calls come from the optional payment-reconciliation feature, which contacts Stripe/PayPal using the site owner's own API credentials — no customer personal data is sent. Both services are now documented in the readme's "External services" section with their terms and privacy links. The un-minified React source is included under `src/` with build steps documented, and the unnecessary `load_plugin_textdomain()` call has been removed.

Thank you for your time.
