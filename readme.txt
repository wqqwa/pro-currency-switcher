=== Pro Currency Switcher ===
Contributors: woocross
Tags: woocommerce, currency, multi-currency, exchange rate, currency switcher, price converter
Requires at least: 5.0
Tested up to: 6.7
Requires PHP: 7.4
Stable tag: 7.5.2
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

A free, lightweight WooCommerce multi-currency switcher supporting 206 currencies, manual exchange rates, currency selector, online customer service widget, cache compatibility, and WooCommerce Blocks.

== Description ==

**Pro Currency Switcher** is a free WooCommerce multi-currency plugin designed for cross-border e-commerce. It allows your customers to browse and shop in their preferred currency with minimal setup.

= Core Features =

* **206 Currencies** — Covers all major economies: Asia-Pacific, Europe, Americas, Middle East, Africa
* **Manual Exchange Rates** — Set rates manually or fetch from a free public API with one click (rates expire after 30 hours in the free version)
* **Currency Selector** — Simple dropdown selector on product pages, shop, cart, and checkout
* **Online Customer Service Widget** — Built-in floating widget supporting 11 channels: QQ, WeChat, WhatsApp, Telegram, Line, Messenger, Viber, Signal, Phone, Email, and a contact form
* **Cache Compatibility** — Works with WP Super Cache, W3 Total Cache, and CDN through Cookie + JavaScript dynamic updates
* **WooCommerce Blocks** — Full support for price conversion in block-based editor
* **WooCommerce Extensions** — Compatible with Subscriptions, Bookings, Product Bundles, and Composite Products
* **Developer Hooks** — 42 customizable hooks (28 `apply_filters` + 14 `do_action`) for third-party extensions

= Supported Currencies =

Supports 206 currencies including but not limited to:

* **Asia**: CNY, TWD, HKD, JPY, KRW, SGD, MYR, THB, IDR, PHP, VND, INR
* **Americas**: USD, CAD, MXN, BRL, ARS, CLP, COP
* **Europe**: EUR, GBP, CHF, RUB, SEK, NOK, DKK, PLN, CZK, TRY
* **Oceania & Africa**: AUD, NZD, ZAR, EGP, NGN, KES
* **Middle East**: SAR, AED, QAR, KWD, ILS

= Cache Compatible =

Works seamlessly with popular caching solutions:

* WP Super Cache
* W3 Total Cache
* CDN (Cloudflare, etc.)
* Varnish / Nginx proxy cache

= Developer Friendly =

* PSR-4 autoloading with `ProCurrencySwitcher` namespace
* REST API endpoints: `POST /pcs/v1/prices` and `GET /pcs/v1/currencies`
* Database migration framework for safe schema updates
* i18n ready with `.pot` template and Chinese translation
* 42 customizable action and filter hooks

= Upgrade to Pro =

Upgrade to [Pro Currency Switcher Pro](https://woocross.com/pricing) to unlock:

* API auto exchange rates (ExchangeRate-API, Fixer)
* GeoIP auto-detection
* Custom exchange rates and markups
* Price rounding templates (.99, .95, etc.)
* Country-based pricing rules
* Image watermark
* Order analytics dashboard
* Advanced selector styles (floating button, sidebar, top bar)
* Order bump (checkout upsell)
* Product independent pricing
* Checkout settlement currency

== Installation ==

1. Upload the plugin files to `/wp-content/plugins/pro-currency-switcher/`, or install through the WordPress plugins screen
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Go to **Currency Switcher → Settings** to configure
4. Select your base currency and enable currencies
5. Set exchange rates manually for each enabled currency

== Frequently Asked Questions ==

= How do I set exchange rates? =
Go to **Currency Switcher → Settings** and click "Manual Rate Settings". Enter the exchange rate for each currency relative to your base currency.

= Does this plugin automatically fetch exchange rates? =
The free version supports manual exchange rates only. Auto-fetch via API is available in the Pro version.

= Is it compatible with page caching? =
Yes. The plugin uses Cookie-based currency storage with JavaScript dynamic price updates, ensuring compatibility with WP Super Cache, W3 Total Cache, and CDN.

= Does it work with WooCommerce Subscriptions and Bookings? =
Yes. The free version includes basic compatibility with WooCommerce Subscriptions, Bookings, Product Bundles, and Composite Products.

= How do I add the currency selector to my theme? =
The currency selector is automatically displayed on product pages, shop, cart, and checkout. You can also use the shortcode or widget if available.

== Screenshots ==

1. Admin settings page with base currency selection and enabled currencies grid
2. Manual exchange rate management interface
3. Frontend currency selector dropdown on a product page
4. Online customer service widget with multiple channels

== Changelog ==

= 7.5.2 =
* Fix: PHP 7.4 compatibility issue causing 404 error on admin pages
* Fix: Base currency no longer counts toward the 5-currency free limit
* Fix: License admin menu always registers even if API is unreachable

= 7.5.1 =
* New: One-click rate fetching from free public API (open.er-api.com)
* New: Rate expiry system — free version rates expire after 30 hours, then revert to base currency
* New: Expiry countdown timer in admin panel
* New: Rate source indicator (API / Manual)
* Fix: Database migration for rate_source and expires_at columns
* Update: Professional version highlighted as "rates never expire"

= 7.5.0 =
* New: Free version released on WordPress.org
* New: Online customer service widget with 11 channels
* New: Cache compatibility layer (Cookie + JS + Vary header + REST API)
* New: WooCommerce Blocks compatibility
* New: PSR-4 namespace architecture
* New: 42 developer hooks
* New: Database migration framework
* New: i18n support (.pot template + Chinese translation)
* Support: 206 currencies with flag emojis
* Support: WooCommerce Subscriptions, Bookings, Product Bundles, Composite Products

== Upgrade Notice ==

= 7.5.1 =
Added one-click rate fetching and 30-hour rate expiry for free version. Upgrade to Pro for permanent rates.

= 7.5.0 =
Free version initial release. Upgrade to Pro for API auto-rates, GeoIP detection, and more advanced features.
