# Changelog
All notable changes to this project will be documented in this file.
The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.2.1] - 2026-05-03

### Security
- **Critical: Removed hardcoded HMAC secret key** — The default API signing key `pcs_hmac_secret_key_2026` has been removed. Each installation now auto-generates a unique 64-character random key on first activation, preventing license signature forgery.
- **Critical: PackageInstaller hardening** — Plugin package installation now includes 4 layers of security verification:
  - SHA256 hash verification against server-provided checksum
  - ZIP path traversal detection (rejects `../` and `\` in filenames)
  - File type whitelist (only `.php`, `.js`, `.css`, images, fonts, and language files allowed)
  - Malicious code pattern detection (scans for `eval()`, `base64_decode()`, `shell_exec()`, `system()`, etc.)
- **Critical: Order Bump server-side price calculation** — Discounted prices are no longer accepted from client-side POST data. All bump prices are now calculated server-side from database rules, preventing price manipulation attacks.
- **High: CSRF protection for variation pricing** — `save_variation_pricing()` now verifies `woocommerce_meta_nonce` and checks `edit_products` capability.
- **High: XSS prevention in inline JavaScript** — All PHP variables injected into inline JavaScript via `generate_price_update_script()` now use `wp_json_encode()` for proper escaping.
- **High: LicenseUpdater HMAC signing** — API update requests now include HMAC-SHA256 signatures (`X-PCS-Signature` header), matching the LicenseManager implementation.
- **High: REST API authentication** — The `/wp-json/pcs/v1/currencies` endpoint now requires `read` capability (user must be logged in).
- **Medium: HTTPS for GeoIP API** — Changed `http://ip-api.com` to `https://ip-api.com` to prevent man-in-the-middle attacks.
- **Medium: Admin output escaping** — All currency code and name outputs in admin settings now use `esc_html()` and `esc_attr()`.
- **Medium: Double price conversion prevention** — Added `_pcs_original_price` metadata marking to prevent price from being converted twice by multiple filters.
- **Medium: Exchange rate upper bound** — Exchange rates are now validated to be within reasonable range (`0 < rate < 100,000`).
- **Medium: Exchange rate filter audit logging** — When the `pcs_pre_update_rates` filter modifies rates, a warning is logged for security auditing.
- **JavaScript XSS fixes** — Replaced unsafe `.html()` with `.text()` in all dynamic content insertion:
  - `pro-currency-switcher.js`: Added `pcsEscapeHtml()` utility function
  - `contact-widget.js`: Message detail fields now use `.text()`
  - `frontend.js`: `showMessage()` now uses `.text()`
  - `admin.js`: `showNotice()` now uses `.text()`

### Changed
- License secret key is now auto-generated per installation using `wp_generate_password(64, true, true)`
- PackageInstaller falls back to `LicenseManager::get_api_secret()` when no explicit key is configured

---
## [1.2.0] - 2026-04-21

### Added
- **Currency Selector Styles**: 5 new selector styles
  - Dropdown menu (default)
  - Floating button
  - Sidebar panel
  - Horizontal list
  - Flag grid
- **Display Positions**: 6 configurable positions
  - Product page
  - Shop/archive page
  - Cart page
  - Checkout page
  - Header (via hook)
  - Footer (via hook)
- **Icon Styles**: 4 icon display modes
  - Flag icons
  - Currency codes (USD, EUR, etc.)
  - Currency symbols ($, €, etc.)
  - Currency names (text)
  - Custom mode (mix and match)
- **Appearance Customization**
  - Theme color picker
  - Border radius slider (0-20px)
  - Box shadow toggle
  - Animation toggle
- **Admin Settings**: New "Selector Settings" tab in Enterprise settings

### Changed
- Renamed `bar` style to `horizontal` (backward compatible)
- Renamed `links` style to `flag-grid` (backward compatible)
- CSS now uses CSS custom properties for dynamic styling

### Fixed
- ZIP file upload now works without ZipArchive PHP extension
- Improved error handling for file uploads

---

## [1.1.0] - 2026-04-18

### Added
- **Live Chat Widget**: Built-in customer support widget
  - 12+ channels: QQ, WeChat, WhatsApp, Telegram, Line, Messenger, Viber, Signal, Phone, Email, Contact Form
  - 6 position options
  - 4 icon styles
  - Custom theme color
  - Backend message management
- **GeoIP Detection**: Auto-detect visitor country by IP
  - Free GeoIP API integration
  - Auto-recommend local currency
  - Cookie-based preference storage
- **WooCommerce Blocks Compatibility**: Full support for block-based checkout
  - Product grid blocks
  - Single product blocks
  - Cart blocks
  - Checkout blocks

### Changed
- Improved exchange rate caching (30-minute TTL)
- Better error handling for API failures

### Fixed
- Price conversion rounding issues
- Currency symbol display for custom currencies

---

## [1.0.0] - 2026-04-15

### Added
- **Core Features**
  - Support for 206 global currencies
  - Manual exchange rate management
  - Custom rate markup percentage
  - Rate validity period (30 hours)
- **Price Conversion**
  - Auto conversion on product pages
  - Shop/archive page conversion
  - Cart page conversion
  - Checkout page conversion
  - Coupon amount conversion
  - Shipping cost conversion
- **Currency Selector**
  - Dropdown style selector
  - Shortcode support `[pcs_selector]`
  - PHP function for theme integration
- **Admin Panel**
  - Currency management interface
  - Exchange rate settings
  - License management (Pro versions)
- **Multi-version Support**
  - Free version (basic features)
  - Pro Single (1 site)
  - Pro Multi (3 sites)
  - Pro Business (10 sites)
  - Pro Enterprise (unlimited sites)

### Security
- Nonce verification on all forms
- Input sanitization
- Output escaping
- Capability checks for admin actions

---

## Version History

| Version | Date | Description |
|---------|------|-------------|
| 1.2.1 | 2026-05-03 | Security hardening release |
| 1.2.0 | 2026-04-21 | Selector styles, positions, customization |
| 1.1.0 | 2026-04-18 | Live chat widget, GeoIP, Blocks support |
| 1.0.0 | 2026-04-15 | Initial release |

---

## Upgrade Guide

### From 1.2.0 to 1.2.1

1. Deactivate the plugin
2. Upload the new version (or update via WordPress.org)
3. Reactivate the plugin
4. **Important**: A new unique API secret key will be auto-generated on first activation. If you have a Pro license, re-activate your license key in **Currency Switcher → License**.

**Note**: This is a security release. All users are strongly recommended to upgrade.

### From 1.1.x to 1.2.0

1. Deactivate the plugin
2. Upload the new version
3. Reactivate the plugin
4. Go to **Currency Switcher → Advanced Settings → Selector Settings**
5. Configure your preferred selector style and position

**Note**: Existing `bar` and `links` styles will automatically map to `horizontal` and `flag-grid`.

### From 1.0.x to 1.1.0

1. Deactivate the plugin
2. Upload the new version
3. Reactivate the plugin
4. New features (Live Chat, GeoIP) are disabled by default
5. Enable them in **Currency Switcher → General Settings**

---

## Roadmap

### Planned for 1.3.0
- [ ] Cryptocurrency support (BTC, ETH, USDT)
- [ ] Apple Pay / Google Pay currency detection
- [ ] A/B testing for currency display
- [ ] Advanced analytics dashboard

### Planned for 1.4.0
- [ ] Multi-language currency names
- [ ] Currency-specific payment gateways
- [ ] Scheduled rate updates
- [ ] Rate alert notifications

### Future Considerations
- [ ] AI-powered price optimization
- [ ] Competitor price monitoring
- [ ] Multi-store synchronization
- [ ] REST API for headless WooCommerce

---

[1.2.1]: https://github.com/wqqwa/pro-currency-switcher/compare/v1.2.0...v1.2.1
[1.2.0]: https://github.com/wqqwa/pro-currency-switcher/compare/v1.1.0...v1.2.0
[1.1.0]: https://github.com/wqqwa/pro-currency-switcher/compare/v1.0.0...v1.1.0
[1.0.0]: https://github.com/wqqwa/pro-currency-switcher/releases/tag/v1.0.0
