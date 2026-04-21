<?php
/**
 * WooCommerce Blocks 兼容层
 * 支持新版编辑器区块（Block）中的价格转换
 *
 * @package ProCurrencySwitcher
 * @since 6.0.0
 */

namespace ProCurrencySwitcher\Core;

if (!defined('ABSPATH')) {
    exit;
}

class BlocksCompat {

    public function __construct() {
        if (!function_exists('WC')) {
            return;
        }

        // 注册 Block 价格转换
        add_filter('woocommerce_blocks_product_grid_item_html', [$this, 'convert_block_product_price'], 10, 3);

        // Single Product Block
        add_filter('woocommerce_product_price', [$this, 'convert_product_block_price'], 10, 2);

        // Cart Block
        add_filter('woocommerce_blocks_cart_item_price', [$this, 'convert_cart_block_price'], 10, 2);

        // Checkout Block
        add_filter('woocommerce_blocks_checkout_order_summary_order_total', [$this, 'convert_checkout_block_total'], 10, 2);

        // 开发者钩子：Blocks兼容初始化
        do_action('pcs_blocks_compat_init', $this);
    }

    /**
     * 转换产品网格区块中的价格
     */
    public function convert_block_product_price($html, $data, $product) {
        if (!class_exists(CurrencyService::class)) {
            return $html;
        }

        $service = CurrencyService::get_instance();
        $current = $service->get_current_currency();
        $base = $service->get_base_currency();

        if ($current === $base) {
            return $html;
        }

        // 提取价格并转换
        $price = $product->get_price();
        if ($price <= 0) {
            return $html;
        }

        $converted = $service->convert_price($price, $base, $current);
        $formatted = $service->format_price($converted, $current);

        // 替换HTML中的价格
        $pattern = '/<span class="woocommerce-Price-amount[^"]*">.*?<\/span>/';
        if (preg_match($pattern, $html)) {
            $html = preg_replace($pattern, $formatted, $html);
        }

        return apply_filters('pcs_block_product_price', $html, $product, $current);
    }

    /**
     * 转换产品区块价格
     */
    public function convert_product_block_price($price_html, $product) {
        if (!class_exists(CurrencyService::class)) {
            return $price_html;
        }

        $service = CurrencyService::get_instance();
        $current = $service->get_current_currency();
        $base = $service->get_base_currency();

        if ($current === $base) {
            return $price_html;
        }

        $price = $product->get_price();
        if ($price <= 0) {
            return $price_html;
        }

        $converted = $service->convert_price($price, $base, $current);
        return $service->format_price($converted, $current);
    }

    /**
     * 转换购物车区块价格
     */
    public function convert_cart_block_price($price, $cart_item) {
        if (!class_exists(CurrencyService::class)) {
            return $price;
        }

        $service = CurrencyService::get_instance();
        $current = $service->get_current_currency();
        $base = $service->get_base_currency();

        if ($current === $base) {
            return $price;
        }

        $numeric_price = floatval(preg_replace('/[^\d.]/', '', $price));
        $converted = $service->convert_price($numeric_price, $base, $current);
        return $service->format_price($converted, $current);
    }

    /**
     * 转换结账区块总价
     */
    public function convert_checkout_block_total($total_html, $order) {
        if (!class_exists(CurrencyService::class)) {
            return $total_html;
        }

        $service = CurrencyService::get_instance();
        $current = $service->get_current_currency();
        $base = $service->get_base_currency();

        if ($current === $base) {
            return $total_html;
        }

        $numeric = floatval(preg_replace('/[^\d.]/', '', $total_html));
        $converted = $service->convert_price($numeric, $base, $current);
        return $service->format_price($converted, $current);
    }
}
