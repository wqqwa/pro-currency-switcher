# Pro Currency Switcher

<div align="center">

![WordPress Plugin Version](https://img.shields.io/badge/version-1.2.0-blue)
![WordPress](https://img.shields.io/badge/WordPress-5.0%2B-blue)
![WooCommerce](https://img.shields.io/badge/WooCommerce-5.0%2B-purple)
![PHP](https://img.shields.io/badge/PHP-7.4%2B-777BB4)
![License](https://img.shields.io/badge/license-Non--Commercial-orange)

**强大的 WooCommerce 多货币切换插件**

[功能特性](#功能特性) • [安装指南](#安装指南) • [使用方法](#使用方法) • [文档](#文档) • [参与贡献](#参与贡献)

**中文** | [English](README_en.md)
</div>

---

## 📖 项目简介

Pro Currency Switcher 是一款专业的 WooCommerce 多货币切换插件，让你的商店能够以多种货币显示价格。插件会自动检测访客所在地区，并切换到当地货币，有效提升跨境电商的转化率。

### 为什么选择本插件？

- 🌍 **支持 206 种货币** — 覆盖全球几乎所有国家和地区
- 🔄 **自动汇率更新** — 从免费公共 API 获取实时汇率（无需 API Key）
- 🎯 **GeoIP 自动检测** — 根据访客 IP 自动推荐本地货币
- 💬 **在线客服组件** — 内置 12+ 渠道的客服功能
- 📦 **兼容 WooCommerce Blocks** — 完美支持新版区块编辑器结账
- ⚡ **缓存友好** — 兼容 WP Super Cache、W3 Total Cache 和 CDN

---

## ✨ 功能特性

### 免费版功能

| 功能 | 说明 |
|------|------|
| 多货币支持 | 为你的 WooCommerce 商店启用最多 206 种货币 |
| 手动汇率 | 为每种货币设置自定义汇率 |
| 货币选择器 | 产品页面上的简洁下拉选择器 |
| 价格转换 | 在商品页、购物车、结账页自动转换价格 |
| GeoIP 检测 | 根据 IP 自动检测访客国家并推荐货币 |
| 在线客服 | QQ、微信、WhatsApp、Telegram 等 12+ 渠道 |
| Blocks 兼容 | 完美兼容 WooCommerce 区块化结账 |
| 缓存兼容 | 基于 Cookie 检测 + JS 动态更新 |

### Pro 版功能（付费）

| 功能 | 说明 |
|------|------|
| API 自动汇率 | 每 30 分钟自动更新汇率 |
| 价格取整 | 8 种取整预设（.99、.95、.00 等） |
| 图片水印 | 在商品图片上添加货币水印 |
| 订单分析 | 可视化仪表盘展示货币使用统计 |
| 国家定价 | 按国家/地区设置不同价格 |
| 多种选择器样式 | 5 种样式：下拉菜单、浮动按钮、侧边栏、水平列表、国旗网格 |
| 自定义外观 | 主题色、圆角、阴影、动画效果 |

---

## 📥 安装指南

### 从 WordPress.org 安装（推荐）

1. 在 WordPress 后台进入 **插件 → 安装插件**
2. 搜索 "Pro Currency Switcher"
3. 点击 **立即安装**，然后 **启用**

### 手动安装

1. 下载 [最新版本](https://github.com/wqqwa/pro-currency-switcher/releases)
2. 将 `pro-currency-switcher` 文件夹上传到 `/wp-content/plugins/`
3. 在 WordPress 后台的 **插件** 菜单中启用插件
4. 到 **Currency Switcher → 常规设置** 配置货币

### 系统要求

| 要求 | 版本 |
|------|------|
| WordPress | 5.0 或更高 |
| WooCommerce | 5.0 或更高 |
| PHP | 7.4 或更高 |
| MySQL | 5.7 或更高 |

---

## 🚀 使用方法

### 基础设置

1. **设置基础货币**  
   进入 **Currency Switcher → 常规设置**，选择商店的基础货币。

2. **启用货币**  
   勾选你想启用的货币。插件支持 206 种货币。

3. **配置汇率**  
   - **手动模式**：输入自定义汇率
   - **自动模式**（Pro 版）：汇率每 30 分钟自动更新

4. **启用 GeoIP 检测**（可选）  
   开启"自动检测用户货币"，自动推荐本地货币。

### 短代码

在任意位置显示货币选择器：

```
[pcs_selector]
```

### PHP 调用

```php
// 获取当前货币
$currency = \ProCurrencySwitcher\Core\CurrencyService::get_instance()->get_current_currency();

// 转换价格
$converted = \ProCurrencySwitcher\Core\CurrencyService::get_instance()->convert_price(100, 'USD', 'EUR');

// 格式化价格
$formatted = \ProCurrencySwitcher\Core\CurrencyService::get_instance()->format_price(100, 'EUR');
```

---

## 📚 文档

- [安装指南](docs/installation.md)
- [配置指南](docs/configuration.md)
- [开发者 API](docs/api.md)
- [Hooks 参考](docs/hooks.md)
- [常见问题](docs/faq.md)
- [技术架构](docs/ARCHITECTURE.md)
- [设计规范](docs/DESIGN.md)

---

## 🤝 参与贡献

欢迎贡献代码！请查看 [贡献指南](CONTRIBUTING.md) 了解详情。

### 开发环境搭建

```bash
# 克隆仓库
git clone https://github.com/wqqwa/pro-currency-switcher.git

# 进入目录
cd pro-currency-switcher
```

### 代码规范

本项目遵循 [WordPress 编码规范](https://developer.wordpress.org/coding-standards/)。

---

## 📝 开源协议

本项目采用 **MIT + 非商业使用限制** 协议。

- ✅ **免费使用**：个人网站、教育、研究、开发测试
- ❌ **需要商业授权**：盈利性商店、SaaS、客户项目

商业授权请访问 [hb.woocross.com/pricing.php](https://hb.woocross.com/pricing.php)。详见 [LICENSE](LICENSE)。

---

## 🔗 相关链接

- [插件官网](https://hb.woocross.com)
- [Pro 版购买](https://hb.woocross.com/pricing.php)
- [问题反馈](https://github.com/wqqwa/pro-currency-switcher/issues)
- [技术支持](mailto:woocross@qq.com)

---

<div align="center">

**由 [WooCross](https://woocross.com) 用 ❤️ 打造**

</div>
