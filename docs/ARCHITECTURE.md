# Architecture Overview

This document describes the technical architecture of Pro Currency Switcher.

## 📐 System Architecture

```
┌─────────────────────────────────────────────────────────────────┐
│                        WordPress + WooCommerce                    │
├─────────────────────────────────────────────────────────────────┤
│                                                                   │
│  ┌─────────────┐  ┌─────────────┐  ┌─────────────┐              │
│  │   Admin     │  │   Core      │  │  Frontend   │              │
│  │   Panel     │  │   Engine    │  │   Display   │              │
│  └──────┬──────┘  └──────┬──────┘  └──────┬──────┘              │
│         │                │                │                      │
│         └────────────────┼────────────────┘                      │
│                          │                                       │
│                    ┌─────┴─────┐                                 │
│                    │   Hooks   │                                 │
│                    │  Manager  │                                 │
│                    └─────┬─────┘                                 │
│                          │                                       │
│         ┌────────────────┼────────────────┐                      │
│         │                │                │                      │
│    ┌────┴────┐     ┌─────┴─────┐    ┌─────┴─────┐                │
│    │ Options │     │  Database │    │   Cache   │                │
│    │  API    │     │   Layer   │    │  Manager  │                │
│    └─────────┘     └───────────┘    └───────────┘                │
│                                                                   │
└─────────────────────────────────────────────────────────────────┘
```

## 🗂️ Directory Structure

```
pro-currency-switcher/
├── pro-currency-switcher.php      # Main plugin file (entry point)
├── uninstall.php                  # Cleanup on plugin uninstall
│
├── includes/
│   ├── Core/
│   │   ├── CurrencyService.php    # Currency management & conversion
│   │   ├── ExchangeRates.php      # Exchange rate fetching
│   │   ├── GeoIPService.php       # IP geolocation service
│   │   ├── PriceFormatter.php     # Price formatting utilities
│   │   └── BlocksCompat.php       # WooCommerce Blocks compatibility
│   │
│   ├── Frontend/
│   │   ├── CurrencySelector.php   # Currency selector rendering
│   │   ├── PriceConverter.php     # Frontend price conversion
│   │   ├── ContactWidget.php      # Live chat widget
│   │   └── Assets.php             # CSS/JS enqueue management
│   │
│   ├── Admin/
│   │   ├── AdminSettings.php      # Settings page
│   │   ├── CurrencyTable.php      # Currency list table
│   │   └── AjaxHandlers.php       # AJAX request handlers
│   │
│   └── Utils/
│       ├── Helpers.php            # Helper functions
│       └── Logger.php             # Debug logging
│
├── assets/
│   ├── css/
│   │   ├── admin.css              # Admin panel styles
│   │   ├── frontend.css           # Frontend styles
│   │   └── selector-styles.css    # Currency selector styles
│   │
│   ├── js/
│   │   ├── admin.js               # Admin panel scripts
│   │   ├── frontend.js            # Frontend scripts
│   │   └── selector.js            # Currency selector scripts
│   │
│   └── images/
│       └── flags/                 # Country flag icons
│
├── templates/
│   ├── selector-dropdown.php      # Dropdown selector template
│   ├── selector-float.php         # Floating button template
│   └── contact-widget.php         # Contact widget template
│
└── languages/
    ├── pro-currency-switcher.pot   # Translation template
    ├── pro-currency-switcher-zh_CN.po
    └── pro-currency-switcher-zh_CN.mo
```

## 🔄 Data Flow

### Price Conversion Flow

```
User Request
     │
     ▼
┌─────────────────┐
│ WooCommerce     │
│ Price Filter    │
└────────┬────────┘
         │
         ▼
┌─────────────────┐     ┌─────────────────┐
│ PriceConverter  │────▶│ CurrencyService │
│ (Frontend)      │     │ (Core)          │
└────────┬────────┘     └────────┬────────┘
         │                       │
         │              ┌────────┴────────┐
         │              │                 │
         │              ▼                 ▼
         │     ┌─────────────┐   ┌─────────────┐
         │     │ ExchangeRate│   │   Options   │
         │     │   Cache     │   │   (DB)      │
         │     └─────────────┘   └─────────────┘
         │
         ▼
┌─────────────────┐
│ PriceFormatter  │
│ (format output) │
└────────┬────────┘
         │
         ▼
    Display to User
```

### Currency Detection Flow

```
Visitor arrives
      │
      ▼
┌─────────────────┐
│ Check Cookie    │──── Previous selection? ────▶ Use saved currency
└────────┬────────┘
         │ No
         ▼
┌─────────────────┐
│ GeoIP Detection │──── Detect country by IP
└────────┬────────┘
         │
         ▼
┌─────────────────┐
│ Currency Mapping│──── Map country to currency
└────────┬────────┘
         │
         ▼
┌─────────────────┐
│ Set Cookie      │──── Save for 30 days
└─────────────────┘
```

## 🗄️ Database Schema

### Options Table (wp_options)

| Option Name | Type | Description |
|-------------|------|-------------|
| `pcs_enabled_currencies` | array | List of enabled currency codes |
| `pcs_exchange_rates` | array | Cached exchange rates |
| `pcs_rate_last_update` | timestamp | Last rate update time |
| `pcs_base_currency` | string | Store base currency (default: USD) |
| `pcs_rate_source` | string | Exchange rate API source |
| `pcs_geoip_enabled` | bool | GeoIP detection enabled |
| `pcs_selector_position` | array | Selector display positions |
| `pcs_contact_channels` | array | Enabled contact channels |

### Custom Tables (Pro Version)

```sql
-- Order analytics
CREATE TABLE wp_pcs_order_analytics (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    order_id BIGINT UNSIGNED NOT NULL,
    currency VARCHAR(3) NOT NULL,
    original_amount DECIMAL(19,4),
    converted_amount DECIMAL(19,4),
    exchange_rate DECIMAL(19,6),
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_currency (currency),
    INDEX idx_created (created_at)
);
```

## 🔌 Hooks Reference

### Actions

```php
// Fired when currency is switched
do_action( 'pcs_currency_switched', $new_currency, $old_currency );

// Fired when exchange rates are updated
do_action( 'pcs_rates_updated', $rates );

// Fired on plugin activation
do_action( 'pcs_activated' );

// Fired on plugin deactivation
do_action( 'pcs_deactivated' );
```

### Filters

```php
// Modify exchange rate
$rate = apply_filters( 'pcs_exchange_rate', $rate, $from, $to );

// Modify converted price
$price = apply_filters( 'pcs_converted_price', $price, $currency );

// Modify currency selector HTML
$html = apply_filters( 'pcs_selector_html', $html );

// Modify available currencies
$currencies = apply_filters( 'pcs_available_currencies', $currencies );
```

## 🔐 Security Considerations

### Input Sanitization

```php
// All user inputs are sanitized
$currency = sanitize_text_field( $_POST['currency'] );
$amount = floatval( $_POST['amount'] );
```

### Nonce Verification

```php
// All forms use nonce verification
if ( ! wp_verify_nonce( $_POST['pcs_nonce'], 'pcs_action' ) ) {
    wp_die( 'Security check failed' );
}
```

### Output Escaping

```php
// All outputs are escaped
echo esc_html( $currency );
echo esc_attr( $value );
echo esc_url( $link );
```

### Capability Checks

```php
// Admin actions check capabilities
if ( ! current_user_can( 'manage_woocommerce' ) ) {
    wp_die( 'Unauthorized' );
}
```

## ⚡ Performance Optimization

### Caching Strategy

```
┌─────────────────────────────────────────────────────────┐
│                     Cache Layers                         │
├─────────────────────────────────────────────────────────┤
│                                                         │
│  ┌─────────────┐  ┌─────────────┐  ┌─────────────┐     │
│  │  Object     │  │  Transient  │  │  Cookie     │     │
│  │  Cache      │  │  (DB)       │  │  (Client)   │     │
│  │  (Memory)   │  │             │  │             │     │
│  └─────────────┘  └─────────────┘  └─────────────┘     │
│                                                         │
│  Exchange Rates:  30 min TTL                            │
│  GeoIP Results:  24 hour TTL                            │
│  Currency Cookie: 30 days                               │
│                                                         │
└─────────────────────────────────────────────────────────┘
```

### Database Queries

- Use transients for cached data
- Batch queries where possible
- Index frequently queried columns
- Avoid N+1 query patterns

### Frontend Optimization

- CSS/JS minification
- Lazy load flag images
- Defer non-critical JS
- Use CSS sprites for flags

## 🌐 API Integration

### Exchange Rate APIs

| API | Free Limit | Update Frequency |
|-----|------------|------------------|
| exchangerate-api.com | 1,500/month | Daily |
| open.er-api.com | Unlimited | Daily |
| fixer.io | 100/month | Daily |

### GeoIP APIs

| API | Free Limit | Accuracy |
|-----|------------|----------|
| ip-api.com | 45/min | Country level |
| ipinfo.io | 50,000/month | Country level |
| Cloudflare (built-in) | Unlimited | Country level |

## 📱 Responsive Design

The plugin is fully responsive:

- Mobile-first CSS approach
- Touch-friendly selectors
- Adaptive widget positioning
- Responsive flag icons

## 🧪 Testing

### Unit Tests

```bash
# Run unit tests
vendor/bin/phpunit

# Run with coverage
vendor/bin/phpunit --coverage-html coverage/
```

### Integration Tests

```bash
# Run integration tests
vendor/bin/phpunit --testsuite integration
```

### Manual Testing Checklist

- [ ] Currency switching works on all pages
- [ ] Prices update correctly in cart/checkout
- [ ] GeoIP detection works correctly
- [ ] Selector displays properly on mobile
- [ ] Cache compatibility (WP Super Cache, W3TC)
- [ ] WooCommerce Blocks compatibility
- [ ] Multi-language compatibility (WPML, Polylang)

---

For more details, see the [API Documentation](api.md) and [Hooks Reference](hooks.md).
