<?php
/**
 * 授权验证管理器（插件端）
 *
 * 对接API接口：
 * - POST /api/v1/license/activate    激活授权码
 * - POST /api/v1/license/verify      验证授权码（每24h）
 * - POST /api/v1/license/deactivate  停用授权码
 * - POST /api/v1/plugin/check-update 检查付费版更新
 *
 * 安全机制：
 * - HMAC-SHA256请求签名
 * - 24小时本地缓存
 * - 7天服务器不可达宽限期
 * - 到期自动锁定付费功能
 *
 * @package ProCurrencySwitcher
 * @since 7.5.0
 */

namespace ProCurrencySwitcher\License;

if (!defined('ABSPATH')) {
    exit;
}

class LicenseManager {

    /** @var string API服务器地址 */
    private $api_url;

    /** @var string API密钥（用于HMAC签名） */
    private $api_secret;

    /** @var string 缓存key */
    const CACHE_KEY = 'pcs_license_cache';

    /** @var int 本地缓存有效期（秒）= 24小时 */
    const CACHE_TTL = 86400;

    /** @var int 服务器不可达宽限期（秒）= 7天 */
    const GRACE_PERIOD = 604800;

    /** @var LicenseManager 单例 */
    private static $instance = null;

    /** @var array|null 当前授权信息 */
    private $license_data = null;

    /** @var bool 是否有效授权 */
    private $is_active = false;

    /** @var string 当前站点域名 */
    private $domain = '';

    /**
     * 获取单例
     */
    public static function get_instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        $this->api_url = defined('PCS_API_URL')
            ? PCS_API_URL
            : 'https://hb.woocross.com/api/v1';

        // 安全修复：移除硬编码密钥，安装时自动生成唯一密钥
        $this->api_secret = defined('PCS_API_SECRET')
            ? PCS_API_SECRET
            : $this->ensure_api_secret();

        $this->domain = $this->get_site_domain();
        $this->load_cached_license();
        $this->check_license();
    }

    /**
     * 确保API密钥存在（安装时自动生成）
     */
    private function ensure_api_secret(): string {
        $secret = get_option('pcs_api_secret', '');
        if (empty($secret)) {
            $secret = wp_generate_password(64, true, true);
            update_option('pcs_api_secret', $secret, false);
        }
        return $secret;
    }

    /**
     * 获取API密钥（公共方法，供其他模块使用）
     */
    public function get_api_secret(): string {
        return $this->api_secret;
    }

    // ==================== 公共API ====================

    /**
     * 授权是否有效
     */
    public function is_active(): bool {
        return $this->is_active;
    }

    /**
     * 获取当前套餐类型
     */
    public function get_plan(): string {
        if (!$this->is_active || !$this->license_data) {
            return 'free';
        }
        return $this->license_data['plan'] ?? 'free';
    }

    /**
     * 获取套餐显示名称
     */
    public function get_plan_label(): string {
        $labels = [
            'pro'       => __('Pro', 'pro-currency-switcher'),
            'enterprise' => __('Enterprise', 'pro-currency-switcher'),
            'unlimited' => __('Unlimited', 'pro-currency-switcher'),
            'free'      => __('Free', 'pro-currency-switcher'),
        ];
        return $labels[$this->get_plan()] ?? __('Free', 'pro-currency-switcher');
    }

    /**
     * 获取到期时间
     */
    public function get_expires_at(): string {
        if (!$this->license_data) return '';
        return $this->license_data['expires_at'] ?? '';
    }

    /**
     * 获取授权码
     */
    public function get_license_key(): string {
        if (!$this->license_data) return '';
        return $this->license_data['license_key'] ?? '';
    }

    /**
     * 获取站点域名
     */
    public function get_domain(): string {
        return $this->domain;
    }

    /**
     * 获取已用站点数
     */
    public function get_sites_used(): int {
        if (!$this->license_data) return 0;
        return intval($this->license_data['sites_used'] ?? $this->license_data['sites_count'] ?? 0);
    }

    /**
     * 获取最大站点数
     */
    public function get_sites_max(): int {
        if (!$this->license_data) return 1;
        return intval($this->license_data['sites_max'] ?? $this->license_data['sites_limit'] ?? 1);
    }

    /**
     * 获取最新版本号
     */
    public function get_latest_version(): string {
        if (!$this->license_data) return PCS_VERSION;
        return $this->license_data['latest_version'] ?? PCS_VERSION;
    }

    /**
     * 是否有可用更新
     */
    public function has_update(): bool {
        return version_compare($this->get_latest_version(), PCS_VERSION, '>');
    }

    /**
     * 获取付费版下载URL
     */
    public function get_download_url(): string {
        if (!$this->license_data) return '';
        return $this->license_data['download_url'] ?? '';
    }

    // ==================== 套餐权限判断 ====================

    private const PLAN_LEVELS = [
        'free'      => 0,
        'pro'       => 1,
        'enterprise' => 2,
        'unlimited' => 3,
    ];

    /**
     * 检查当前授权是否满足指定套餐要求
     */
    public function is_plan(string $required_plan): bool {
        $current_level = self::PLAN_LEVELS[$this->get_plan()] ?? 0;
        $required_level = self::PLAN_LEVELS[$required_plan] ?? 0;
        return $current_level >= $required_level;
    }

    /**
     * 检查功能是否可用
     */
    public function can_use(string $feature): bool {
        if (!$this->is_active) {
            $free_features = ['currency_switch', 'contact_widget', 'cache_compat', 'blocks_compat', 'manual_rates'];
            return in_array($feature, $free_features);
        }

        $feature_plans = [
            'currency_switch'    => 'free',
            'contact_widget'     => 'free',
            'cache_compat'       => 'free',
            'blocks_compat'      => 'free',
            'manual_rates'       => 'free',
            'api_rates'          => 'pro',
            'geoip_detect'       => 'pro',
            'watermark'          => 'pro',
            'order_analytics'    => 'pro',
            'advanced_selector'  => 'pro',
            'custom_rates'       => 'pro',
            'price_rounding'     => 'pro',
            'country_pricing'    => 'pro',
            'enterprise_settings'=> 'pro',
            'order_bump'         => 'enterprise',
            'product_pricing'    => 'enterprise',
            'checkout_settlement'=> 'enterprise',
        ];

        $required_plan = $feature_plans[$feature] ?? 'free';
        return $this->is_plan($required_plan);
    }

    // ==================== 激活/停用 ====================

    /**
     * 激活授权码
     */
    public function activate(string $license_key): array {
        $response = $this->api_request('/license/activate', [
            'license_key'    => $license_key,
            'domain'         => $this->domain,
            'plugin_version' => PCS_VERSION,
        ]);

        if (is_wp_error($response)) {
            return ['success' => false, 'message' => $response->get_error_message()];
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);

        if (!$body || empty($body['success'])) {
            $error_msg = $body['message'] ?? $body['error'] ?? $body['data']['error'] ?? __('Activation failed, please check your license key', 'pro-currency-switcher');
            return ['success' => false, 'message' => $error_msg];
        }

        $license_data = $body['data'];
        $license_data['license_key'] = $license_key;
        $license_data['domain'] = $this->domain;
        $license_data['cached_at'] = current_time('mysql');
        $license_data['server_unreachable_since'] = null;

        $this->save_cached_license($license_data);
        $this->license_data = $license_data;
        $this->is_active = true;

        update_option('pcs_license_key', $license_key);

        return ['success' => true, 'message' => __('License activated successfully!', 'pro-currency-switcher'), 'data' => $license_data];
    }

    /**
     * 停用授权码
     */
    public function deactivate(): array {
        $license_key = $this->get_license_key();

        if (empty($license_key)) {
            return ['success' => false, 'message' => __('No active license found', 'pro-currency-switcher')];
        }

        $this->api_request('/license/deactivate', [
            'license_key' => $license_key,
            'domain'      => $this->domain,
        ]);

        $this->clear_license();
        delete_option('pcs_license_key');

        return ['success' => true, 'message' => __('License deactivated', 'pro-currency-switcher')];
    }

    // ==================== 内部方法 ====================

    /**
     * 检查授权状态（缓存优先）
     */
    private function check_license(): void {
        if (!$this->license_data || empty($this->license_data['license_key'])) {
            $this->is_active = false;
            $this->clear_currency_state();
            return;
        }

        $cached_at = strtotime($this->license_data['cached_at'] ?? '1970-01-01');
        $cache_age = time() - $cached_at;

        if ($cache_age < self::CACHE_TTL) {
            $this->is_active = $this->validate_license_data();
            return;
        }

        // 缓存过期，向服务器验证
        $response = $this->api_request('/license/verify', [
            'license_key'    => $this->license_data['license_key'],
            'domain'         => $this->domain,
            'plugin_version' => PCS_VERSION,
        ]);

        if (is_wp_error($response)) {
            $this->handle_server_unreachable();
            return;
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);

        if (!$body || empty($body['success'])) {
            $this->is_active = false;
            $this->clear_currency_state();
            return;
        }

        $license_data = $body['data'];
        $license_data['license_key'] = $this->license_data['license_key'];
        $license_data['domain'] = $this->domain;
        $license_data['cached_at'] = current_time('mysql');
        $license_data['server_unreachable_since'] = null;

        $this->save_cached_license($license_data);
        $this->license_data = $license_data;
        $this->is_active = true;
    }

    /**
     * 服务器不可达 → 宽限期
     */
    private function handle_server_unreachable(): void {
        $unreachable_since = $this->license_data['server_unreachable_since'] ?? null;

        if (empty($unreachable_since)) {
            $this->license_data['server_unreachable_since'] = current_time('mysql');
            $this->save_cached_license($this->license_data);
            $this->is_active = true;
            return;
        }

        $duration = time() - strtotime($unreachable_since);
        $this->is_active = ($duration < self::GRACE_PERIOD) && $this->validate_license_data();
    }

    /**
     * 验证本地数据有效性（不请求服务器）
     */
    private function validate_license_data(): bool {
        if (!$this->license_data) return false;

        $expires_at = $this->license_data['expires_at'] ?? '';
        if (!empty($expires_at)) {
            $expires_ts = strtotime($expires_at);
            if ($expires_ts && $expires_ts < time()) return false;
        }

        $domain = $this->license_data['domain'] ?? '';
        if (!empty($domain) && $domain !== $this->domain) return false;

        return true;
    }

    /**
     * 发送API请求（含HMAC签名）
     */
    private function api_request(string $endpoint, array $data) {
        $url = rtrim($this->api_url, '/') . $endpoint;
        $timestamp = time();

        // HMAC-SHA256签名
        $signature_payload = $endpoint . '|' . $timestamp;
        $signature = hash_hmac('sha256', $signature_payload, $this->api_secret);

        $response = wp_remote_post($url, [
            'timeout' => 10,
            'body'    => $data,
            'headers' => [
                'Content-Type'   => 'application/x-www-form-urlencoded',
                'X-PCS-Signature' => $signature,
                'X-PCS-Timestamp' => (string) $timestamp,
                'X-PCS-Domain'    => $this->domain,
                'X-PCS-Version'   => PCS_VERSION,
            ],
        ]);

        if (is_wp_error($response)) return $response;

        $code = wp_remote_retrieve_response_code($response);
        if ($code !== 200) {
            $body = json_decode(wp_remote_retrieve_body($response), true);
            $error_detail = $body['error'] ?? '';
            $error_msg = $error_detail
                ? sprintf(__('Server error (HTTP %d): %s', 'pro-currency-switcher'), $code, $error_detail)
                : sprintf(__('Server error (HTTP %d)', 'pro-currency-switcher'), $code);
            return new \WP_Error('api_error', $error_msg);
        }

        return $response;
    }

    /**
     * 获取站点域名
     */
    private function get_site_domain(): string {
        $url = home_url();
        $parsed = wp_parse_url($url);
        return strtolower($parsed['host'] ?? $_SERVER['HTTP_HOST'] ?? 'localhost');
    }

    // ==================== 缓存管理 ====================

    private function load_cached_license(): void {
        $cached = get_option(self::CACHE_KEY, null);
        if (is_array($cached)) {
            $this->license_data = $cached;
        }
    }

    private function save_cached_license(array $data): void {
        update_option(self::CACHE_KEY, $data, false);
    }

    private function clear_license(): void {
        $this->license_data = null;
        $this->is_active = false;
        delete_option(self::CACHE_KEY);
    }

    /**
     * 清除货币选择状态（Cookie + WC Session）
     * 授权过期时调用，确保前端回退到基准货币
     */
    private function clear_currency_state(): void {
        // 清除Cookie
        if (!headers_sent()) {
            setcookie('pcs_currency', '', [
                'expires' => time() - 3600,
                'path' => '/',
                'domain' => '',
                'secure' => is_ssl(),
                'httponly' => false,
                'samesite' => 'Lax',
            ]);
        }
        unset($_COOKIE['pcs_currency']);

        // 清除WooCommerce Session
        if (function_exists('WC') && WC()->session) {
            try {
                WC()->session->__unset('pcs_currency');
            } catch (Exception $e) {}
        }
    }

    /**
     * 强制重新验证
     */
    public function force_verify(): array {
        if (!$this->license_data || empty($this->license_data['license_key'])) {
            return ['success' => false, 'message' => __('No active license found', 'pro-currency-switcher')];
        }

        $this->license_data['cached_at'] = '1970-01-01 00:00:00';
        $this->license_data['server_unreachable_since'] = null;
        $this->save_cached_license($this->license_data);
        $this->check_license();

        return $this->is_active
            ? ['success' => true, 'message' => __('License verified', 'pro-currency-switcher')]
            : ['success' => false, 'message' => __('License verification failed', 'pro-currency-switcher')];
    }
}
