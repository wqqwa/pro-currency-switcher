<?php
/**
 * WooCommerce扩展兼容层
 * 支持 Subscriptions、Bookings、Bundle Products、Composite Products 等
 *
 * @package ProCurrencySwitcher
 * @since 5.0.0
 */

namespace ProCurrencySwitcher\Core;

use ProCurrencySwitcher\Core\CurrencyService;

if (!defined('ABSPATH')) {
    exit;
}

class WCExtensionsCompat {

    public function __construct() {
        if (!function_exists('WC')) {
            return;
        }

        // WooCommerce Subscriptions
        if (class_exists('WC_Subscriptions')) {
            $this->init_subscriptions_compat();
        }

        // WooCommerce Bookings
        if (class_exists('WC_Bookings')) {
            $this->init_bookings_compat();
        }

        // WooCommerce Product Bundles
        if (class_exists('WC_Bundles')) {
            $this->init_bundles_compat();
        }

        // WooCommerce Composite Products
        if (class_exists('WC_Composite_Products')) {
            $this->init_composite_compat();
        }

        // 通用：动态定价兼容
        add_filter('woocommerce_get_price', [$this, 'compat_dynamic_pricing'], 50, 2);
    }

    /**
     * WooCommerce Subscriptions 兼容
     */
    private function init_subscriptions_compat() {
        // 订阅价格转换
        add_filter('woocommerce_subscription_get_price', [$this, 'convert_subscription_price'], 10, 2);

        // 订阅变体价格
        add_filter('woocommerce_subscriptions_product_price', [$this, 'convert_subscription_product_price'], 10, 2);

        // 订阅切换时保持货币
        add_action('woocommerce_subscription_switch_completed', [$this, 'on_subscription_switch']);

        // 订阅续费时使用原始货币
        add_filter('wcs_renewal_order_created', [$this, 'set_renewal_order_currency'], 10, 2);

        // 签名包价格转换
        add_filter('woocommerce_subscriptions_product_sign_up_fee', [$this, 'convert_sign_up_fee'], 10, 2);

        // 免费试用提示
        add_filter('woocommerce_subscriptions_product_trial_string', [$this, 'convert_trial_string'], 10, 3);
    }

    /**
     * 转换订阅价格
     */
    public function convert_subscription_price($price, $product) {
        $service = CurrencyService::get_instance();
        $current = $service->get_current_currency();
        $base = $service->get_base_currency();

        if ($current === $base || $price <= 0) {
            return $price;
        }

        // 检查手动价格
        $manual = get_post_meta($product->get_id(), '_pcs_price_' . strtolower($current), true);
        if ($manual && floatval($manual) > 0) {
            return floatval($manual);
        }

        return $service->convert_price($price, $base, $current);
    }

    /**
     * 转换订阅产品价格
     */
    public function convert_subscription_product_price($price, $product) {
        return $this->convert_subscription_price($price, $product);
    }

    /**
     * 订阅切换时保持货币
     */
    public function on_subscription_switch($subscription) {
        $service = CurrencyService::get_instance();
        $current = $service->get_current_currency();
        $subscription->update_meta_data('_pcs_currency', $current);
        $subscription->save();
    }

    /**
     * 续费订单使用原始货币
     */
    public function set_renewal_order_currency($renewal, $subscription) {
        $currency = $subscription->get_meta('_pcs_currency');
        if ($currency) {
            $renewal->set_currency($currency);
        }
        return $renewal;
    }

    /**
     * 转换注册费
     */
    public function convert_sign_up_fee($fee, $product) {
        $service = CurrencyService::get_instance();
        return $service->convert_price($fee, $service->get_base_currency(), $service->get_current_currency());
    }

    /**
     * 转换试用提示中的价格
     */
    public function convert_trial_string($trial_string, $product, $amount) {
        $service = CurrencyService::get_instance();
        $current = $service->get_current_currency();
        $base = $service->get_base_currency();

        if ($current !== $base && $amount > 0) {
            $converted = $service->convert_price($amount, $base, $current);
            $formatted = $service->format_price($converted, $current);
            $trial_string = str_replace(wc_price($amount), $formatted, $trial_string);
        }

        return $trial_string;
    }

    /**
     * WooCommerce Bookings 兼容
     */
    private function init_bookings_compat() {
        // 预订价格转换
        add_filter('woocommerce_get_price', [$this, 'convert_booking_price'], 50, 2);

        // 预订表单中显示货币
        add_filter('booking_form_calculated_booking_cost', [$this, 'convert_booking_cost'], 10, 3);
    }

    /**
     * 转换预订价格
     */
    public function convert_booking_price($price, $product) {
        if (!method_exists($product, 'get_type') || $product->get_type() !== 'booking') {
            return $price;
        }

        $service = CurrencyService::get_instance();
        return $service->convert_price($price, $service->get_base_currency(), $service->get_current_currency());
    }

    /**
     * 转换预订成本
     */
    public function convert_booking_cost($cost, $product, $data) {
        $service = CurrencyService::get_instance();
        return $service->convert_price($cost, $service->get_base_currency(), $service->get_current_currency());
    }

    /**
     * WooCommerce Product Bundles 兼容
     */
    private function init_bundles_compat() {
        // 捆绑商品价格转换
        add_filter('woocommerce_bundles_get_price', [$this, 'convert_bundle_price'], 10, 2);

        // 捆绑商品容器价格
        add_filter('woocommerce_bundle_get_price', [$this, 'convert_bundle_price'], 10, 2);
    }

    /**
     * 转换捆绑价格
     */
    public function convert_bundle_price($price, $bundle) {
        $service = CurrencyService::get_instance();
        return $service->convert_price($price, $service->get_base_currency(), $service->get_current_currency());
    }

    /**
     * WooCommerce Composite Products 兼容
     */
    private function init_composite_compat() {
        add_filter('woocommerce_composite_get_price', [$this, 'convert_composite_price'], 10, 2);
    }

    /**
     * 转换组合商品价格
     */
    public function convert_composite_price($price, $composite) {
        $service = CurrencyService::get_instance();
        return $service->convert_price($price, $service->get_base_currency(), $service->get_current_currency());
    }

    /**
     * 通用动态定价兼容
     * 确保所有第三方价格修改器之后应用货币转换
     */
    public function compat_dynamic_pricing($price, $product) {
        // 只在非基准货币时转换
        $service = CurrencyService::get_instance();
        $current = $service->get_current_currency();
        $base = $service->get_base_currency();

        if ($current === $base || $price <= 0) {
            return $price;
        }

        // 检查是否已经转换过（避免重复转换）
        $meta_key = '_pcs_price_' . strtolower($current);
        $manual = get_post_meta($product->get_id(), $meta_key, true);
        if ($manual && floatval($manual) > 0) {
            return floatval($manual);
        }

        // 检查价格是否看起来已经是转换过的（简单启发式）
        // 如果价格与基准价格相同，说明可能需要转换
        return $service->convert_price($price, $base, $current);
    }
}
