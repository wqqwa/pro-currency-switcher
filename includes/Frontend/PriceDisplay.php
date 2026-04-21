<?php
/**
 * 价格显示转换器
 * 在前端自动转换WooCommerce产品价格、购物车价格等
 *
 * @package ProCurrencySwitcher
 * @since 1.0.0
 */

namespace ProCurrencySwitcher\Frontend;

use ProCurrencySwitcher\Core\CurrencyService;

if (!defined('ABSPATH')) {
    exit;
}

class PriceDisplay {

    public function __construct() {
        add_filter('woocommerce_get_price_html', [$this, 'convert_price_display'], 10, 2);
        add_filter('woocommerce_cart_item_price', [$this, 'convert_cart_price'], 10, 3);
        add_filter('woocommerce_cart_item_subtotal', [$this, 'convert_cart_subtotal'], 10, 3);
        // 确保前端价格样式正确加载
        add_action('wp_enqueue_scripts', [$this, 'enqueue_price_styles']);
    }

    /**
     * 加载价格显示样式
     */
    public function enqueue_price_styles() {
        $custom_css = "
            .pcs-converted-price {
                font-weight: 700 !important;
                color: inherit;
            }
            .pcs-converted-price .pcs-currency-symbol {
                font-weight: 700;
                margin-right: 2px;
            }
            .pcs-converted-price .pcs-price-amount {
                font-weight: 700;
            }
        ";
        wp_add_inline_style('pcs-frontend', $custom_css);
    }

    public function convert_price_display($price_html, $product) {
        if (!class_exists(CurrencyService::class)) {
            return $price_html;
        }

        try {
            $service = CurrencyService::get_instance();

            $current_currency = $service->get_current_currency();
            $base_currency = $service->get_base_currency();

            if ($current_currency === $base_currency) {
                return $price_html;
            }

            // WooCommerce 内部已经通过 woocommerce_product_get_price filter
            // 返回了转换后的价格，这里只需要重新格式化显示
            // 获取转换后的价格
            $regular_price = floatval($product->get_regular_price());
            $sale_price = floatval($product->get_sale_price());

            // 格式化价格（使用当前货币的符号和格式）
            $formatted_regular = $this->format_price($regular_price, $current_currency);
            $formatted_sale = $this->format_price($sale_price, $current_currency);

            // 保留 WooCommerce 原生 HTML 结构（原价划线 + 活动价）
            if ($sale_price > 0 && $regular_price > $sale_price) {
                return '<del aria-hidden="true">' . $formatted_regular . '</del> <ins>' . $formatted_sale . '</ins>';
            } else {
                return $formatted_regular;
            }

        } catch (Exception $e) {
            error_log('PriceDisplay error: ' . $e->getMessage());
            return $price_html;
        }
    }

    public function convert_cart_price($price, $cart_item, $cart_item_key) {
        return $this->convert_price_string($price);
    }

    public function convert_cart_subtotal($subtotal, $cart_item, $cart_item_key) {
        return $this->convert_price_string($subtotal);
    }

    private function convert_price_string($price_string) {
        if (!class_exists(CurrencyService::class)) {
            return $price_string;
        }

        try {
            $service = CurrencyService::get_instance();

            $current_currency = $service->get_current_currency();
            $base_currency = $service->get_base_currency();

            if ($current_currency === $base_currency) {
                return $price_string;
            }

            // WooCommerce 内部已经返回了转换后的价格，只需重新格式化
            $price = floatval(preg_replace('/[^\d.]/', '', $price_string));
            return $this->format_price($price, $current_currency);

        } catch (Exception $e) {
            error_log('PriceDisplay error: ' . $e->getMessage());
            return $price_string;
        }
    }

    /**
     * 格式化价格（带加粗样式）
     * 使用HTML包裹确保价格显示为粗体
     */
    private function format_price($price, $currency) {
        // 使用全局货币数据获取符号信息
        $currencies = pcs_get_all_currencies_data();
        $currency_data = $currencies[$currency] ?? [];

        $symbol = $currency_data['symbol'] ?? $currency;
        $decimals = $currency_data['decimals'] ?? 2;
        $decimal_sep = $currency_data['decimal_separator'] ?? '.';
        $thousands_sep = $currency_data['thousands_separator'] ?? ',';
        $position = $currency_data['symbol_position'] ?? 'left';

        $formatted_amount = number_format($price, $decimals, $decimal_sep, $thousands_sep);

        // 根据符号位置组装价格，使用带加粗样式的HTML
        if ($position === 'right') {
            $formatted = '<span class="pcs-converted-price"><span class="pcs-price-amount">' . esc_html($formatted_amount) . '</span><span class="pcs-currency-symbol">' . esc_html($symbol) . '</span></span>';
        } else {
            $formatted = '<span class="pcs-converted-price"><span class="pcs-currency-symbol">' . esc_html($symbol) . '</span><span class="pcs-price-amount">' . esc_html($formatted_amount) . '</span></span>';
        }

        return $formatted;
    }
}
