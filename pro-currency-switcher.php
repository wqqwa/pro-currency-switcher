<?php
/**
 * Plugin Name:       Pro Currency Switcher
 * Plugin URI:        https://hb.woocross.com
 * Description:       适用于 WooCommerce 的免费多币种切换器。支持 206 种货币、手动汇率、货币选择器、在线客服小部件、缓存兼容性以及 WooCommerce 模块。
 * Version:           1.2.0
 * Requires at least: 5.0
 * Tested up to:      7.0
 * Requires PHP:      7.4
 * Author:            WooCross
 * Author URI:        https://hb.woocross.com
 * License:           GPLv2 or later
 * License URI:       https://hb.woocross.com/license
 * Text Domain:       pro-currency-switcher
 * Domain Path:       /languages
 *
 * WC requires at least: 3.0
 * WC tested up to:      9.7
 *
 * @package           ProCurrencySwitcher
 * @version           1.2.0
 * @author            WooCross
 * @license           GPLv2 or later
 */

defined('ABSPATH') || exit;

// ============================================================
// 激活/停用期间：只注册激活钩子，不加载任何其他代码
// ============================================================
$is_activation_request = false;
if (isset($_GET['action']) && in_array($_GET['action'], ['activate', 'activate-selected', 'deactivate', 'deactivate-selected', 'upload-plugin', 'upgrade-plugin'], true)) {
    $is_activation_request = true;
}
if (isset($_POST['action']) && in_array($_POST['action'], ['activate', 'activate-selected', 'deactivate', 'deactivate-selected', 'upload-plugin', 'upgrade-plugin'], true)) {
    $is_activation_request = true;
}

// ============================================================
// 插件常量
// ============================================================
define('PCS_VERSION', '1.2.0');
define('PCS_DB_VERSION', '1.0.0');
define('PCS_PLUGIN_PATH', plugin_dir_path(__FILE__));
define('PCS_PLUGIN_URL', plugin_dir_url(__FILE__));
define('PCS_PLUGIN_BASENAME', plugin_basename(__FILE__));
define('PCS_IS_FREE', true); // 标记为免费版

// ============================================================
// PSR-4 自动加载器
// ============================================================
spl_autoload_register(function (string $class): void {
    $prefix = 'ProCurrencySwitcher\\';
    $base_dir = PCS_PLUGIN_PATH . 'includes/';

    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }

    $relative_class = substr($class, $len);
    $file = $base_dir . str_replace('\\', '/', $relative_class) . '.php';

    if (file_exists($file)) {
        require_once $file;
    }
});

// ============================================================
// 主插件控制器（免费版）
// ============================================================
final class ProCurrencySwitcher {

    /** @var bool 防止重复初始化 */
    private static bool $initialized = false;

    public static function init(): void {
        if (self::$initialized) {
            return;
        }
        self::$initialized = true;

        register_activation_hook(__FILE__, [__CLASS__, 'activate']);
        register_deactivation_hook(__FILE__, [__CLASS__, 'deactivate']);
        register_uninstall_hook(__FILE__, [__CLASS__, 'uninstall']);

        if ($GLOBALS['is_activation_request'] ?? false) {
            return;
        }

        require_once PCS_PLUGIN_PATH . 'includes/functions.php';
        require_once PCS_PLUGIN_PATH . 'includes/currency-data.php';

        // 插件列表页添加官网链接
        add_filter('plugin_row_meta', [__CLASS__, 'plugin_row_meta_links'], 10, 2);
        add_action('plugins_loaded', [__CLASS__, 'on_plugins_loaded'], 5);
        add_action('init', [__CLASS__, 'on_init']);
        add_action('before_woocommerce_init', [__CLASS__, 'declare_wc_compatibility']);
    }

    public static function on_plugins_loaded(): void {
        load_plugin_textdomain('pro-currency-switcher', false, dirname(PCS_PLUGIN_BASENAME) . '/languages');
        self::check_version_update();
        self::check_db_migration();

        add_action('wp_ajax_pcs_switch_currency', [__CLASS__, 'ajax_switch_currency']);
        add_action('wp_ajax_nopriv_pcs_switch_currency', [__CLASS__, 'ajax_switch_currency']);
        add_action('wp_ajax_pcs_get_currencies', [__CLASS__, 'ajax_get_currencies']);
        add_action('wp_ajax_nopriv_pcs_get_currencies', [__CLASS__, 'ajax_get_currencies']);
        add_action('wp_ajax_pcs_dismiss_notice', [__CLASS__, 'ajax_dismiss_notice']);

        // 初始化授权系统
        add_action('plugins_loaded', [__CLASS__, 'init_license_system'], 20);
    }

    /**
     * 初始化授权系统（优先级20，确保所有类已加载）
     * 容错设计：授权系统出错不影响插件主功能
     */
    public static function init_license_system(): void {
        // 初始化授权管理器（单例，自动检查授权状态）
        // 注意：LicenseAdmin（菜单）移到 init_admin() 中创建，确保在 AdminSettings 之后
        if (class_exists('ProCurrencySwitcher\\License\\LicenseManager')) {
            try {
                $manager = \ProCurrencySwitcher\License\LicenseManager::get_instance();

                // 授权有效时加载Premium模块
                if ($manager->is_active() && is_dir(PCS_PLUGIN_PATH . 'includes/Premium')) {
                    self::load_premium_modules($manager);
                }
            } catch (\Throwable $e) {
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    error_log('[PCS] 授权系统初始化失败: ' . $e->getMessage());
                }
            }
        }

        // 自动更新检查
        if (class_exists('ProCurrencySwitcher\\License\\LicenseUpdater')) {
            try {
                new \ProCurrencySwitcher\License\LicenseUpdater();
            } catch (\Throwable $e) {
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    error_log('[PCS] LicenseUpdater 初始化失败: ' . $e->getMessage());
                }
            }
        }
    }

    /**
     * 加载付费模块（根据套餐类型条件加载）
     */
    private static function load_premium_modules($manager): void {
        $premium_dir = PCS_PLUGIN_PATH . 'includes/Premium/';

        // 专业版模块（Pro及以上可用）
        if ($manager->can_use('api_rates')) {
            $pro_files = [
                'Pro/ExchangeService.php',
                'Pro/WatermarkHandler.php',
                'Pro/OrderAnalyticsSync.php',
                'Pro/CountryPricingManager.php',
                'Pro/CustomRatesManager.php',
                'Pro/PriceRoundingManager.php',
                'Pro/CurrencySelectorStyles.php',
            ];
            foreach ($pro_files as $file) {
                $path = $premium_dir . $file;
                if (file_exists($path)) {
                    require_once $path;
                }
            }
        }

        // 企业版模块（Enterprise及以上可用）
        if ($manager->can_use('order_bump')) {
            $ent_files = [
                'Enterprise/OrderBumpDisplay.php',
                'Enterprise/ProductPricingManager.php',
                'Enterprise/CheckoutSettlementHandler.php',
            ];
            foreach ($ent_files as $file) {
                $path = $premium_dir . $file;
                if (file_exists($path)) {
                    require_once $path;
                }
            }
        }
    }

    /**
     * init 回调：初始化免费版模块
     * 注意：此文件不包含任何付费功能代码
     */
    public static function on_init(): void {
        add_action('wp_enqueue_scripts', [__CLASS__, 'enqueue_frontend_assets']);
        add_action('admin_enqueue_scripts', [__CLASS__, 'enqueue_admin_assets']);

        // 初始化统一服务层（单例）
        try {
            $cs_class = \ProCurrencySwitcher\Core\CurrencyService::class;
            if (class_exists($cs_class)) {
                $cs_class::get_instance();
            }
        } catch (\Throwable $e) {}

        // 初始化缓存兼容
        try {
            $cache_class = \ProCurrencySwitcher\Core\CacheCompatHandler::class;
            if (class_exists($cache_class)) {
                new $cache_class();
            }
        } catch (\Throwable $e) {}

        // 前端组件
        if (!is_admin() && !wp_doing_ajax()) {
            self::init_frontend();
        }

        // 后台组件
        if (is_admin()) {
            self::init_admin();
        }

        // WooCommerce 集成
        if (function_exists('WC')) {
            self::init_woocommerce();
        }

        // 开发者钩子
        do_action('pcs_init', PCS_VERSION);
    }

    // --------------------------------------------------------
    // 模块初始化（仅免费版模块）
    // --------------------------------------------------------

    private static function init_frontend(): void {
        $classes = [
            \ProCurrencySwitcher\Frontend\CurrencySelector::class,
            \ProCurrencySwitcher\Frontend\PriceDisplay::class,
            \ProCurrencySwitcher\Frontend\ContactWidget::class,
        ];
        foreach ($classes as $class) {
            if (class_exists($class)) {
                new $class();
            }
        }
    }

    private static function init_admin(): void {
        $classes = [
            \ProCurrencySwitcher\Admin\AdminSettings::class,
            \ProCurrencySwitcher\Admin\ContactWidgetSettings::class,
        ];
        foreach ($classes as $class) {
            if (class_exists($class)) {
                new $class();
            }
        }

        // LicenseAdmin 必须在 AdminSettings 之后创建，确保菜单注册顺序正确
        // AdminSettings 先注册顶级菜单和"基本设置"子菜单
        // LicenseAdmin 后注册"授权管理"子菜单
        if (class_exists('ProCurrencySwitcher\\License\\LicenseAdmin')) {
            try {
                new \ProCurrencySwitcher\License\LicenseAdmin();
            } catch (\Throwable $e) {
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    error_log('[PCS] LicenseAdmin 初始化失败: ' . $e->getMessage());
                }
            }
        }
    }

    public static function declare_wc_compatibility(): void {
        if (!class_exists(\Automattic\WooCommerce\Utilities\FeaturesUtil::class)) {
            return;
        }

        $features = [
            'custom_order_tables',
            'product_block_editor',
            'cart_checkout_blocks',
        ];

        foreach ($features as $feature) {
            try {
                \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility($feature, 'pro-currency-switcher/pro-currency-switcher.php', true);
            } catch (Exception $e) {}
        }
    }

    private static function init_woocommerce(): void {
        $wc_classes = [
            \ProCurrencySwitcher\Core\WooCommerceIntegration::class,
            \ProCurrencySwitcher\Core\WCExtensionsCompat::class,
            \ProCurrencySwitcher\Core\BlocksCompat::class,
        ];
        foreach ($wc_classes as $class) {
            if (class_exists($class)) {
                new $class();
            }
        }
    }

    // --------------------------------------------------------
    // 资源加载
    // --------------------------------------------------------

    public static function enqueue_frontend_assets(): void {
        wp_enqueue_style('pcs-frontend', PCS_PLUGIN_URL . 'assets/css/frontend.css', [], PCS_VERSION);
        wp_enqueue_style('pcs-pro-styles', PCS_PLUGIN_URL . 'assets/css/pro-currency-switcher.css', [], PCS_VERSION);
        wp_enqueue_style('pcs-contact-widget', PCS_PLUGIN_URL . 'assets/css/contact-widget.css', [], PCS_VERSION);

        wp_enqueue_script('pcs-frontend', PCS_PLUGIN_URL . 'assets/js/frontend.js', ['jquery'], PCS_VERSION, true);
        wp_enqueue_script('pcs-pro-js', PCS_PLUGIN_URL . 'assets/js/pro-currency-switcher.js', ['jquery'], PCS_VERSION, true);
        wp_enqueue_script('pcs-contact-widget', PCS_PLUGIN_URL . 'assets/js/contact-widget.js', ['jquery'], PCS_VERSION, true);

        $current_currency = 'USD';
        $base_currency = get_option('woocommerce_currency', 'USD');
        $enabled_currencies = [];
        $currency_symbols = [];
        $currency_names = [];
        $price_format = 'left';

        $cs_class = \ProCurrencySwitcher\Core\CurrencyService::class;
        if (class_exists($cs_class)) {
            $service = $cs_class::get_instance();
            $current_currency = $service->get_current_currency();
            $base_currency = $service->get_base_currency();
            $enabled_currencies = $service->get_enabled_currencies();
            $price_format = get_option('woocommerce_currency_pos', 'left');
        }

        $all_currencies = pcs_get_all_currencies_data();
        foreach ($enabled_currencies as $code) {
            $currency_symbols[$code] = $all_currencies[$code]['symbol'] ?? $code;
            $currency_names[$code] = $all_currencies[$code]['name'] ?? $code;
        }

        wp_localize_script('pcs-frontend', 'pcs_data', [
            'ajax_url'         => admin_url('admin-ajax.php'),
            'current_currency' => $current_currency,
            'nonce'            => wp_create_nonce('pcs_nonce'),
        ]);

        wp_localize_script('pcs-pro-js', 'pcs_ajax', [
            'ajax_url'          => admin_url('admin-ajax.php'),
            'nonce'             => wp_create_nonce('pcs_nonce'),
            'current_currency'  => $current_currency,
            'base_currency'     => $base_currency,
            'currency_symbols'  => $currency_symbols,
            'currency_names'    => $currency_names,
            'price_format'      => $price_format,
        ]);
    }

    public static function enqueue_admin_assets(string $hook): void {
        if (strpos($hook, 'pro-currency') === false) {
            return;
        }

        wp_enqueue_style('pcs-admin', PCS_PLUGIN_URL . 'assets/css/admin.css', [], PCS_VERSION);
        wp_enqueue_script('pcs-admin', PCS_PLUGIN_URL . 'assets/js/admin.js', ['jquery'], PCS_VERSION, true);

        wp_localize_script('pcs-admin', 'pcs_admin_ajax', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce'    => wp_create_nonce('pcs_admin_nonce'),
        ]);
    }

    /**
     * 插件列表页添加官网链接
     */
    public static function plugin_row_meta_links(array $links, string $file): array {
        $base = 'pro-currency-switcher/pro-currency-switcher.php';
        if ($file !== $base) {
            return $links;
        }
        $site_links = [
            'docs'     => ['url' => 'https://hb.woocross.com/docs',        'label' => __('Documentation', 'pro-currency-switcher')],
            'api'      => ['url' => 'https://hb.woocross.com/api-docs',    'label' => __('API Docs', 'pro-currency-switcher')],
            'upgrade'  => ['url' => 'https://hb.woocross.com/upgrade',     'label' => __('Upgrade Guide', 'pro-currency-switcher')],
            'support'  => ['url' => 'https://hb.woocross.com/support',     'label' => __('Support', 'pro-currency-switcher')],
        ];
        foreach ($site_links as $key => $item) {
            $links[] = '<a href="' . esc_url($item['url']) . '" target="_blank" rel="noopener">' . esc_html($item['label']) . '</a>';
        }
        return $links;
    }

    // --------------------------------------------------------
    // AJAX 处理
    // --------------------------------------------------------

    public static function ajax_switch_currency(): void {
        check_ajax_referer('pcs_nonce', 'nonce');

        $currency = sanitize_text_field(wp_unslash($_POST['currency'] ?? ''));

        if (empty($currency)) {
            wp_send_json_error(['message' => __('Currency code cannot be empty', 'pro-currency-switcher')]);
            return;
        }

        $currency = apply_filters('pcs_pre_switch_currency', $currency);

        $cs_class = \ProCurrencySwitcher\Core\CurrencyService::class;
        if (class_exists($cs_class)) {
            $service = $cs_class::get_instance();
            if ($service->switch_currency($currency)) {
                do_action('pcs_currency_switched', $currency, $service->get_current_currency());
                wp_send_json_success([
                    'currency'         => $currency,
                    'message'          => __('Currency switched successfully', 'pro-currency-switcher'),
                    'refresh_required' => true,
                ]);
                return;
            }
        }

        wp_send_json_error(['message' => __('Invalid currency code', 'pro-currency-switcher')]);
    }

    public static function ajax_get_currencies(): void {
        check_ajax_referer('pcs_nonce', 'nonce');

        $all_currencies = pcs_get_all_currencies_data();
        $cs_class = \ProCurrencySwitcher\Core\CurrencyService::class;
        $enabled = [];
        $current = 'USD';

        if (class_exists($cs_class)) {
            $service = $cs_class::get_instance();
            $enabled = $service->get_enabled_currencies();
            $current = $service->get_current_currency();
        }

        $currencies = [];
        foreach ($enabled as $code) {
            $data = $all_currencies[$code] ?? [];
            $currencies[] = [
                'code'   => $code,
                'name'   => $data['name'] ?? $code,
                'symbol' => $data['symbol'] ?? $code,
            ];
        }

        wp_send_json_success([
            'currencies'       => $currencies,
            'current_currency' => $current,
        ]);
    }

    // --------------------------------------------------------
    // 版本更新系统
    // --------------------------------------------------------

    private static function get_version_defaults(): array {
        return [
            '1.0.0' => [
                'pcs_base_currency'      => 'USD',
                'pcs_enabled_currencies' => ['USD', 'CNY', 'EUR', 'GBP', 'JPY'],
                'pcs_auto_detect'        => 'no',
                'pcs_rate_update_log'    => [],
                'pcs_settings_saved_message' => '',
            ],
        ];
    }

    private static function get_deprecated_options(): array {
        return [];
    }

    private static function get_update_notices(): array {
        return [
            '7.5.2' => __(
                'Pro Currency Switcher v7.5.2：修复PHP 7.4兼容性问题，修复后台404错误，优化货币数量限制（基准货币不计入限额）。',
                'pro-currency-switcher'
            ),
            '7.5.1' => __(
                'Pro Currency Switcher v7.5.1：新增一键获取汇率功能（免费API），汇率有效期30小时，过期自动恢复基础货币显示。后台新增过期倒计时和汇率来源标识。',
                'pro-currency-switcher'
            ),
            '7.5.0' => __(
                'Pro Currency Switcher v7.5.0：免费版发布！支持基础货币切换、在线客服、缓存兼容。升级到专业版解锁自动汇率、图片水印、订单分析等高级功能。',
                'pro-currency-switcher'
            ),
        ];
    }

    public static function check_version_update(): void {
        $stored_version = get_option('pcs_version', '0.0.0');

        if (version_compare($stored_version, PCS_VERSION, '>=')) {
            return;
        }

        $old_version = $stored_version;
        self::migrate_settings($old_version, PCS_VERSION);
        self::cleanup_deprecated_options($old_version, PCS_VERSION);
        update_option('pcs_version', PCS_VERSION);
        do_action('pcs_version_updated', $old_version, PCS_VERSION);

        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log(sprintf('[PCS] 插件从 v%s 升级到 v%s', $old_version, PCS_VERSION));
        }

        if (is_admin() && current_user_can('manage_options')) {
            self::show_update_notice($old_version, PCS_VERSION);
        }
    }

    private static function migrate_settings(string $from, string $to): void {
        $all_defaults = self::get_version_defaults();

        foreach ($all_defaults as $version => $options) {
            if (version_compare($version, $from, '<=') || version_compare($version, $to, '>')) {
                continue;
            }

            foreach ($options as $key => $value) {
                if (get_option($key) === false) {
                    add_option($key, $value);
                }
            }

            do_action("pcs_migrate_settings_{$version}", $from, $to);
        }
    }

    private static function cleanup_deprecated_options(string $from, string $to): void {
        $deprecated = self::get_deprecated_options();

        foreach ($deprecated as $version => $options) {
            if (version_compare($version, $from, '<=') || version_compare($version, $to, '>')) {
                continue;
            }

            foreach ($options as $option) {
                delete_option($option);
            }
        }
    }

    private static function show_update_notice(string $old_version, string $to): void {
        $notices = self::get_update_notices();

        foreach ($notices as $version => $message) {
            if (version_compare($version, $old_version, '<=') || version_compare($version, $to, '>')) {
                continue;
            }

            $notice_key = 'pcs_update_notice_' . str_replace('.', '_', $version);
            if (get_transient($notice_key) !== false) {
                continue;
            }

            add_action('admin_notices', function () use ($message, $version, $notice_key) {
                $dismiss_url = wp_nonce_url(
                    admin_url('admin-ajax.php?action=pcs_dismiss_notice&notice=' . urlencode($notice_key)),
                    'pcs_dismiss_notice'
                );
                ?>
                <div class="notice notice-info is-dismissible" data-pcs-notice="<?php echo esc_attr($notice_key); ?>" style="border-left: 4px solid #2271b1;">
                    <p><strong>Pro Currency Switcher v<?php echo esc_html($version); ?></strong></p>
                    <p><?php echo esc_html($message); ?></p>
                    <p>
                        <a href="<?php echo esc_url(admin_url('options-general.php?page=pro-currency-settings')); ?>" class="button button-primary">
                            <?php esc_html_e('View Settings', 'pro-currency-switcher'); ?>
                        </a>
                    </p>
                </div>
                <script>
                jQuery(document).on('click', '[data-pcs-notice="<?php echo esc_js($notice_key); ?>"] .notice-dismiss', function() {
                    jQuery.post(ajaxurl, {
                        action: 'pcs_dismiss_notice',
                        notice: '<?php echo esc_js($notice_key); ?>',
                        nonce: '<?php echo esc_js(wp_create_nonce("pcs_dismiss_notice")); ?>'
                    });
                });
                </script>
                <?php
            });

            set_transient($notice_key, 'shown', 7 * DAY_IN_SECONDS);
        }
    }

    public static function ajax_dismiss_notice(): void {
        check_ajax_referer('pcs_dismiss_notice', 'nonce');

        $notice = sanitize_text_field(wp_unslash($_POST['notice'] ?? ''));
        if (!empty($notice) && strpos($notice, 'pcs_update_notice_') === 0) {
            set_transient($notice, 'dismissed', 30 * DAY_IN_SECONDS);
        }

        wp_send_json_success();
    }

    // --------------------------------------------------------
    // 数据库迁移系统
    // --------------------------------------------------------

    public static function check_db_migration(): void {
        $current_db_version = get_option('pcs_db_version', '0.0.0');

        if (version_compare($current_db_version, PCS_DB_VERSION, '<')) {
            self::run_migrations($current_db_version, PCS_DB_VERSION);
            update_option('pcs_db_version', PCS_DB_VERSION);
        }
    }

    public static function run_migrations(string $from, string $to): void {
        if (version_compare($from, '1.0.0', '<')) {
            self::create_tables();
            do_action('pcs_migration_1_0_0');
        }
    }

    // --------------------------------------------------------
    // 生命周期
    // --------------------------------------------------------

    public static function activate(): void {
        ob_start();
        try {
            self::create_tables();

            $defaults = [
                'pcs_base_currency'             => 'USD',
                'pcs_enabled_currencies'        => ['USD', 'CNY', 'EUR', 'GBP', 'JPY'],
                'pcs_version'                   => PCS_VERSION,
                'pcs_db_version'                => PCS_DB_VERSION,
                'pcs_auto_detect'               => 'no',
                'pcs_rate_update_log'           => [],
                'pcs_settings_saved_message'    => '',
            ];

            foreach ($defaults as $key => $value) {
                if (get_option($key) === false) {
                    add_option($key, $value);
                }
            }

            do_action('pcs_activated', PCS_VERSION);
        } finally {
            ob_end_clean();
        }
    }

    public static function deactivate(): void {
        ob_start();
        try {
            delete_transient('pcs_exchange_rates');
            delete_transient('pcs_displayable_currencies');
            do_action('pcs_deactivated');
        } finally {
            ob_end_clean();
        }
    }

    public static function uninstall(): void {
        global $wpdb;

        $wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}pcs_exchange_rates");
        $wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}pcs_currency_usage");
        $wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}pcs_contact_messages");

        $options = [
            'pcs_base_currency', 'pcs_enabled_currencies', 'pcs_version', 'pcs_db_version',
            'pcs_rate_update_log', 'pcs_settings_saved_message',
            'pcs_contact_widget_settings',
        ];
        foreach ($options as $option) {
            delete_option($option);
        }

        $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_pcs_%'");
        $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_timeout_pcs_%'");
        $wpdb->query("DELETE FROM {$wpdb->postmeta} WHERE meta_key LIKE '_pcs_%'");
    }

    /**
     * 创建数据表（仅免费版需要的表）
     * 注意：付费版独有的表（pcs_order_analytics, pcs_order_bumps, pcs_order_bump_stats）
     * 不在此处创建，由付费版插件自行管理
     */
    private static function create_tables(): void {
        global $wpdb;
        $charset = $wpdb->get_charset_collate();

        $sql = [
            "CREATE TABLE {$wpdb->prefix}pcs_exchange_rates (
                id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
                base_currency char(3) NOT NULL,
                target_currency char(3) NOT NULL,
                exchange_rate decimal(16,6) NOT NULL DEFAULT 0,
                rate_source varchar(20) NOT NULL DEFAULT 'manual',
                expires_at datetime DEFAULT NULL,
                last_updated datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY  (id),
                UNIQUE KEY currency_pair (base_currency, target_currency),
                KEY last_updated (last_updated),
                KEY expires_at (expires_at)
            ) $charset;",

            "CREATE TABLE {$wpdb->prefix}pcs_currency_usage (
                id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
                currency_code char(3) NOT NULL,
                session_id varchar(255) NOT NULL DEFAULT '',
                ip_address varchar(45) NOT NULL DEFAULT '',
                country_code char(2) NOT NULL DEFAULT '',
                created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY  (id),
                KEY currency_code (currency_code),
                KEY created_at (created_at)
            ) $charset;",

            "CREATE TABLE {$wpdb->prefix}pcs_contact_messages (
                id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
                name varchar(100) NOT NULL DEFAULT '',
                email varchar(100) NOT NULL DEFAULT '',
                phone varchar(50) NOT NULL DEFAULT '',
                message text NOT NULL,
                created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY  (id),
                KEY created_at (created_at)
            ) $charset;",
        ];

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        foreach ($sql as $query) {
            dbDelta($query);
        }

        // 兼容升级：为旧版本表添加 rate_source 和 expires_at 字段
        self::maybe_add_rate_expiry_columns();
    }

    /**
     * 为旧版本数据库添加过期相关字段
     */
    private static function maybe_add_rate_expiry_columns(): void {
        global $wpdb;
        $table = $wpdb->prefix . 'pcs_exchange_rates';
        $row = $wpdb->get_results("SHOW COLUMNS FROM {$table} LIKE 'rate_source'");
        if (empty($row)) {
            $wpdb->query("ALTER TABLE {$table} ADD COLUMN rate_source varchar(20) NOT NULL DEFAULT 'manual' AFTER exchange_rate");
            $wpdb->query("ALTER TABLE {$table} ADD COLUMN expires_at datetime DEFAULT NULL AFTER rate_source");
            $wpdb->query("ALTER TABLE {$table} ADD INDEX expires_at (expires_at)");
        }
    }
}

// 启动插件
ProCurrencySwitcher::init();
