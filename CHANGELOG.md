# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

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
| 1.2.0 | 2026-04-21 | Selector styles, positions, customization |
| 1.1.0 | 2026-04-18 | Live chat widget, GeoIP, Blocks support |
| 1.0.0 | 2026-04-15 | Initial release |

---

## Upgrade Guide

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

[1.2.0]: https://github.com/woocross/pro-currency-switcher/compare/v1.1.0...v1.2.0
[1.1.0]: https://github.com/woocross/pro-currency-switcher/compare/v1.0.0...v1.1.0
[1.0.0]: https://github.com/woocross/pro-currency-switcher/releases/tag/v1.0.0
