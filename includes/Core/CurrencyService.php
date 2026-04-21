<?php
/**
 * 统一货币服务层（免费版）
 * 整合汇率获取、价格转换、缓存管理、货币切换等基础操作
 *
 * 免费版功能：基础汇率转换、手动汇率、GeoIP自动检测、货币检测（Cookie/URL）、价格格式化
 *
 * @package ProCurrencySwitcher
 * @since 1.0.0
 * @version 7.5.0-free
 */

namespace ProCurrencySwitcher\Core;

if (!defined('ABSPATH')) {
    exit;
}

class CurrencyService {

    /** @var CurrencyService 单例实例 */
    private static $instance = null;

    /** @var string 当前货币 */
    private $current_currency;

    /** @var string 基准货币 */
    private $base_currency;

    /** @var array 启用的货币列表 */
    private $enabled_currencies;

    /** @var array 汇率缓存 */
    private $rates_cache = [];

    /**
     * 获取单例实例
     */
    public static function get_instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        $this->base_currency = get_option('pcs_base_currency', 'USD');
        $this->enabled_currencies = get_option('pcs_enabled_currencies', ['USD', 'EUR', 'GBP', 'JPY', 'CNY']);
        if (!is_array($this->enabled_currencies)) {
            $this->enabled_currencies = ['USD', 'EUR', 'GBP', 'JPY', 'CNY'];
        }
        $this->current_currency = $this->detect_current_currency();

        // 核心：让 WooCommerce 内部也使用当前选择的货币
        // 这样结账、支付网关、订单金额都会使用正确的货币
        add_filter('woocommerce_currency', [$this, 'filter_woocommerce_currency']);
    }

    /**
     * 过滤 WooCommerce 货币设置
     * 让 WC 内部计算（结账、支付、订单）使用用户选择的货币
     * 后台管理员页面不受影响，始终使用基准货币
     */
    public function filter_woocommerce_currency($default_currency) {
        // 后台（非 AJAX）保持基准货币，避免管理员看到转换后的价格
        if (is_admin() && !wp_doing_ajax()) {
            return $default_currency;
        }
        // REST API 请求：仅后台编辑器（/wp/v2/）保持基准货币
        // 前端 Store API（/wc/store/）需要使用用户选择的货币，否则支付金额会错误
        if (defined('REST_REQUEST') && REST_REQUEST) {
            $request_uri = $_SERVER['REQUEST_URI'] ?? '';
            if (preg_match('#/wp-json/wp/v#i', $request_uri)) {
                return $default_currency;
            }
            // 其他 REST 请求（包括 /wc/store/checkout 支付提交）使用用户选择的货币
            return $this->current_currency;
        }
        return $this->current_currency;
    }

    /**
     * 检测当前货币（优先级：GET参数 > GeoIP > Cookie > Session > 默认）
     * 缓存兼容：语言通过Cookie实现，货币以IP地理定位为准
     *
     * 安全机制：授权过期时禁用GeoIP，汇率过期时跳过Cookie/Session检测，直接返回基准货币
     */
    private function detect_current_currency() {
        $currency = null;

        // 检查授权状态（授权过期时禁用GeoIP自动检测）
        $license_active = $this->is_license_active();

        // 1. GET参数（URL手动切换，最高优先级）
        if (isset($_GET['currency'])) {
            $currency = sanitize_text_field(wp_unslash($_GET['currency']));
            if ($this->is_currency_enabled($currency)) {
                $this->set_currency_cookie($currency);
                return $currency;
            }
        }

        // 2. GeoIP地理定位自动检测（需要授权有效 + 后台开启）
        if ($license_active && get_option('pcs_auto_detect', 'no') === 'yes') {
            $geo_currency = $this->detect_by_geolocation();
            if ($geo_currency && $this->is_currency_enabled($geo_currency) && $this->is_rate_available($geo_currency)) {
                $this->set_currency_cookie($geo_currency);
                return $geo_currency;
            }
        }

        // 3. Cookie（备选，当GeoIP未开启或检测失败时使用）
        if (isset($_COOKIE['pcs_currency'])) {
            $currency = sanitize_text_field(wp_unslash($_COOKIE['pcs_currency']));
            if ($this->is_currency_enabled($currency) && $this->is_rate_available($currency)) {
                return $currency;
            }
        }

        // 4. WooCommerce Session（如果可用）
        if (function_exists('WC') && WC()->session) {
            $wc_currency = WC()->session->get('pcs_currency');
            if ($wc_currency && $this->is_currency_enabled($wc_currency) && $this->is_rate_available($wc_currency)) {
                return $wc_currency;
            }
        }

        // 5. 开发者钩子：允许修改检测到的货币
        $detected = apply_filters('pcs_detected_currency', $currency ?? $this->base_currency, $this->base_currency);
        if ($detected && $this->is_currency_enabled($detected) && $this->is_rate_available($detected)) {
            return $detected;
        }

        // 6. 默认基准货币
        return $this->base_currency;
    }

    /**
     * 检查授权是否有效
     * 授权过期时禁用GeoIP自动检测功能
     */
    private function is_license_active(): bool {
        if (class_exists(\ProCurrencySwitcher\License\LicenseManager::class)) {
            return \ProCurrencySwitcher\License\LicenseManager::get_instance()->is_active();
        }
        return true; // 无LicenseManager时默认允许
    }

    /**
     * 检查指定货币的汇率是否可用（未过期）
     * 当汇率过期或不存在时返回false，防止显示错误的货币和价格
     */
    private function is_rate_available($target_currency): bool {
        // 基准货币始终可用
        if ($target_currency === $this->base_currency) {
            return true;
        }

        // 从数据库检查汇率是否存在且未过期
        global $wpdb;
        $row = $wpdb->get_row($wpdb->prepare(
            "SELECT exchange_rate, expires_at FROM {$wpdb->prefix}pcs_exchange_rates WHERE base_currency = %s AND target_currency = %s",
            $this->base_currency, $target_currency
        ));

        if (!$row) {
            return false;
        }

        // 检查汇率是否有效（大于0且不等于1，除非基准货币和目标货币汇率确实为1）
        $rate = floatval($row->exchange_rate);
        if ($rate <= 0) {
            return false;
        }

        // 检查是否过期
        if (!empty($row->expires_at) && strtotime($row->expires_at) < time()) {
            // 允许通过过滤器跳过过期检查（专业版使用）
            return apply_filters('pcs_rate_no_expiry', false, $this->base_currency, $target_currency);
        }

        return true;
    }

    /**
     * 通过地理定位检测货币
     */
    private function detect_by_geolocation() {
        // Cloudflare头（最快）
        if (!empty($_SERVER['HTTP_CF_IPCOUNTRY'])) {
            $country = sanitize_text_field(wp_unslash($_SERVER['HTTP_CF_IPCOUNTRY']));
            return $this->country_to_currency($country);
        }

        // 缓存结果
        $ip = $this->get_client_ip();
        $cache_key = 'pcs_geo_' . md5($ip);
        $cached = get_transient($cache_key);
        if ($cached !== false) {
            return $cached;
        }

        // 调用地理定位API
        $country = '';
        $api_url = 'https://ipapi.co/' . $ip . '/json/';
        $response = wp_remote_get($api_url, ['timeout' => 3]);

        if (!is_wp_error($response)) {
            $data = json_decode(wp_remote_retrieve_body($response), true);
            if ($data && isset($data['country_code'])) {
                $country = sanitize_text_field($data['country_code']);
            }
        }

        if (empty($country)) {
            // 备用API
            $response = wp_remote_get('http://ip-api.com/json/' . $ip, ['timeout' => 3]);
            if (!is_wp_error($response)) {
                $data = json_decode(wp_remote_retrieve_body($response), true);
                if ($data && isset($data['countryCode'])) {
                    $country = sanitize_text_field($data['countryCode']);
                }
            }
        }

        $currency = $this->country_to_currency($country);
        set_transient($cache_key, $currency, HOUR_IN_SECONDS);
        return $currency;
    }

    /**
     * 国家代码转货币代码
     */
    private function country_to_currency($country_code) {
        $map = [
            'US' => 'USD', 'CA' => 'CAD', 'GB' => 'GBP', 'EU' => 'EUR', 'DE' => 'EUR',
            'FR' => 'EUR', 'IT' => 'EUR', 'ES' => 'EUR', 'NL' => 'EUR', 'BE' => 'EUR',
            'AT' => 'EUR', 'PT' => 'EUR', 'IE' => 'EUR', 'GR' => 'EUR', 'FI' => 'EUR',
            'JP' => 'JPY', 'CN' => 'CNY', 'KR' => 'KRW', 'IN' => 'INR', 'AU' => 'AUD',
            'NZ' => 'NZD', 'SG' => 'SGD', 'MY' => 'MYR', 'TH' => 'THB', 'VN' => 'VND',
            'ID' => 'IDR', 'PH' => 'PHP', 'TW' => 'TWD', 'HK' => 'HKD', 'MO' => 'MOP',
            'CH' => 'CHF', 'SE' => 'SEK', 'NO' => 'NOK', 'DK' => 'DKK', 'PL' => 'PLN',
            'CZ' => 'CZK', 'HU' => 'HUF', 'RO' => 'RON', 'BR' => 'BRL', 'MX' => 'MXN',
            'AR' => 'ARS', 'CL' => 'CLP', 'CO' => 'COP', 'AE' => 'AED', 'SA' => 'SAR',
            'ZA' => 'ZAR', 'EG' => 'EGP', 'NG' => 'NGN', 'KE' => 'KES', 'RU' => 'RUB',
            'TR' => 'TRY', 'IL' => 'ILS', 'QA' => 'QAR', 'KW' => 'KWD', 'BH' => 'BHD',
            'OM' => 'OMR', 'LB' => 'LBP', 'JO' => 'JOD', 'LA' => 'LAK', 'KH' => 'KHR',
            'MM' => 'MMK', 'BD' => 'BDT', 'PK' => 'PKR', 'LK' => 'LKR', 'NP' => 'NPR',
        ];
        return apply_filters('pcs_country_currency', $map[strtoupper($country_code)] ?? '', $country_code);
    }

    /**
     * 获取客户端IP
     */
    private function get_client_ip() {
        $ip_keys = ['HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'REMOTE_ADDR'];
        foreach ($ip_keys as $key) {
            if (!empty($_SERVER[$key])) {
                $ip = explode(',', $_SERVER[$key])[0];
                $ip = trim($ip);
                if (filter_var($ip, FILTER_VALIDATE_IP)) {
                    return $ip;
                }
            }
        }
        return '0.0.0.0';
    }

    /**
     * 设置货币Cookie（缓存兼容）
     */
    private function set_currency_cookie($currency) {
        setcookie('pcs_currency', $currency, [
            'expires' => time() + (30 * DAY_IN_SECONDS),
            'path' => '/',
            'domain' => '',
            'secure' => is_ssl(),
            'httponly' => false,
            'samesite' => 'Lax',
        ]);
        $_COOKIE['pcs_currency'] = $currency;

        // 同步到WooCommerce Session
        if (function_exists('WC') && WC()->session) {
            try {
                WC()->session->set('pcs_currency', $currency);
            } catch (Exception $e) {}
        }
    }

    // ==================== 公共API ====================

    /**
     * 获取当前货币
     */
    public function get_current_currency() {
        return $this->current_currency;
    }

    /**
     * 获取基准货币
     */
    public function get_base_currency() {
        return $this->base_currency;
    }

    /**
     * 获取启用的货币列表
     */
    public function get_enabled_currencies() {
        return apply_filters('pcs_enabled_currencies', $this->enabled_currencies);
    }

    /**
     * 切换货币
     */
    public function switch_currency($currency_code) {
        $currency_code = sanitize_text_field($currency_code);

        if (!$this->is_currency_enabled($currency_code)) {
            return false;
        }

        $this->current_currency = $currency_code;
        $this->set_currency_cookie($currency_code);

        do_action('pcs_currency_changed', $currency_code, $this->current_currency);

        return true;
    }

    /**
     * 检查货币是否启用
     */
    public function is_currency_enabled($currency_code) {
        return in_array($currency_code, $this->enabled_currencies);
    }

    /**
     * 获取汇率（免费版：仅从数据库获取，有过期检查）
     * 过期逻辑：免费版汇率30小时后过期，过期返回1.0
     * 专业版：通过 pcs_rate_no_expiry 过滤器跳过过期检查
     */
    public function get_exchange_rate($from, $to) {
        if ($from === $to) {
            return 1.0;
        }

        $cache_key = "{$from}_{$to}";
        if (isset($this->rates_cache[$cache_key])) {
            return $this->rates_cache[$cache_key];
        }

        // 从数据库获取汇率（含过期信息）
        $db_rate = $this->get_database_rate_with_expiry($from, $to);
        if ($db_rate !== null) {
            // 检查是否过期（专业版可通过过滤器跳过）
            $no_expiry = apply_filters('pcs_rate_no_expiry', false, $from, $to);
            if (!$no_expiry && !empty($db_rate['expires_at']) && strtotime($db_rate['expires_at']) < time()) {
                // 汇率已过期，返回1.0（即基础货币原价）
                $this->rates_cache[$cache_key] = 1.0;
                return apply_filters('pcs_exchange_rate_fallback', 1.0, $from, $to);
            }

            $rate = floatval($db_rate['exchange_rate']);

            // 汇率合理性检查：防止异常汇率导致价格错误
            // 正常汇率范围：0.0001 ~ 100000（覆盖 VND ~25000、IDR ~16000 等高面额货币）
            if ($rate <= 0 || $rate > 100000) {
                error_log(sprintf('[PCS] 异常汇率被拒绝: %s→%s = %s (来源: %s)', $from, $to, $rate, $db_rate['rate_source'] ?? 'unknown'));
                $this->rates_cache[$cache_key] = 1.0;
                return apply_filters('pcs_exchange_rate_fallback', 1.0, $from, $to);
            }

            $rate = apply_filters('pcs_exchange_rate', $rate, $from, $to, $db_rate['rate_source'] ?? 'manual');
            $this->rates_cache[$cache_key] = $rate;
            return $rate;
        }

        $this->rates_cache[$cache_key] = 1.0;
        return apply_filters('pcs_exchange_rate_fallback', 1.0, $from, $to);
    }

    /**
     * 获取所有汇率（相对于基准货币）
     */
    public function get_all_rates() {
        $rates = [$this->base_currency => 1.0];

        foreach ($this->enabled_currencies as $currency) {
            if ($currency !== $this->base_currency) {
                $rates[$currency] = $this->get_exchange_rate($this->base_currency, $currency);
            }
        }

        return $rates;
    }

    /**
     * 转换价格（免费版：仅基础汇率转换，不应用取整规则）
     */
    public function convert_price($price, $from = null, $to = null) {
        if ($from === null) {
            $from = $this->base_currency;
        }
        if ($to === null) {
            $to = $this->current_currency;
        }

        if ($from === $to || $price <= 0) {
            return $price;
        }

        $rate = $this->get_exchange_rate($from, $to);
        $converted = $price * $rate;

        $converted = apply_filters('pcs_converted_price', $converted, $price, $from, $to);

        return $converted;
    }

    /**
     * 格式化价格（统一入口）
     */
    public function format_price($price, $currency = null) {
        if ($currency === null) {
            $currency = $this->current_currency;
        }

        $currencies = pcs_get_all_currencies_data();
        $data = $currencies[$currency] ?? [];

        $symbol = $data['symbol'] ?? $currency;
        $decimals = $data['decimals'] ?? 2;
        $dec_sep = $data['decimal_separator'] ?? '.';
        $thou_sep = $data['thousands_separator'] ?? ',';
        $position = $data['symbol_position'] ?? 'left';

        $amount = number_format($price, $decimals, $dec_sep, $thou_sep);

        if ($position === 'right') {
            $html = '<span class="pcs-converted-price"><span class="pcs-price-amount">' . esc_html($amount) . '</span><span class="pcs-currency-symbol"> ' . esc_html($symbol) . '</span></span>';
        } elseif ($position === 'left_space') {
            $html = '<span class="pcs-converted-price"><span class="pcs-currency-symbol">' . esc_html($symbol) . ' </span><span class="pcs-price-amount">' . esc_html($amount) . '</span></span>';
        } else {
            $html = '<span class="pcs-converted-price"><span class="pcs-currency-symbol">' . esc_html($symbol) . '</span><span class="pcs-price-amount">' . esc_html($amount) . '</span></span>';
        }

        return apply_filters('pcs_formatted_price', $html, $price, $currency);
    }

    /**
     * 转换并格式化价格
     */
    public function convert_and_format($price, $from = null, $to = null) {
        $converted = $this->convert_price($price, $from, $to);
        return $this->format_price($converted, $to);
    }

    // ==================== 数据库汇率 ====================

    /**
     * 获取数据库汇率（仅返回数值，兼容旧调用）
     */
    private function get_database_rate($from, $to) {
        $result = $this->get_database_rate_with_expiry($from, $to);
        return $result ? floatval($result['exchange_rate']) : null;
    }

    /**
     * 获取数据库汇率（含过期信息和来源）
     */
    private function get_database_rate_with_expiry($from, $to) {
        global $wpdb;
        $row = $wpdb->get_row($wpdb->prepare(
            "SELECT exchange_rate, rate_source, expires_at FROM {$wpdb->prefix}pcs_exchange_rates WHERE base_currency = %s AND target_currency = %s",
            $from, $to
        ));
        if ($row) {
            return [
                'exchange_rate' => $row->exchange_rate,
                'rate_source'   => $row->rate_source ?? 'manual',
                'expires_at'    => $row->expires_at,
            ];
        }
        return null;
    }

    // ==================== 缓存管理 ====================

    /**
     * 清除所有缓存
     */
    public function clear_all_cache() {
        do_action('pcs_before_cache_clear');

        delete_transient('pcs_exchange_rates');
        delete_transient('pcs_displayable_currencies');
        $this->rates_cache = [];

        // 清除WooCommerce缓存
        if (function_exists('WC') && WC()->session) {
            try {
                WC()->session->__unset('pcs_currency');
            } catch (Exception $e) {}
        }

        if (function_exists('wp_cache_flush_group')) {
            wp_cache_flush_group('pcs');
        }

        do_action('pcs_after_cache_clear');
    }

    /**
     * 清除汇率缓存
     */
    public function clear_rates_cache() {
        delete_transient('pcs_exchange_rates');
        $this->rates_cache = [];
    }
}
