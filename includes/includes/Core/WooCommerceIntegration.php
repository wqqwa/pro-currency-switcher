<?php
/**
 * WooCommerce深度集成
 * 处理优惠券、运费、订单的多币种转换
 *
 * @package ProCurrencySwitcher
 * @since 1.0.0
 */

namespace ProCurrencySwitcher\Core;

use ProCurrencySwitcher\Core\CurrencyService;

if (!defined('ABSPATH')) {
    exit;
}

class WooCommerceIntegration {

    /**
     * 请求级转换缓存：防止同一请求内对同一产品的同一价格属性重复转换
     * key: "{product_id}_{price_type}", value: 转换后的价格
     */
    private static $converted_prices = [];

    /**
     * 支付网关支持的货币列表
     * 不在此列表中的货币在结账时自动回退到基准货币（USD）
     */
    private static $gateway_supported_currencies = [
        'paypal' => ['USD', 'EUR', 'GBP', 'JPY', 'AUD', 'CAD', 'CHF', 'HKD', 'SGD', 'NZD', 'SEK', 'DKK', 'NOK', 'MXN', 'BRL', 'PHP', 'TWD', 'THB', 'MYR', 'CZK', 'HUF', 'ILS', 'KRW', 'PLN', 'RUB', 'TRY', 'UAH', 'ZAR'],
        'stripe' => ['USD', 'EUR', 'GBP', 'JPY', 'AUD', 'CAD', 'CHF', 'HKD', 'SGD', 'NZD', 'MXN', 'BRL', 'PHP', 'TWD', 'THB', 'MYR', 'CZK', 'DKK', 'NOK', 'SEK', 'PLN', 'HUF', 'ILS', 'KRW', 'RUB', 'TRY', 'UAH', 'ZAR', 'CNY', 'IDR', 'INR', 'VND'],
    ];

    /**
     * 初始化
     */
    public function __construct() {
        if (!function_exists('WC')) {
            return;
        }

        // 核心修复：转换产品原始价格（让 WC 内部使用转换后的价格）
        // 当 woocommerce_currency 被过滤为非基准货币时，产品价格需要从基准货币转换
        // 三个 filter 分别注册，缓存 key 区分价格类型，防止 regular/sale 互相干扰
        add_filter('woocommerce_product_get_price', [$this, 'convert_product_price'], 10, 2);
        add_filter('woocommerce_product_get_regular_price', [$this, 'convert_regular_price'], 10, 2);
        add_filter('woocommerce_product_get_sale_price', [$this, 'convert_sale_price'], 10, 2);

        // 优惠券金额转换
        add_filter('woocommerce_coupon_get_amount', [$this, 'convert_coupon_amount'], 10, 2);
        add_filter('woocommerce_coupon_get_minimum_amount', [$this, 'convert_coupon_min_amount'], 10, 2);
        add_filter('woocommerce_coupon_get_maximum_amount', [$this, 'convert_coupon_max_amount'], 10, 2);

        // 运费转换
        add_filter('woocommerce_shipping_method_add_rate_args', [$this, 'convert_shipping_rate'], 10, 2);

        // 订单货币记录
        add_action('woocommerce_checkout_update_order_meta', [$this, 'save_order_currency']);
        add_action('woocommerce_admin_order_data_after_order_details', [$this, 'display_order_currency']);

        // 订单金额转换回基准货币（用于报表）
        add_filter('woocommerce_get_order_totals', [$this, 'adjust_order_totals_display'], 10, 2);

        // 购物车货币显示
        add_filter('woocommerce_cart_totals_order_total_html', [$this, 'convert_cart_total']);
        add_filter('woocommerce_get_formatted_order_total', [$this, 'convert_order_total_display'], 10, 2);

        // 结账货币锁定
        add_action('woocommerce_before_checkout_form', [$this, 'lock_checkout_currency']);

        // 支付网关货币回退：不支持当前货币时，在创建订单时将金额转回基准货币
        add_action('woocommerce_checkout_create_order', [$this, 'fallback_order_to_base_currency'], 10, 2);

        // 邮件中显示原始货币
        add_filter('woocommerce_email_order_items_table', [$this, 'show_currency_in_email'], 10, 2);
    }

    /**
     * 转换产品价格（通用方法）
     * 
     * @param float $price 原始价格
     * @param WC_Product $product 产品对象
     * @param string $price_type 价格类型标识（price/regular/sale）
     * @return float 转换后的价格
     */
    private function do_convert_price($price, $product, $price_type) {
        if ($price <= 0) return $price;

        $service = CurrencyService::get_instance();
        $current = $service->get_current_currency();
        $base = $service->get_base_currency();

        // 当前货币就是基准货币，不需要转换
        if ($current === $base) return $price;

        // 防止重复转换：检查价格是否已经被转换过
        // 方法：比较传入价格与数据库中的原始价格
        // 如果传入价格 != 数据库原始价格，说明已经被转换过，直接返回
        $cache_key = $product->get_id() . '_' . $price_type . '_original';
        if (!isset(self::$converted_prices[$cache_key])) {
            // 从数据库读取原始价格（不受 filter 影响）
            $meta_key = '_' . $price_type . '_price';
            if ($price_type === 'price') {
                $meta_key = '_price';
            }
            $original_price = get_post_meta($product->get_id(), $meta_key, true);
            self::$converted_prices[$cache_key] = floatval($original_price);
        }

        $original_price = self::$converted_prices[$cache_key];
        if ($original_price > 0 && abs($price - $original_price) > 0.01) {
            // 传入价格与数据库原始价格不同，说明已经被转换过
            return $price;
        }

        // 从基准货币转换为当前货币
        $converted = $service->convert_price($price, $base, $current);

        return $converted;
    }

    public function convert_product_price($price, $product) {
        return $this->do_convert_price($price, $product, 'price');
    }

    public function convert_regular_price($price, $product) {
        return $this->do_convert_price($price, $product, 'regular');
    }

    public function convert_sale_price($price, $product) {
        return $this->do_convert_price($price, $product, 'sale');
    }

    /**
     * 转换优惠券金额
     */
    public function convert_coupon_amount($amount, $coupon) {
        if ($amount <= 0) return $amount;

        $service = CurrencyService::get_instance();
        $current = $service->get_current_currency();
        $base = $service->get_base_currency();

        if ($current === $base) return $amount;

        // 检查优惠券是否有手动设置的金额
        $manual = get_post_meta($coupon->get_id(), '_pcs_coupon_amount_' . strtolower($current), true);
        if ($manual && floatval($manual) > 0) {
            return floatval($manual);
        }

        // 自动转换
        $amount = $service->convert_price($amount, $base, $current);
        $amount = apply_filters('pcs_coupon_amount', $amount, $coupon);
        return $amount;
    }

    /**
     * 转换优惠券最低金额
     */
    public function convert_coupon_min_amount($amount, $coupon) {
        if ($amount <= 0) return $amount;
        $service = CurrencyService::get_instance();
        return $service->convert_price($amount, $service->get_base_currency(), $service->get_current_currency());
    }

    /**
     * 转换优惠券最高金额
     */
    public function convert_coupon_max_amount($amount, $coupon) {
        if ($amount <= 0) return $amount;
        $service = CurrencyService::get_instance();
        return $service->convert_price($amount, $service->get_base_currency(), $service->get_current_currency());
    }

    /**
     * 转换运费
     * WooCommerce 内部已经通过 woocommerce_currency 使用正确的货币，
     * 这里不再需要手动转换汇率，只需通过 filter 允许第三方调整
     */
    public function convert_shipping_rate($args, $method) {
        $service = CurrencyService::get_instance();
        $current = $service->get_current_currency();
        $base = $service->get_base_currency();

        if ($current === $base) return $args;

        $args = apply_filters('pcs_shipping_rate', $args, $method);
        return $args;
    }

    /**
     * 保存订单货币信息
     */
    public function save_order_currency($order_id) {
        $service = CurrencyService::get_instance();
        $current = $service->get_current_currency();
        $base = $service->get_base_currency();

        $order = wc_get_order($order_id);
        if (!$order) return;

        // 记录用户选择的显示货币
        $order->update_meta_data('_pcs_order_currency', $current);
        $order->update_meta_data('_pcs_base_currency', $base);
        $order->update_meta_data('_pcs_exchange_rate', $service->get_exchange_rate($base, $current));
        $order->update_meta_data('_pcs_exchange_rate_time', current_time('mysql'));

        $order->save();

        do_action('pcs_order_currency_saved', $order_id, $current, $base);
    }

    /**
     * 在订单详情页显示货币信息
     */
    public function display_order_currency($order) {
        $currency = $order->get_meta('_pcs_order_currency');
        $base = $order->get_meta('_pcs_base_currency');
        $rate = $order->get_meta('_pcs_exchange_rate');

        if (empty($currency)) return;

        $currencies = pcs_get_all_currencies_data();
        $data = $currencies[$currency] ?? [];

        echo '<div class="pcs-order-currency-info" style="background:#f8f9fa;padding:10px 15px;border-radius:4px;margin:10px 0;">';
        echo '<strong>' . esc_html__('Order Currency', 'pro-currency-switcher') . ':</strong> ';
        echo esc_html($data['flag'] ?? '') . ' ' . esc_html($currency) . ' - ' . esc_html($data['name'] ?? $currency);
        if ($rate) {
            echo '<br><small>' . sprintf(esc_html__('Rate: 1 %s = %s %s (%s)'), $base, number_format($rate, 6), $currency, $order->get_meta('_pcs_exchange_rate_time')) . '</small>';
        }

        // 显示支付货币回退信息
        $is_fallback = $order->get_meta('_pcs_payment_fallback');
        if ($is_fallback) {
            $payment_currency = $order->get_meta('_pcs_payment_currency');
            $display_currency = $order->get_meta('_pcs_display_currency');
            $fallback_rate = $order->get_meta('_pcs_payment_fallback_rate');
            $payment_data = $currencies[$payment_currency] ?? [];
            echo '<br><span style="color:#e67e22;">⚠ ' . esc_html__('Payment Settlement', 'pro-currency-switcher') . ': ';
            echo esc_html($payment_data['flag'] ?? '') . ' ' . esc_html($payment_currency);
            echo ' (' . sprintf(esc_html__('%s not supported by payment gateway, settled in %s', 'pro-currency-switcher'), $display_currency, $payment_currency) . ')</span>';
        }

        echo '</div>';
    }

    /**
     * 转换购物车总计显示
     * WooCommerce 内部已经返回了正确货币的金额，只需重新格式化
     */
    public function convert_cart_total($formatted_total) {
        $service = CurrencyService::get_instance();
        $current = $service->get_current_currency();
        $base = $service->get_base_currency();

        if ($current === $base) return $formatted_total;

        // 提取数字并重新格式化（不重复转换汇率）
        $price = floatval(preg_replace('/[^\d.]/', '', $formatted_total));
        $formatted_total = $service->format_price($price, $current);

        $formatted_total = apply_filters('pcs_cart_total', $formatted_total);
        return $formatted_total;
    }

    /**
     * 转换订单总计显示
     */
    public function convert_order_total_display($formatted_total, $order) {
        $order_currency = $order->get_meta('_pcs_order_currency');
        if (empty($order_currency)) return $formatted_total;

        $service = CurrencyService::get_instance();
        return $service->format_price(floatval(preg_replace('/[^\d.]/', '', $formatted_total)), $order_currency);
    }

    /**
     * 锁定结账货币（防止结账过程中切换货币）
     */
    public function lock_checkout_currency() {
        $service = CurrencyService::get_instance();
        $current = $service->get_current_currency();

        if (function_exists('WC') && WC()->session) {
            WC()->session->set('pcs_checkout_currency', $current);
        }
    }

    /**
     * 支付网关货币回退：在创建订单时检查并转换
     * 
     * 当用户选择的货币不被支付网关支持时（如 PayPal 不支持 CNY），
     * 在创建订单时将所有金额从显示货币转回基准货币（USD）。
     * 
     * 这种方式不影响页面渲染（用户始终看到自己选择的货币），
     * 只在最终提交支付时才做转换，避免 WC Block 前后端不一致。
     */
    public function fallback_order_to_base_currency($order, $data) {
        $service = CurrencyService::get_instance();
        $display_currency = $service->get_current_currency();
        $base_currency = $service->get_base_currency();

        // 已经是基准货币，不需要回退
        if ($display_currency === $base_currency) {
            return;
        }

        // 检查支付网关是否支持当前货币
        $needs_fallback = $this->check_gateway_currency_support($display_currency, $base_currency);
        if (!$needs_fallback) {
            return;
        }

        // 获取汇率（显示货币 → 基准货币）
        $rate = $service->get_exchange_rate($base_currency, $display_currency);
        if ($rate <= 0) {
            return;
        }

        // 将订单金额从显示货币转回基准货币
        $reverse_rate = 1.0 / $rate;

        // 转换订单总额
        $total = $order->get_total();
        if ($total > 0) {
            $order->set_total(round(floatval($total) * $reverse_rate, 2));
        }

        // 转换订单各项金额
        foreach (['subtotal', 'discount_total', 'shipping_total', 'cart_tax', 'shipping_tax', 'total_tax'] as $key) {
            $getter = 'get_' . $key;
            $setter = 'set_' . $key;
            if (method_exists($order, $getter) && method_exists($order, $setter)) {
                $val = $order->$getter();
                if ($val > 0) {
                    $order->$setter(round(floatval($val) * $reverse_rate, 2));
                }
            }
        }

        // 设置订单货币为基准货币
        $order->set_currency($base_currency);

        // 记录回退信息
        $order->update_meta_data('_pcs_payment_currency', $base_currency);
        $order->update_meta_data('_pcs_display_currency', $display_currency);
        $order->update_meta_data('_pcs_payment_fallback_rate', $rate);
        $order->update_meta_data('_pcs_payment_fallback', true);

        // 保存（WC 后续会再次 save）
        $order->save();
    }

    /**
     * 检查支付网关是否支持当前货币
     * @return bool true = 需要回退到基准货币
     */
    private function check_gateway_currency_support($currency, $base_currency) {
        $available_gateways = [];
        if (isset(WC()->payment_gateways) && WC()->payment_gateways) {
            try {
                $gateways = WC()->payment_gateways->get_available_payment_gateways();
                if (is_array($gateways)) {
                    $available_gateways = $gateways;
                }
            } catch (\Throwable $e) {
                return false;
            }
        }

        if (empty($available_gateways)) {
            return false;
        }

        foreach ($available_gateways as $gateway_id => $gateway) {
            $supported = self::$gateway_supported_currencies[$gateway_id] ?? null;
            if ($supported !== null && !in_array($currency, $supported, true)) {
                return true; // 有网关不支持，需要回退
            }
        }

        return false; // 所有网关都支持
    }

    /**
     * 邮件中显示货币信息
     */
    public function show_currency_in_email($table, $order) {
        $currency = $order->get_meta('_pcs_order_currency');
        if (empty($currency)) return $table;

        $currencies = pcs_get_all_currencies_data();
        $data = $currencies[$currency] ?? [];

        $table .= '<div style="margin:10px 0;padding:8px;background:#f0f0f0;border-radius:4px;font-size:12px;">';
        $table .= sprintf(
            esc_html__('Order Currency: %s %s (%s)', 'pro-currency-switcher'),
            esc_html($data['flag'] ?? ''),
            esc_html($currency),
            esc_html($data['name'] ?? $currency)
        );
        $table .= '</div>';

        return $table;
    }
}
