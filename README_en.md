# Pro Currency Switcher

<div align="center">

![WordPress Plugin Version](https://img.shields.io/badge/version-1.2.0-blue)
![WordPress](https://img.shields.io/badge/WordPress-5.0%2B-blue)
![WooCommerce](https://img.shields.io/badge/WooCommerce-5.0%2B-purple)
![PHP](https://img.shields.io/badge/PHP-7.4%2B-777BB4)
![License](https://img.shields.io/badge/license-Non--Commercial-orange)

**A powerful multi-currency switcher plugin for WooCommerce**

[Features](#features) • [Installation](#installation) • [Usage](#usage) • [Documentation](#documentation) • [Contributing](#contributing)

English | [中文](README.md)

</div>

---

## 📖 Overview

Pro Currency Switcher is a professional WooCommerce plugin that enables your store to display prices in multiple currencies. It automatically detects visitor location and switches to their local currency, improving conversion rates for international customers.

### Why Choose This Plugin?

- 🌍 **206 Currencies Supported** - Cover almost all countries and regions worldwide
- 🔄 **Auto Exchange Rates** - Fetch real-time rates from free public APIs (no API key required)
- 🎯 **GeoIP Detection** - Automatically recommend local currency based on visitor IP
- 💬 **Live Chat Widget** - Built-in customer support widget with 12+ channels
- 📦 **WooCommerce Blocks Compatible** - Full support for modern block-based checkout
- ⚡ **Cache Friendly** - Works with WP Super Cache, W3 Total Cache, and CDNs

---

## ✨ Features

### Core Features (Free)

| Feature | Description |
|---------|-------------|
| Multi-Currency Support | Enable up to 206 currencies for your WooCommerce store |
| Manual Exchange Rates | Set custom exchange rates for each currency |
| Currency Selector | Simple dropdown selector on product pages |
| Price Conversion | Automatic price conversion on shop, cart, and checkout pages |
| GeoIP Detection | Auto-detect visitor country and recommend currency |
| Live Chat Widget | QQ, WeChat, WhatsApp, Telegram, and 8+ more channels |
| WooCommerce Blocks | Full compatibility with block-based checkout |
| Cache Compatibility | Cookie-based detection + JS dynamic updates |

### Pro Features (Paid)

| Feature | Description |
|---------|-------------|
| API Auto Rates | Automatic rate updates every 30 minutes |
| Price Rounding | 8 rounding presets (.99, .95, .00, etc.) |
| Image Watermark | Add currency watermark to product images |
| Order Analytics | Visual dashboard for currency usage statistics |
| Country Pricing | Set different prices by country/region |
| Multiple Selector Styles | 5 styles: dropdown, floating, sidebar, horizontal, flag-grid |
| Custom Appearance | Theme color, border radius, shadow, animation |

---

## 📥 Installation

### From WordPress.org (Recommended)

1. Go to **Plugins → Add New** in your WordPress admin
2. Search for "Pro Currency Switcher"
3. Click **Install Now** and then **Activate**

### Manual Installation

1. Download the [latest release](https://github.com/woocross/pro-currency-switcher/releases)
2. Upload the `pro-currency-switcher` folder to `/wp-content/plugins/`
3. Activate the plugin through the **Plugins** menu in WordPress
4. Configure currencies at **Currency Switcher → General Settings**

### Requirements

| Requirement | Version |
|-------------|---------|
| WordPress | 5.0 or higher |
| WooCommerce | 5.0 or higher |
| PHP | 7.4 or higher |
| MySQL | 5.7 or higher |

---

## 🚀 Usage

### Basic Setup

1. **Set Base Currency**  
   Go to **Currency Switcher → General Settings** and select your store's base currency.

2. **Enable Currencies**  
   Click on the currencies you want to enable. The plugin supports 206 currencies.

3. **Configure Exchange Rates**  
   - **Manual Mode**: Enter custom exchange rates
   - **Auto Mode** (Pro): Rates update automatically every 30 minutes

4. **Enable GeoIP Detection** (Optional)  
   Turn on "Auto Detect User Currency" to automatically recommend local currency.

### Shortcode

Display a currency selector anywhere using shortcode:

```
[pcs_selector]
```

### PHP Integration

```php
// Get current currency
$currency = \ProCurrencySwitcher\Core\CurrencyService::get_instance()->get_current_currency();

// Convert price
$converted = \ProCurrencySwitcher\Core\CurrencyService::get_instance()->convert_price(100, 'USD', 'EUR');

// Format price
$formatted = \ProCurrencySwitcher\Core\CurrencyService::get_instance()->format_price(100, 'EUR');
```

---

## 📚 Documentation

- [Installation Guide](docs/installation.md)
- [Configuration Guide](docs/configuration.md)
- [Developer API](docs/api.md)
- [Hooks Reference](docs/hooks.md)
- [FAQ](docs/faq.md)

---

## 🤝 Contributing

We welcome contributions! Please see our [Contributing Guide](CONTRIBUTING.md) for details.

### Development Setup

```bash
# Clone the repository
git clone https://github.com/woocross/pro-currency-switcher.git

# Navigate to the directory
cd pro-currency-switcher

# Install dependencies (if any)
composer install
```

### Coding Standards

This project follows [WordPress Coding Standards](https://developer.wordpress.org/coding-standards/). Please ensure your code passes PHPCS checks before submitting a PR.

---

## 📝 License

This plugin is licensed under the **MIT License with Non-Commercial Restriction**.

- ✅ **Free for**: Personal websites, education, research, development/testing
- ❌ **Requires commercial license**: Revenue-generating stores, SaaS, client projects

For commercial use, visit [hb.woocross.com/pricing.php](https://hb.woocross.com/pricing.php). See [LICENSE](LICENSE) for details.

---

## 🔗 Links

- [Plugin Homepage](https://hb.woocross.com)
- [WordPress.org](https://wordpress.org/plugins/pro-currency-switcher/)
- [Support Forum](https://wordpress.org/support/plugin/pro-currency-switcher/)
- [Pro Version](https://hb.woocross.com/pricing.php)

---

## 💬 Support

- **Documentation**: [docs/](docs/)
- **Issues**: [GitHub Issues](https://github.com/woocross/pro-currency-switcher/issues)
- **Email**: woocross@qq.com
- **WhatsApp**: [+86 123 4567 8900](https://wa.me/8612345678900)

---

<div align="center">

**Made with ❤️ by [WooCross](https://woocross.com)**

</div>
