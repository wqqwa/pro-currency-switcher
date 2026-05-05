<?php
/**
 * WooCross Currency Switcher - Helper functions
 * 
 * @package ProCurrencySwitcher
 */

// 防止直接访问
if (!defined('ABSPATH')) {
    exit;
}

/**
 * 获取所有货币数据
 * 根据 locale 自动切换货币名称语言（中文/英文）
 */
function pcs_get_all_currencies_data() {
    static $currencies = null;

    if (null === $currencies) {
        // 修复：使用 require 而非 require_once，static变量已防止重复加载
        $raw_currencies = require PCS_PLUGIN_PATH . 'includes/currency-data.php';

        // 检测当前 locale 是否为中文环境
        $locale = determine_locale();
        $is_zh = (strpos($locale, 'zh_') === 0);

        // 根据 locale 处理货币名称
        foreach ($raw_currencies as $code => &$data) {
            if ($is_zh && isset($data['name_zh'])) {
                $data['name'] = $data['name_zh'];
            }
            unset($data['name_zh']); // 从返回数据中移除 name_zh 字段，保持接口一致
        }

        $currencies = $raw_currencies;
    }

    return $currencies;
}

/**
 * 获取启用的货币列表
 */
function pcs_get_enabled_currencies() {
    $enabled = get_option('pcs_enabled_currencies', []);
    if (!is_array($enabled)) { $enabled = []; }
    $base_currency = 'USD';
    
    // 确保基准货币始终包含在内
    if (!in_array($base_currency, $enabled)) {
        $enabled[] = $base_currency;
    }
    
    return array_unique($enabled);
}

/**
 * 检查货币是否启用
 */
function pcs_is_currency_enabled($currency_code) {
    $enabled = pcs_get_enabled_currencies();
    return in_array(strtoupper($currency_code), $enabled);
}

/**
 * 获取基准货币
 */
function pcs_get_base_currency() {
    return 'USD';
}

/**
 * 转换价格
 */
function pcs_convert_price($price, $from_currency, $to_currency) {
    if ($from_currency === $to_currency) {
        return $price;
    }
    
    // 获取汇率数据
    $exchange_rates = pcs_get_exchange_rates();
    
    // 转换为美元
    // 修复：添加 isset 检查，防止汇率不存在时产生警告
    if (!isset($exchange_rates[$from_currency]) || !isset($exchange_rates[$to_currency])) {
        return $price; // 汇率数据缺失时返回原价
    }
    $usd_amount = $price / $exchange_rates[$from_currency];
    
    // 转换为目标货币
    return $usd_amount * $exchange_rates[$to_currency];
}

/**
 * 获取汇率数据（免费版：从数据库读取，有过期检查）
 * 过期汇率不返回（视为无汇率），调用方会回退到1.0
 */
function pcs_get_exchange_rates() {
    global $wpdb;
    $base_currency = get_option('pcs_base_currency', 'USD');
    $table_name = $wpdb->prefix . 'pcs_exchange_rates';

    $results = $wpdb->get_results($wpdb->prepare(
        "SELECT target_currency, exchange_rate, rate_source, expires_at FROM {$table_name} WHERE base_currency = %s",
        $base_currency
    ));

    $rates = [$base_currency => 1.0];
    if ($results) {
        $no_expiry = apply_filters('pcs_rate_no_expiry', false);
        foreach ($results as $row) {
            // 免费版：检查过期
            if (!$no_expiry && !empty($row->expires_at) && strtotime($row->expires_at) < time()) {
                continue; // 跳过已过期的汇率
            }
            $rates[$row->target_currency] = floatval($row->exchange_rate);
        }
    }

    return $rates;
}

/**
 * 格式化货币价格
 */
function pcs_format_price($price, $currency_code) {
    $currencies = pcs_get_all_currencies_data();
    $currency = $currencies[$currency_code] ?? $currencies['USD'];
    
    $formatted = number_format(
        $price,
        $currency['decimals'],
        $currency['decimal_separator'],
        $currency['thousands_separator']
    );
    
    if ($currency['symbol_position'] === 'left') {
        return $currency['symbol'] . $formatted;
    } else {
        return $formatted . $currency['symbol'];
    }
}

/**
 * 记录货币使用情况
 */
function pcs_log_currency_usage($currency_code) {
    global $wpdb;
    
    // 检查表是否存在
    $table_name = $wpdb->prefix . 'pcs_currency_usage';
    if ($wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table_name)) !== $table_name) {
        return false; // 表不存在，不记录
    }
    
    $session_id = session_id();
    // 修复：使用 filter_var 验证IP地址格式，防止注入攻击
    $ip_address = filter_var($_SERVER['REMOTE_ADDR'] ?? '', FILTER_VALIDATE_IP) ?: '';
    $country_code = ''; // 免费版不支持GeoIP检测
    
    $result = $wpdb->insert(
        $table_name,
        [
            'currency_code' => $currency_code,
            'session_id' => $session_id,
            'ip_address' => $ip_address,
            'country_code' => $country_code
        ],
        ['%s', '%s', '%s', '%s']
    );
    
    return $result !== false;
}

/**
 * 清除相关缓存
 */
function pcs_clear_related_caches() {
    // 清除价格缓存
    delete_transient('pcs_exchange_rates');
    delete_transient('pcs_displayable_currencies');
    
    // 清除WooCommerce相关缓存
    if (function_exists('wc_delete_product_transients')) {
        wc_delete_product_transients();
    }
    
    // 清除地理定位缓存
    if (function_exists('WC') && WC()->session) {
        WC()->session->__unset('visitor_geo_info');
    }
}

/**
 * 获取货币使用统计
 */
function pcs_get_currency_usage_stats($days = 30) {
    global $wpdb;
    
    $table_name = $wpdb->prefix . 'pcs_currency_usage';
    $date_limit = date('Y-m-d H:i:s', strtotime("-$days days"));
    
    $results = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT currency_code, COUNT(*) as usage_count 
             FROM $table_name 
             WHERE created_at >= %s 
             GROUP BY currency_code 
             ORDER BY usage_count DESC",
            $date_limit
        )
    );
    
    $stats = [];
    foreach ($results as $row) {
        $stats[$row->currency_code] = $row->usage_count;
    }
    
    return $stats;
}