<?php
/**
 * 缓存兼容处理器
 * 确保在页面缓存（WP Super Cache、W3 Total Cache、CDN等）环境下货币切换正常工作
 *
 * 核心策略：
 * 1. 通过Cookie存储用户货币选择
 * 2. 通过JavaScript在客户端动态更新价格（不依赖服务端渲染）
 * 3. 通过Late Initialization在缓存页面加载后重新检测货币
 *
 * @package ProCurrencySwitcher
 * @since 5.0.0
 */

namespace ProCurrencySwitcher\Core;

use ProCurrencySwitcher\Core\CurrencyService;

if (!defined('ABSPATH')) {
    exit;
}

class CacheCompatHandler {

    /**
     * 初始化缓存兼容处理
     */
    public function __construct() {
        // 前端：注入货币检测和价格更新脚本
        add_action('wp_enqueue_scripts', [$this, 'enqueue_compat_scripts'], 99);

        // AJAX端点：获取当前货币的价格数据
        add_action('wp_ajax_pcs_get_prices', [$this, 'ajax_get_prices']);
        add_action('wp_ajax_nopriv_pcs_get_prices', [$this, 'ajax_get_prices']);

        // REST API端点（供前端JS调用）
        add_action('rest_api_init', [$this, 'register_rest_endpoint']);

        // 缓存插件兼容：告诉缓存插件按Cookie区分缓存
        add_filter('wpsc_supercache_filename', [$this, 'vary_cache_by_currency']);
        add_action('init', [$this, 'send_vary_header']);
    }

    /**
     * 发送Vary头（CDN兼容）
     */
    public function send_vary_header() {
        if (!is_admin()) {
            header('Vary: Cookie');
        }
    }

    /**
     * WP Super Cache兼容：按货币Cookie区分缓存
     */
    public function vary_cache_by_currency($filename) {
        $currency = isset($_COOKIE['pcs_currency']) ? sanitize_text_field($_COOKIE['pcs_currency']) : 'USD';
        return $filename . '-' . $currency;
    }

    /**
     * 注入缓存兼容脚本
     * 
     * 仅在页面缓存环境下启用（通过检测是否为缓存页面）。
     * 当 PHP filter 正常执行时（非缓存页面），价格已在服务端转换，
     * 不需要 JS 再次转换，否则会导致双重转换。
     */
    public function enqueue_compat_scripts() {
        if (is_admin()) {
            return;
        }

        try {
            $service = CurrencyService::get_instance();
            $base_currency = $service->get_base_currency();
            $current_currency = $service->get_current_currency();
            $enabled_currencies = $service->get_enabled_currencies();
        } catch (Exception $e) {
            return;
        }

        // 如果当前就是基准货币，不需要动态更新
        if ($current_currency === $base_currency) {
            return;
        }

        // 检测是否为页面缓存环境
        // 如果 woocommerce_currency filter 正常生效，说明是动态页面，不需要 JS 转换
        // 只有在缓存页面（PHP filter 未执行）时才需要 JS 补充转换
        $is_cached_page = apply_filters('pcs_is_cached_page', false);
        if (!$is_cached_page) {
            return;
        }

        $inline_js = $this->generate_price_update_script($base_currency, $current_currency);
        wp_add_inline_script('pcs-frontend', $inline_js, 'after');
    }

    /**
     * 生成客户端价格更新脚本
     * 在缓存页面加载后，通过AJAX获取当前货币的价格并动态替换
     */
    private function generate_price_update_script($base_currency, $current_currency) {
        $ajax_url = admin_url('admin-ajax.php');
        $nonce = wp_create_nonce('pcs_nonce');
        $rest_url = rest_url('pcs/v1/prices');

        return "
        (function() {
            'use strict';

            // 检查Cookie中的货币是否与页面显示的货币一致
            var cookieCurrency = document.cookie.match(/pcs_currency=([^;]+)/);
            cookieCurrency = cookieCurrency ? cookieCurrency[1] : '{$base_currency}';

            if (cookieCurrency === '{$base_currency}') {
                return; // 基准货币，无需更新
            }

            // 收集页面上的价格元素
            function collectPriceElements() {
                var prices = [];
                // WooCommerce价格元素
                document.querySelectorAll('.price, .woocommerce-Price-amount, [data-price], .product_price, .cart-item-price, .order-total, .cart-subtotal').forEach(function(el) {
                    var text = el.textContent || el.innerText;
                    var match = text.match(/[\d,]+\.?\d*/);
                    if (match) {
                        prices.push({
                            element: el,
                            originalText: text,
                            priceValue: parseFloat(match[0].replace(/,/g, ''))
                        });
                    }
                });
                return prices;
            }

            // 通过AJAX获取转换后的价格
            function fetchConvertedPrices(prices) {
                var priceValues = prices.map(function(p) { return p.priceValue; });

                fetch('{$ajax_url}', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: 'action=pcs_get_prices&nonce={$nonce}&prices=' + encodeURIComponent(JSON.stringify(priceValues)) + '&from={$base_currency}&to=' + cookieCurrency
                })
                .then(function(response) { return response.json(); })
                .then(function(data) {
                    if (data.success && data.data && data.data.prices) {
                        updatePriceElements(prices, data.data.prices, data.data.currency);
                    }
                })
                .catch(function(err) {
                    console.warn('PCS price update failed:', err);
                });
            }

            // 更新价格元素
            function updatePriceElements(prices, convertedPrices, currency) {
                prices.forEach(function(item, index) {
                    if (convertedPrices[index] !== undefined) {
                        var formatted = convertedPrices[index];
                        var span = document.createElement('span');
                        span.className = 'pcs-converted-price';
                        span.style.fontWeight = '700';
                        span.innerHTML = formatted;
                        item.element.innerHTML = '';
                        item.element.appendChild(span);
                    }
                });

                // 更新页面上的货币显示
                document.querySelectorAll('.pcs-currency-display, .current-currency').forEach(function(el) {
                    el.textContent = currency;
                });

                // 更新WooCommerce购物车碎片
                if (typeof jQuery !== 'undefined' && typeof jQuery.fn.wc_fragment_refresh === 'function') {
                    jQuery(document.body).trigger('wc_fragment_refresh');
                }
            }

            // DOM加载完成后执行
            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', function() {
                    var prices = collectPriceElements();
                    if (prices.length > 0) {
                        fetchConvertedPrices(prices);
                    }
                });
            } else {
                var prices = collectPriceElements();
                if (prices.length > 0) {
                    fetchConvertedPrices(prices);
                }
            }
        })();
        ";
    }

    /**
     * AJAX获取转换后的价格
     */
    public function ajax_get_prices() {
        check_ajax_referer('pcs_nonce', 'nonce');

        $prices = json_decode(wp_unslash($_POST['prices'] ?? '[]'), true);
        $from = sanitize_text_field($_POST['from'] ?? 'USD');
        $to = sanitize_text_field($_POST['to'] ?? 'USD');

        if (!is_array($prices) || empty($prices)) {
            wp_send_json_error(__('Invalid price data', 'pro-currency-switcher'));
        }

        $service = CurrencyService::get_instance();
        $formatted = [];

        foreach ($prices as $price) {
            $price = floatval($price);
            $converted = $service->convert_price($price, $from, $to);
            $formatted[] = $service->format_price($converted, $to);
        }

        $formatted = apply_filters('pcs_ajax_converted_prices', $formatted, $prices, $from, $to);

        wp_send_json_success([
            'prices' => $formatted,
            'currency' => $to,
        ]);
    }

    /**
     * 注册REST API端点
     */
    public function register_rest_endpoint() {
        register_rest_route('pcs/v1', '/prices', [
            'methods' => 'POST',
            'callback' => [$this, 'rest_get_prices'],
            'permission_callback' => [$this, 'rest_rate_limit_check'],
        ]);

        register_rest_route('pcs/v1', '/currencies', [
            'methods' => 'GET',
            'callback' => [$this, 'rest_get_currencies'],
            'permission_callback' => '__return_true',
        ]);
    }

    /**
     * REST API 速率限制检查
     */
    public function rest_rate_limit_check(): bool {
        $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        $key = 'pcs_rest_rate_' . md5($ip);
        $count = intval(get_transient($key));
        if ($count >= 60) {
            return false;
        }
        set_transient($key, $count + 1, 60);
        return true;
    }

    /**
     * REST API获取价格
     */
    public function rest_get_prices($request) {
        $params = $request->get_json_params();
        $prices = $params['prices'] ?? [];
        $from = sanitize_text_field($params['from'] ?? 'USD');
        $to = sanitize_text_field($params['to'] ?? 'USD');

        $service = CurrencyService::get_instance();
        $formatted = [];

        foreach ($prices as $price) {
            $converted = $service->convert_price(floatval($price), $from, $to);
            $formatted[] = $service->format_price($converted, $to);
        }

        $formatted = apply_filters('pcs_ajax_converted_prices', $formatted, $prices, $from, $to);

        return rest_ensure_response([
            'prices' => $formatted,
            'currency' => $to,
        ]);
    }

    /**
     * REST API获取货币列表
     */
    public function rest_get_currencies() {
        $service = CurrencyService::get_instance();
        $currencies = pcs_get_all_currencies_data();
        $enabled = $service->get_enabled_currencies();
        $rates = $service->get_all_rates();

        $result = [];
        foreach ($enabled as $code) {
            $data = $currencies[$code] ?? [];
            $result[$code] = [
                'code' => $code,
                'name' => $data['name'] ?? $code,
                'symbol' => $data['symbol'] ?? $code,
                'flag' => $data['flag'] ?? '',
                'rate' => $rates[$code] ?? 1.0,
            ];
        }

        $result = apply_filters('pcs_rest_currencies', $result, $service);

        return rest_ensure_response([
            'base_currency' => $service->get_base_currency(),
            'current_currency' => $service->get_current_currency(),
            'currencies' => $result,
        ]);
    }
}
