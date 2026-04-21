<?php
/**
 * 管理设置页面（免费版）
 *
 * 免费版功能：基准货币选择、启用货币管理、手动汇率设置
 * 付费版功能（已移除）：API自动汇率、GeoIP自动检测、API提供商配置
 *
 * @package ProCurrencySwitcher
 * @since 1.0.0
 * @version 7.5.0-free
 */

namespace ProCurrencySwitcher\Admin;

use ProCurrencySwitcher\Core\CurrencyService;

if (!defined('ABSPATH')) {
    exit;
}

class AdminSettings {

    public function __construct() {
        add_action('admin_menu', [$this, 'add_admin_menu']);
        add_action('admin_init', [$this, 'register_settings']);
        add_action('admin_notices', [$this, 'admin_notices']);

        // AJAX handlers
        $ajax_actions = [
            'pcs_save_manual_rate'        => 'ajax_save_manual_rate',
            'save_manual_rate'            => 'ajax_save_manual_rate',
            'pcs_toggle_currency'         => 'ajax_toggle_currency',
            'pcs_set_base_currency'       => 'ajax_set_base_currency',
            'pcs_bulk_currency_action'    => 'ajax_bulk_currency_action',
            'pcs_fetch_rates_api'         => 'ajax_fetch_rates_api',
        ];
        foreach ($ajax_actions as $action => $method) {
            add_action('wp_ajax_' . $action, [$this, $method]);
        }
    }

    public function add_admin_menu() {
        // 一级菜单
        add_menu_page(
            __('WooCross Currency Switcher', 'pro-currency-switcher'),
            __('Currency Switcher', 'pro-currency-switcher'),
            'manage_options',
            'pro-currency-switcher',
            [$this, 'settings_page'],
            'dashicons-money-alt',
            56
        );

        // 基本设置（子菜单，替换默认的一级菜单项）
        add_submenu_page(
            'pro-currency-switcher',
            __('WooCross Currency Switcher Settings', 'pro-currency-switcher'),
            __('General Settings', 'pro-currency-switcher'),
            'manage_options',
            'pro-currency-switcher',
            [$this, 'settings_page']
        );

        // 帮助页面
        add_submenu_page(
            'pro-currency-switcher',
            __('Help', 'pro-currency-switcher'),
            __('Help', 'pro-currency-switcher'),
            'manage_options',
            'pcs-help',
            [$this, 'help_page_callback']
        );
    }

    public function register_settings() {
        // 注册设置组（group名必须与表单option_page和settings_fields一致）
        register_setting('pcs-settings', 'pcs_base_currency');
        register_setting('pcs-settings', 'pcs_enabled_currencies', [
            'type' => 'array',
            'default' => ['USD', 'CNY', 'EUR', 'GBP', 'JPY'],
            'sanitize_callback' => [$this, 'sanitize_enabled_currencies'],
        ]);
        register_setting('pcs-settings', 'pcs_auto_detect', [
            'type' => 'string',
            'default' => 'no',
        ]);

        // 添加设置章节
        add_settings_section(
            'pcs_general_section',
            __('General Settings', 'pro-currency-switcher'),
            [$this, 'general_section_callback'],
            'pcs-settings'
        );

        // Base currency field
        add_settings_field(
            'pcs_base_currency',
            __('Base Currency', 'pro-currency-switcher'),
            [$this, 'base_currency_callback'],
            'pcs-settings',
            'pcs_general_section'
        );

        // 启用货币字段
        add_settings_field(
            'pcs_enabled_currencies',
            __('Enabled Currencies', 'pro-currency-switcher'),
            [$this, 'enabled_currencies_callback'],
            'pcs-settings',
            'pcs_general_section'
        );

        // GeoIP自动检测开关
        add_settings_field(
            'pcs_auto_detect',
            __('Auto Detect Currency', 'pro-currency-switcher'),
            [$this, 'auto_detect_callback'],
            'pcs-settings',
            'pcs_general_section'
        );
    }

    /**
     * 后端验证：免费版限制启用货币数量
     * 基准货币不计入限制（基准货币始终启用）
     */
    public function sanitize_enabled_currencies($input) {
        if (!is_array($input)) {
            return ['USD', 'CNY', 'EUR', 'GBP', 'JPY'];
        }

        // 过滤有效值
        $input = array_map('sanitize_text_field', $input);
        $input = array_filter($input, function($code) {
            return preg_match('/^[A-Z]{3}$/', $code);
        });

        // 确保基准货币始终在列表中
        $base_currency = get_option('pcs_base_currency', 'USD');
        if (!in_array($base_currency, $input)) {
            $input[] = $base_currency;
        }

        // 免费版限制：基准货币 + 5个其他货币
        $max_currencies = apply_filters('pcs_max_currencies', 5);
        $max_total = $max_currencies + 1; // +1 给基准货币
        if (count($input) > $max_total) {
            // 保留基准货币 + 前N个
            $result = [$base_currency];
            foreach ($input as $code) {
                if ($code !== $base_currency && count($result) < $max_total) {
                    $result[] = $code;
                }
            }
            $input = $result;
            add_settings_error(
                'pcs_enabled_currencies',
                'currency_limit',
                sprintf(
                    __('Free version allows base currency + %d additional currencies. Auto-trimmed. Upgrade to Pro or Enterprise for more.', 'pro-currency-switcher'),
                    $max_currencies
                ),
                'warning'
            );
        }

        return array_values($input);
    }

    public function settings_page() {
        if (!current_user_can('manage_options')) {
            wp_die(__('Insufficient permissions', 'pro-currency-switcher'));
        }

        try {
            $this->render_settings_page();
        } catch (\Throwable $e) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                echo '<div class="notice notice-error"><p>';
                echo '<strong>' . esc_html__('Page rendering error:', 'pro-currency-switcher') . '</strong><br>';
                echo esc_html($e->getMessage()) . '<br>';
                echo esc_html($e->getFile() . ':' . $e->getLine());
                echo '</p></div>';
            } else {
                echo '<div class="notice notice-error"><p>';
                echo '<strong>' . esc_html__('Page rendering error', 'pro-currency-switcher') . '</strong> ';
                echo esc_html__('Please contact the developer with the following information: ', 'pro-currency-switcher');
                echo esc_html($e->getMessage());
                echo '</p></div>';
            }
        }
    }

    private function render_settings_page() {

        // 获取当前设置
        $base_currency = get_option('pcs_base_currency', 'USD');
        $enabled_currencies = get_option('pcs_enabled_currencies', ['USD', 'EUR', 'GBP', 'JPY', 'CNY']);
        if (!is_array($enabled_currencies)) { $enabled_currencies = ['USD', 'EUR', 'GBP', 'JPY', 'CNY']; }
        $enabled_count = is_array($enabled_currencies) ? count($enabled_currencies) : 0;

        // 传递翻译字符串到JS
        $pcs_i18n = [
            'saving'          => esc_js(__('Saving...', 'pro-currency-switcher')),
            'save'            => esc_js(__('Save', 'pro-currency-switcher')),
            'invalid_rate'    => esc_js(__('Please enter a valid exchange rate', 'pro-currency-switcher')),
            'save_success'    => esc_js(__('Exchange rate saved!', 'pro-currency-switcher')),
            'save_fail'       => esc_js(__('Save failed: ', 'pro-currency-switcher')),
            'fetching'        => esc_js(__('Fetching...', 'pro-currency-switcher')),
            'fetch_rates'     => esc_js(__('Fetch Rates', 'pro-currency-switcher')),
            'fetch_success'   => esc_js(__('Rates fetched successfully! Expires in 30 hours.', 'pro-currency-switcher')),
            'fetch_fail'      => esc_js(__('Fetch failed: ', 'pro-currency-switcher')),
            'rate_expired'    => esc_js(__('Expired', 'pro-currency-switcher')),
            'expires_in'      => esc_js(__('Remaining', 'pro-currency-switcher')),
            'expired'         => esc_js(__('Expired', 'pro-currency-switcher')),
            'never_expire'    => esc_js(__('Never expires', 'pro-currency-switcher')),
            'hours'           => esc_js(__('hours', 'pro-currency-switcher')),
            'minutes'         => esc_js(__('minutes', 'pro-currency-switcher')),
            'seconds'         => esc_js(__('seconds', 'pro-currency-switcher')),
            'server_now'      => current_time('timestamp'),
            'max_currencies'  => 5,
            'currency_limit'  => esc_js(__('Free version allows up to 5 currencies. Upgrade to Pro or Enterprise for more.', 'pro-currency-switcher')),
            'upgrade_url'     => 'https://woocross.com/pricing',
        ];
        ?>
        <div class="wrap">
            <h1><?php echo esc_html__('WooCross Currency Switcher', 'pro-currency-switcher'); ?></h1>

            <?php $this->system_status($base_currency, $enabled_count); ?>

            <form method="post" action="options.php">
                <?php
                settings_fields('pcs-settings');
                do_settings_sections('pcs-settings');
                submit_button(__('Save Settings', 'pro-currency-switcher'));
                ?>
            </form>

            <?php $this->exchange_rates_section(); ?>

            <?php $this->upgrade_notice(); ?>
        </div>

        <script>
        jQuery(document).ready(function($) {
            var pcs_i18n = <?php echo wp_json_encode($pcs_i18n); ?>;

            // 手动设置汇率
            $('.pcs-save-manual-rate').on('click', function() {
                var button = $(this);
                var currency = button.data('currency');
                var rate = $('#manual_rate_' + currency).val();

                if (!rate || isNaN(rate)) {
                    alert(pcs_i18n.invalid_rate);
                    return;
                }

                button.prop('disabled', true).text(pcs_i18n.saving);

                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'save_manual_rate',
                        currency: currency,
                        rate: rate,
                        nonce: '<?php echo wp_create_nonce("pcs_admin_nonce"); ?>'
                    },
                    success: function(response) {
                        if (response.success) {
                            alert(pcs_i18n.save_success);
                            location.reload();
                        } else {
                            alert(pcs_i18n.save_fail + response.data);
                        }
                        button.prop('disabled', false).text(pcs_i18n.save);
                    }
                });
            });

            // 一键获取汇率（从免费API）
            $('#pcs-fetch-all-rates').on('click', function() {
                var button = $(this);
                if (!confirm('<?php echo esc_js(__('This will fetch the latest exchange rates from a public API. All rates will expire in 30 hours. Continue?', 'pro-currency-switcher')); ?>')) {
                    return;
                }

                button.prop('disabled', true).text(pcs_i18n.fetching);

                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'pcs_fetch_rates_api',
                        nonce: '<?php echo wp_create_nonce("pcs_admin_nonce"); ?>'
                    },
                    success: function(response) {
                        if (response.success) {
                            alert(pcs_i18n.fetch_success);
                            location.reload();
                        } else {
                            alert(pcs_i18n.fetch_fail + (response.data || ''));
                            button.prop('disabled', false).text(pcs_i18n.fetch_rates);
                        }
                    },
                    error: function() {
                        alert(pcs_i18n.fetch_fail + 'Network error');
                        button.prop('disabled', false).text(pcs_i18n.fetch_rates);
                    }
                });
            });

            // 过期倒计时显示（使用服务器时间戳，避免时区偏差）
            var serverNow = pcs_i18n.server_now;
            var clientNow = Math.floor(Date.now() / 1000);
            var timeOffset = clientNow - serverNow; // 客户端与服务器的时间差

            // 免费版货币数量限制（基准货币不计入）
            var maxCurrencies = pcs_i18n.max_currencies;

            function updateCurrencyLimit() {
                // 只计算非基准货币的勾选数
                var checkedOther = $('input[name="pcs_enabled_currencies[]"]:checked:not([data-base="1"])').length;
                if (checkedOther >= maxCurrencies) {
                    $('input[name="pcs_enabled_currencies[]"]:not(:checked):not([data-base="1"])').prop('disabled', true);
                } else {
                    $('input[name="pcs_enabled_currencies[]"]:not([data-base="1"])').prop('disabled', false);
                }
            }

            $('input[name="pcs_enabled_currencies[]"]:not([data-base="1"])').on('change', function() {
                var checkedOther = $('input[name="pcs_enabled_currencies[]"]:checked:not([data-base="1"])').length;
                if ($(this).is(':checked') && checkedOther > maxCurrencies) {
                    $(this).prop('checked', false);
                    alert(pcs_i18n.currency_limit + '\n\n' + pcs_i18n.upgrade_url);
                    return;
                }
                updateCurrencyLimit();
            });

            // 表单提交前验证
            $('form[action="options.php"]').on('submit', function() {
                var checkedOther = $('input[name="pcs_enabled_currencies[]"]:checked:not([data-base="1"])').length;
                if (checkedOther > maxCurrencies) {
                    alert(pcs_i18n.currency_limit);
                    return false;
                }
                return true;
            });

            updateCurrencyLimit();

            function updateExpiryCountdowns() {
                var now = Math.floor(Date.now() / 1000) - timeOffset; // 校正为服务器时间
                $('.pcs-expiry-countdown').each(function() {
                    var el = $(this);
                    var expiresAt = parseInt(el.data('expires'));
                    var diff = expiresAt - now;

                    if (diff <= 0) {
                        el.html('<span style="color:#d63638;font-weight:bold;">⏰ ' + pcs_i18n.expired + '</span>');
                    } else {
                        var hours = Math.floor(diff / 3600);
                        var minutes = Math.floor((diff % 3600) / 60);
                        var seconds = diff % 60;
                        var color = hours < 6 ? '#d63638' : (hours < 12 ? '#dba617' : '#00a32a');
                        var timeStr = '';
                        if (hours > 0) timeStr += hours + pcs_i18n.hours;
                        timeStr += (minutes < 10 ? '0' : '') + minutes + pcs_i18n.minutes;
                        timeStr += (seconds < 10 ? '0' : '') + seconds + pcs_i18n.seconds;
                        el.html('<span style="color:' + color + ';">⏱ ' + pcs_i18n.expires_in + ' ' + timeStr + '</span>');
                    }
                });
            }
            updateExpiryCountdowns();
            setInterval(updateExpiryCountdowns, 1000); // 每秒更新
        });
        </script>
        <?php
    }

    public function general_section_callback() {
        echo '<p>' . __('Configure basic settings for the currency switcher', 'pro-currency-switcher') . '</p>';
    }

    public function base_currency_callback() {
        $base_currency = get_option('pcs_base_currency', 'USD');
        $all_currencies = $this->get_all_currencies();

        echo '<select name="pcs_base_currency" id="pcs_base_currency">';
        foreach ($all_currencies as $code => $name) {
            $selected = selected($base_currency, $code, false);
            echo "<option value='{$code}' {$selected}>{$code} - {$name}</option>";
        }
        echo '</select>';
        echo '<p class="description">' . __('Select the base currency for your store', 'pro-currency-switcher') . '</p>';
    }

    /**
     * GeoIP自动检测开关回调
     */
    public function auto_detect_callback() {
        $auto_detect = get_option('pcs_auto_detect', 'no');
        echo '<label>';
        echo '<input type="checkbox" name="pcs_auto_detect" value="yes" ' . checked($auto_detect, 'yes', false) . ' />';
        echo ' ' . __('Automatically detect visitor currency by IP address', 'pro-currency-switcher');
        echo '</label>';
        echo '<p class="description">' . __('When enabled, the plugin will detect the visitor\'s country via their IP and display prices in their local currency. Currency detected by IP takes priority over cookie settings.', 'pro-currency-switcher') . '</p>';
    }

    /**
     * 启用货币回调 - 带免费版5个限制（基准货币不计入）
     */
    public function enabled_currencies_callback() {
        $enabled_currencies = get_option('pcs_enabled_currencies', ['USD', 'CNY', 'EUR', 'GBP', 'JPY']);
        if (!is_array($enabled_currencies)) { $enabled_currencies = ['USD', 'CNY', 'EUR', 'GBP', 'JPY']; }
        $all_currencies = $this->get_all_currencies();
        $base_currency = get_option('pcs_base_currency', 'USD');

        // 免费版限制5个其他货币（基准货币不计入），专业版/企业版可通过过滤器调整
        $max_currencies = apply_filters('pcs_max_currencies', 5);
        $other_count = count(array_diff($enabled_currencies, [$base_currency]));
        $is_limited = ($other_count >= $max_currencies);

        echo '<div class="pcs-currency-grid">';
        foreach ($all_currencies as $code => $name) {
            $checked = in_array($code, $enabled_currencies) ? 'checked="checked"' : '';
            $is_china = in_array($code, ['CNY', 'TWD', 'HKD', 'MOP']);
            $is_sea = in_array($code, ['SGD', 'MYR', 'THB', 'IDR', 'PHP', 'VND', 'MMK', 'KHR', 'LAK', 'BND']);

            $class = '';
            if ($is_china) {
                $class = 'pcs-china-currency';
            } elseif ($is_sea) {
                $class = 'pcs-sea-currency';
            }

            // 超出限制时，未选中的非基准货币禁用
            $disabled = '';
            if ($is_limited && empty($checked)) {
                $disabled = 'disabled="disabled"';
            }

            // 基准货币始终选中且不可取消
            $is_base = ($code === $base_currency);
            if ($is_base) {
                $checked = 'checked="checked"';
                $disabled = 'disabled="disabled"';
            }

            echo "<label class='pcs-currency-option {$class}' style='display: block; margin: 5px 0;'>";
            echo "<input type='checkbox' name='pcs_enabled_currencies[]' value='{$code}' {$checked} {$disabled} class='pcs-currency-checkbox' data-base='" . ($is_base ? '1' : '0') . "'>";
            echo " <strong>{$code}</strong> - {$name}";
            if ($is_base) {
                echo ' <span style="color:#2271b1;font-size:11px;">(' . esc_html__('Base Currency', 'pro-currency-switcher') . ')</span>';
            }
            echo "</label>";
        }
        echo '</div>';

        if ($is_limited) {
            echo '<div style="margin-top:10px; padding:10px; background:#fff3cd; border:1px solid #ffc107; border-radius:4px;">';
            echo '<p style="margin:0; color:#856404;">';
            echo '<strong>' . sprintf(
                esc_html__('Free version limit reached (base currency + %d other currencies).', 'pro-currency-switcher'),
                $max_currencies
            ) . '</strong> ';
            echo esc_html__('To enable more currencies, please upgrade to Pro or Enterprise.', 'pro-currency-switcher');
            echo ' <a href="https://woocross.com/pricing" target="_blank" style="color:#2271b1;font-weight:bold;">' . esc_html__('View upgrade plans →', 'pro-currency-switcher') . '</a>';
            echo '</p></div>';
        } else {
            $remaining = $max_currencies - $other_count;
            echo '<p class="description">' . sprintf(
                esc_html__('Select currencies to display on the frontend (%d more currencies can be selected, China and Southeast Asia currencies are highlighted)', 'pro-currency-switcher'),
                $remaining
            ) . '</p>';
        }

        // 添加CSS样式
        echo '<style>
            .pcs-china-currency { background-color: #f0f8ff; padding: 8px; border-left: 4px solid #ff6b6b; }
            .pcs-sea-currency { background-color: #f0fff0; padding: 8px; border-left: 4px solid #4ecdc4; }
            .pcs-currency-grid { max-height: 400px; overflow-y: auto; border: 1px solid #ddd; padding: 10px; }
            .pcs-currency-checkbox:disabled + strong { color: #999; }
        </style>';
    }

    private function system_status($base_currency, $enabled_count) {
        ?>
        <div class="card">
            <h2><?php echo esc_html__('System Status', 'pro-currency-switcher'); ?></h2>
            <table class="form-table">
                <tr>
                    <th scope="row"><?php echo esc_html__('Current Base Currency:', 'pro-currency-switcher'); ?></th>
                    <td><strong><?php echo esc_html($base_currency); ?></strong></td>
                </tr>
                <tr>
                    <th scope="row"><?php echo esc_html__('Enabled Currencies Count:', 'pro-currency-switcher'); ?></th>
                    <td><strong><?php echo esc_html($enabled_count); ?></strong></td>
                </tr>
                <tr>
                    <th scope="row"><?php echo esc_html__('WordPress Version:', 'pro-currency-switcher'); ?></th>
                    <td><?php echo esc_html(get_bloginfo('version')); ?></td>
                </tr>
                <tr>
                    <th scope="row"><?php echo esc_html__('Plugin Version:', 'pro-currency-switcher'); ?></th>
                    <td><?php echo esc_html(PCS_VERSION); ?></td>
                </tr>
            </table>
        </div>

        <style>
        .card {
            background: #fff;
            border: 1px solid #ccd0d4;
            border-radius: 4px;
            padding: 20px;
            margin: 20px 0;
        }
        .card h2 {
            margin-top: 0;
        }
        </style>
        <?php
    }

    /**
     * 汇率管理区域（免费版：手动设置+API获取，30小时过期）
     */
    private function exchange_rates_section() {
        $exchange_rates = $this->get_current_exchange_rates();
        ?>
         <div class="card">
             <h2><?php echo esc_html__('Exchange Rates', 'pro-currency-switcher'); ?></h2>
             <p><?php echo esc_html__('Manually enter exchange rates or fetch the latest rates from a public API. Free version rates expire after 30 hours.', 'pro-currency-switcher'); ?></p>

             <div style="margin: 15px 0; display: flex; gap: 10px; align-items: center;">
                 <button id="pcs-fetch-all-rates" class="button button-secondary">
                     <span class="dashicons dashicons-update" style="vertical-align:middle;margin-right:4px;"></span>
                     <?php echo esc_html__('Fetch Latest Rates', 'pro-currency-switcher'); ?>
                 </button>
                 <span class="description" style="color:#666;">
                     <?php echo esc_html__('Auto-fetched from public API, expires in 30 hours', 'pro-currency-switcher'); ?>
                 </span>
             </div>

             <?php if (empty($exchange_rates)): ?>
             <div style="padding: 20px; background: #fcf9e8; border: 1px solid #dba617; border-radius: 4px; margin: 10px 0;">
                 <p style="margin:0; color: #dba617;">
                     <strong><?php echo esc_html__('No exchange rate data available.', 'pro-currency-switcher'); ?></strong>
                     <?php echo esc_html__('Please save settings above first, then click "Fetch Latest Rates" or enter rates manually.', 'pro-currency-switcher'); ?>
                 </p>
             </div>
             <?php endif; ?>

             <table class="wp-list-table widefat fixed striped">
                 <thead>
                     <tr>
                         <th><?php echo esc_html__('Currency Pair', 'pro-currency-switcher'); ?></th>
                         <th><?php echo esc_html__('Current Rate', 'pro-currency-switcher'); ?></th>
                         <th><?php echo esc_html__('Source', 'pro-currency-switcher'); ?></th>
                         <th><?php echo esc_html__('Validity', 'pro-currency-switcher'); ?></th>
                         <th><?php echo esc_html__('New Rate', 'pro-currency-switcher'); ?></th>
                         <th><?php echo esc_html__('Action', 'pro-currency-switcher'); ?></th>
                         <th><?php echo esc_html__('Last Updated', 'pro-currency-switcher'); ?></th>
                     </tr>
                 </thead>
                 <tbody>
                     <?php if (empty($exchange_rates)): ?>
                     <tr>
                         <td colspan="7" style="text-align:center; color:#999; padding:20px;">
                             <?php echo esc_html__('Enabled currencies will appear here after saving settings.', 'pro-currency-switcher'); ?>
                         </td>
                     </tr>
                     <?php else: ?>
                     <?php foreach ($exchange_rates as $rate): ?>
                     <tr<?php echo !empty($rate['is_expired']) ? ' style="background:#fff5f5;"' : ''; ?>>
                         <td><strong><?php echo esc_html($rate['pair']); ?></strong></td>
                         <td>
                             <?php echo esc_html($rate['rate']); ?>
                             <?php if (!empty($rate['is_expired'])): ?>
                                 <span style="color:#d63638;font-size:11px;"> ⚠ <?php echo esc_html__('Expired', 'pro-currency-switcher'); ?></span>
                             <?php endif; ?>
                         </td>
                         <td>
                             <?php
                             if ($rate['rate_source'] === 'api') {
                                 echo '<span style="color:#2271b1;">🌐 API</span>';
                             } else {
                                 echo '<span style="color:#666;">✏️ ' . esc_html__('Manual', 'pro-currency-switcher') . '</span>';
                             }
                             ?>
                         </td>
                         <td>
                             <?php if (empty($rate['expires_at'])): ?>
                                 <span style="color:#999;"><?php echo esc_html__('Not set', 'pro-currency-switcher'); ?></span>
                             <?php elseif (!empty($rate['is_expired'])): ?>
                                 <span class="pcs-expiry-countdown" data-expires="<?php echo esc_attr(strtotime($rate['expires_at'])); ?>" style="color:#d63638;font-weight:bold;">⏰ <?php echo esc_html__('Expired', 'pro-currency-switcher'); ?></span>
                             <?php else: ?>
                                 <span class="pcs-expiry-countdown" data-expires="<?php echo esc_attr(strtotime($rate['expires_at'])); ?>"></span>
                             <?php endif; ?>
                         </td>
                         <td>
                             <input type="number"
                                    id="manual_rate_<?php echo esc_attr($rate['target']); ?>"
                                    step="0.0001"
                                    min="0"
                                    value="<?php echo esc_attr($rate['raw_rate']); ?>"
                                    class="small-text"
                                    style="width:120px;">
                         </td>
                         <td>
                             <button class="button pcs-save-manual-rate" data-currency="<?php echo esc_attr($rate['target']); ?>">
                                 <?php echo esc_html__('Save', 'pro-currency-switcher'); ?>
                             </button>
                         </td>
                         <td><?php echo esc_html($rate['last_updated']); ?></td>
                     </tr>
                     <?php endforeach; ?>
                     <?php endif; ?>
                 </tbody>
             </table>
         </div>
         <?php
    }

    /**
     * 升级提示（免费版独有）
     */
    private function upgrade_notice() {
        ?>
        <div class="card" style="border-left: 4px solid #2271b1; background: #f0f6fc;">
            <h2><?php echo esc_html__('Upgrade to Pro', 'pro-currency-switcher'); ?></h2>
            <p><strong>Pro version includes the following advanced features:</strong></p>
            <ul style="list-style: disc; padding-left: 20px;">
                <li><strong>🌟 <?php echo esc_html__('Permanent exchange rates', 'pro-currency-switcher'); ?></strong> — <?php echo esc_html__('Free version rates expire in 30 hours, Pro version never expires', 'pro-currency-switcher'); ?></li>
                <li><?php echo esc_html__('Automatic API rate updates (supports ExchangeRate-API, Fixer, etc.)', 'pro-currency-switcher'); ?></li>
                <li><?php echo esc_html__('GeoIP automatic visitor currency detection', 'pro-currency-switcher'); ?></li>
                <li><?php echo esc_html__('Custom exchange rates and rate markups', 'pro-currency-switcher'); ?></li>
                <li><?php echo esc_html__('Price rounding (.99/.95 preset templates)', 'pro-currency-switcher'); ?></li>
                <li><?php echo esc_html__('Image watermark feature', 'pro-currency-switcher'); ?></li>
                <li><?php echo esc_html__('Order analytics dashboard', 'pro-currency-switcher'); ?></li>
                <li><?php echo esc_html__('Advanced selector styles (floating button, sidebar, etc.)', 'pro-currency-switcher'); ?></li>
            </ul>
            <p>
                <a href="https://woocross.com/pricing" class="button button-primary" target="_blank">
                    <?php echo esc_html__('View Pro Details →', 'pro-currency-switcher'); ?>
                </a>
            </p>
        </div>
        <?php
    }

    private function get_current_exchange_rates() {
        global $wpdb;
        $base_currency = get_option('pcs_base_currency', 'USD');
        $enabled_currencies = get_option('pcs_enabled_currencies', []);
        if (!is_array($enabled_currencies)) { $enabled_currencies = []; }

        // 移除基准货币
        $target_currencies = array_diff($enabled_currencies, [$base_currency]);

        $rates = [];
        foreach ($target_currencies as $currency) {
            $rate_data = $wpdb->get_row($wpdb->prepare(
                "SELECT exchange_rate, rate_source, expires_at, last_updated FROM {$wpdb->prefix}pcs_exchange_rates
                 WHERE base_currency = %s AND target_currency = %s",
                $base_currency, $currency
            ));

            $is_expired = false;
            if ($rate_data && !empty($rate_data->expires_at)) {
                $is_expired = strtotime($rate_data->expires_at) < time();
            }

            $rates[] = [
                'pair'         => $base_currency . '/' . $currency,
                'target'       => $currency,
                'rate'         => $rate_data ? number_format($rate_data->exchange_rate, 4) : '0.0000',
                'raw_rate'     => $rate_data ? floatval($rate_data->exchange_rate) : 0,
                'rate_source'  => $rate_data ? ($rate_data->rate_source ?? 'manual') : '',
                'expires_at'   => $rate_data ? $rate_data->expires_at : null,
                'is_expired'   => $is_expired,
                'last_updated' => $rate_data ? $rate_data->last_updated : __('Never updated', 'pro-currency-switcher'),
            ];
        }

        return $rates;
    }

    // --------------------------------------------------------
    // AJAX处理方法（免费版仅支持手动操作）
    // --------------------------------------------------------

    public function ajax_save_manual_rate() {
        check_ajax_referer('pcs_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Insufficient permissions', 'pro-currency-switcher'));
        }

        $currency = sanitize_text_field(wp_unslash($_POST['currency']));
        $rate = floatval($_POST['rate']);

        if ($this->save_manual_rate($currency, $rate)) {
            wp_send_json_success();
        } else {
            wp_send_json_error(__('Save failed', 'pro-currency-switcher'));
        }
    }

    /**
     * 一键获取汇率（从免费公开API）
     * 使用 open.er-api.com 免费API，无需API Key
     */
    public function ajax_fetch_rates_api() {
        check_ajax_referer('pcs_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Insufficient permissions', 'pro-currency-switcher'));
        }

        $base_currency = get_option('pcs_base_currency', 'USD');
        $enabled_currencies = get_option('pcs_enabled_currencies', []);
        if (!is_array($enabled_currencies)) {
            $enabled_currencies = [];
        }

        // Fetch rates from free API
        $api_url = 'https://open.er-api.com/v6/latest/' . urlencode($base_currency);
        $response = wp_remote_get($api_url, [
            'timeout' => 15,
            'headers' => ['Accept' => 'application/json'],
        ]);

        if (is_wp_error($response)) {
            wp_send_json_error(__('API request failed: ' . $response->get_error_message(), 'pro-currency-switcher'));
        }

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        if (!$data || !isset($data['rates']) || ($data['result'] ?? '') !== 'success') {
            wp_send_json_error(__('Invalid API response data', 'pro-currency-switcher'));
        }

        $api_rates = $data['rates'];
        $saved = 0;
        $failed = 0;

        foreach ($enabled_currencies as $currency) {
            if ($currency === $base_currency) {
                continue;
            }

            if (isset($api_rates[$currency]) && floatval($api_rates[$currency]) > 0) {
                $result = $this->save_exchange_rate($base_currency, $currency, floatval($api_rates[$currency]), 'api');
                if ($result) {
                    $saved++;
                } else {
                    $failed++;
                }
            }
        }

        // 清除缓存
        if (class_exists('ProCurrencySwitcher\Core\CurrencyService')) {
            $service = \ProCurrencySwitcher\Core\CurrencyService::get_instance();
            $service->clear_rates_cache();
        }

        if ($saved > 0) {
            wp_send_json_success(sprintf(
                __('Successfully fetched %d currency rates.%s', 'pro-currency-switcher'),
                $saved,
                $failed > 0 ? sprintf(__(' (%d failed)', 'pro-currency-switcher'), $failed) : ''
            ));
        } else {
            wp_send_json_error(__('Failed to fetch any exchange rate data', 'pro-currency-switcher'));
        }
    }

    /**
     * 保存汇率到数据库
     * 免费版：所有汇率30小时后过期
     * 专业版：通过 pcs_rate_no_expiry 过滤器跳过过期
     */
    private function save_exchange_rate($base, $target, $rate, $source = 'manual') {
        global $wpdb;

        // 免费版：设置30小时过期时间（用WordPress本地时间，与strtotime一致）
        $no_expiry = apply_filters('pcs_rate_no_expiry', false, $base, $target);
        $expires_at = $no_expiry ? null : date('Y-m-d H:i:s', current_time('timestamp') + 30 * HOUR_IN_SECONDS);

        return $wpdb->replace(
            $wpdb->prefix . 'pcs_exchange_rates',
            [
                'base_currency' => $base,
                'target_currency' => $target,
                'exchange_rate' => $rate,
                'rate_source' => $source,
                'expires_at' => $expires_at,
                'last_updated' => current_time('mysql')
            ],
            ['%s', '%s', '%f', '%s', '%s', '%s']
        );
    }

    private function save_manual_rate($target_currency, $rate) {
        $base_currency = get_option('pcs_base_currency', 'USD');
        return $this->save_exchange_rate($base_currency, $target_currency, $rate, 'manual');
    }

    /**
     * 获取全球货币列表 - 完整全球货币库
     */
    private function get_all_currencies() {
        $global_currencies = [
            // Asia
            'CNY' => __('Chinese Yuan (Mainland China)', 'pro-currency-switcher'),
            'TWD' => __('New Taiwan Dollar (Taiwan)', 'pro-currency-switcher'),
            'HKD' => __('Hong Kong Dollar (Hong Kong)', 'pro-currency-switcher'),
            'MOP' => __('Macau Pataca (Macau)', 'pro-currency-switcher'),
            'JPY' => __('Japanese Yen (Japan)', 'pro-currency-switcher'),
            'KRW' => __('South Korean Won (South Korea)', 'pro-currency-switcher'),
            'SGD' => __('Singapore Dollar (Singapore)', 'pro-currency-switcher'),
            'MYR' => __('Malaysian Ringgit (Malaysia)', 'pro-currency-switcher'),
            'THB' => __('Thai Baht (Thailand)', 'pro-currency-switcher'),
            'IDR' => __('Indonesian Rupiah (Indonesia)', 'pro-currency-switcher'),
            'PHP' => __('Philippine Peso (Philippines)', 'pro-currency-switcher'),
            'VND' => __('Vietnamese Dong (Vietnam)', 'pro-currency-switcher'),
            'INR' => __('Indian Rupee (India)', 'pro-currency-switcher'),
            'BDT' => __('Bangladeshi Taka (Bangladesh)', 'pro-currency-switcher'),
            'PKR' => __('Pakistani Rupee (Pakistan)', 'pro-currency-switcher'),
            'LKR' => __('Sri Lankan Rupee (Sri Lanka)', 'pro-currency-switcher'),
            'NPR' => __('Nepalese Rupee (Nepal)', 'pro-currency-switcher'),
            'MMK' => __('Myanmar Kyat (Myanmar)', 'pro-currency-switcher'),
            'KHR' => __('Cambodian Riel (Cambodia)', 'pro-currency-switcher'),
            'LAK' => __('Lao Kip (Laos)', 'pro-currency-switcher'),
            'BND' => __('Brunei Dollar (Brunei)', 'pro-currency-switcher'),

            // North & South America
            'USD' => __('United States Dollar (USA)', 'pro-currency-switcher'),
            'CAD' => __('Canadian Dollar (Canada)', 'pro-currency-switcher'),
            'MXN' => __('Mexican Peso (Mexico)', 'pro-currency-switcher'),
            'BRL' => __('Brazilian Real (Brazil)', 'pro-currency-switcher'),
            'ARS' => __('Argentine Peso (Argentina)', 'pro-currency-switcher'),
            'CLP' => __('Chilean Peso (Chile)', 'pro-currency-switcher'),
            'COP' => __('Colombian Peso (Colombia)', 'pro-currency-switcher'),
            'PEN' => __('Peruvian Sol (Peru)', 'pro-currency-switcher'),

            // Europe
            'EUR' => __('Euro (European Union)', 'pro-currency-switcher'),
            'GBP' => __('British Pound (United Kingdom)', 'pro-currency-switcher'),
            'CHF' => __('Swiss Franc (Switzerland)', 'pro-currency-switcher'),
            'RUB' => __('Russian Ruble (Russia)', 'pro-currency-switcher'),
            'SEK' => __('Swedish Krona (Sweden)', 'pro-currency-switcher'),
            'NOK' => __('Norwegian Krone (Norway)', 'pro-currency-switcher'),
            'DKK' => __('Danish Krone (Denmark)', 'pro-currency-switcher'),
            'PLN' => __('Polish Zloty (Poland)', 'pro-currency-switcher'),
            'CZK' => __('Czech Koruna (Czech Republic)', 'pro-currency-switcher'),
            'HUF' => __('Hungarian Forint (Hungary)', 'pro-currency-switcher'),
            'RON' => __('Romanian Leu (Romania)', 'pro-currency-switcher'),
            'BGN' => __('Bulgarian Lev (Bulgaria)', 'pro-currency-switcher'),
            'HRK' => __('Croatian Kuna (Croatia)', 'pro-currency-switcher'),
            'TRY' => __('Turkish Lira (Turkey)', 'pro-currency-switcher'),
            'UAH' => __('Ukrainian Hryvnia (Ukraine)', 'pro-currency-switcher'),

            // Africa & Oceania
            'AUD' => __('Australian Dollar (Australia)', 'pro-currency-switcher'),
            'NZD' => __('New Zealand Dollar (New Zealand)', 'pro-currency-switcher'),
            'ZAR' => __('South African Rand (South Africa)', 'pro-currency-switcher'),
            'EGP' => __('Egyptian Pound (Egypt)', 'pro-currency-switcher'),
            'NGN' => __('Nigerian Naira (Nigeria)', 'pro-currency-switcher'),
            'KES' => __('Kenyan Shilling (Kenya)', 'pro-currency-switcher'),
            'GHS' => __('Ghanaian Cedi (Ghana)', 'pro-currency-switcher'),
            'MAD' => __('Moroccan Dirham (Morocco)', 'pro-currency-switcher'),
            'DZD' => __('Algerian Dinar (Algeria)', 'pro-currency-switcher'),
            'TND' => __('Tunisian Dinar (Tunisia)', 'pro-currency-switcher'),

            // Middle East
            'SAR' => __('Saudi Riyal (Saudi Arabia)', 'pro-currency-switcher'),
            'AED' => __('UAE Dirham (UAE)', 'pro-currency-switcher'),
            'QAR' => __('Qatari Riyal (Qatar)', 'pro-currency-switcher'),
            'KWD' => __('Kuwaiti Dinar (Kuwait)', 'pro-currency-switcher'),
            'OMR' => __('Omani Rial (Oman)', 'pro-currency-switcher'),
            'BHD' => __('Bahraini Dinar (Bahrain)', 'pro-currency-switcher'),
            'JOD' => __('Jordanian Dinar (Jordan)', 'pro-currency-switcher'),
            'LBP' => __('Lebanese Pound (Lebanon)', 'pro-currency-switcher'),
            'ILS' => __('Israeli New Shekel (Israel)', 'pro-currency-switcher'),
        ];

        return $global_currencies;
    }

    public function admin_notices() {
        if (isset($_GET['settings-updated']) && $_GET['settings-updated']) {
            echo '<div class="notice notice-success is-dismissible">';
            echo '<p>' . __('Currency switcher settings saved!', 'pro-currency-switcher') . '</p>';
            echo '</div>';
        }
    }

    /**
     * 帮助页面回调 - 中英文双语标签页
     */
    public function help_page_callback() {
        if (!current_user_can('manage_options')) {
            wp_die(__('Insufficient permissions', 'pro-currency-switcher'));
        }
        $version = defined('PCS_VERSION') ? PCS_VERSION : 'unknown';
        ?>
        <div class="wrap">
            <h1><?php echo esc_html__('Help', 'pro-currency-switcher'); ?></h1>

            <style>
                .pcs-help-tabs { display: flex; gap: 0; margin-bottom: 0; border-bottom: 2px solid #ccd0d4; }
                .pcs-help-tab { padding: 10px 24px; cursor: pointer; font-size: 14px; font-weight: 600; border: 1px solid transparent; border-bottom: none; background: #f0f0f1; color: #646970; border-radius: 4px 4px 0 0; margin-bottom: -2px; transition: all 0.2s; }
                .pcs-help-tab:hover { background: #f6f7f7; color: #135e96; }
                .pcs-help-tab.pcs-help-tab-active { background: #fff; color: #2b6cb0; border-color: #ccd0d4; border-bottom-color: #fff; }
                .pcs-help-content { background: #fff; border: 1px solid #ccd0d4; border-top: none; padding: 30px; border-radius: 0 0 4px 4px; }
                .pcs-help-content h2 { color: #1d4ed8; font-size: 22px; margin-top: 30px; margin-bottom: 12px; padding-bottom: 8px; border-bottom: 2px solid #e2e8f0; }
                .pcs-help-content h2:first-child { margin-top: 0; }
                .pcs-help-content h3 { color: #2d3748; font-size: 17px; margin-top: 20px; margin-bottom: 8px; }
                .pcs-help-content p { line-height: 1.8; color: #4a5568; margin-bottom: 12px; }
                .pcs-help-content ul, .pcs-help-content ol { line-height: 2; color: #4a5568; padding-left: 24px; margin-bottom: 16px; }
                .pcs-help-content li { margin-bottom: 4px; }
                .pcs-help-content strong { color: #2d3748; }
                .pcs-help-content code { background: #edf2f7; padding: 2px 8px; border-radius: 4px; font-size: 13px; color: #2b6cb0; }
                .pcs-help-content .pcs-faq-item { background: #f7fafc; border-left: 4px solid #2b6cb0; padding: 16px 20px; margin-bottom: 12px; border-radius: 0 4px 4px 0; }
                .pcs-help-content .pcs-faq-q { font-weight: 700; color: #1a365d; margin-bottom: 6px; font-size: 15px; }
                .pcs-help-content .pcs-faq-a { color: #4a5568; line-height: 1.7; }
                .pcs-help-content .pcs-support-box { background: #ebf8ff; border: 1px solid #bee3f8; border-radius: 8px; padding: 20px; margin-top: 24px; }
                .pcs-help-content .pcs-support-box p { margin-bottom: 6px; }
                .pcs-help-content .pcs-version { color: #718096; font-size: 13px; }
            </style>

            <div class="pcs-help-tabs">
                <div class="pcs-help-tab pcs-help-tab-active" onclick="pcsSwitchHelpTab('zh', this)"><?php esc_html_e('Chinese Guide', 'pro-currency-switcher'); ?></div>
                <div class="pcs-help-tab" onclick="pcsSwitchHelpTab('en', this)"><?php esc_html_e('English Guide', 'pro-currency-switcher'); ?></div>
            </div>

            <!-- 中文帮助 -->
            <div id="pcs-help-zh" class="pcs-help-content">
                <h2><?php esc_html_e('Pro Currency Switcher User Guide', 'pro-currency-switcher'); ?></h2>

                <h3><?php esc_html_e('Quick Start', 'pro-currency-switcher'); ?></h3>
                <ol>
                    <li><?php esc_html_e('Install and activate the plugin', 'pro-currency-switcher'); ?></li>
                    <li><?php esc_html_e('Go to Settings > PCS Multi-Currency to configure basic parameters', 'pro-currency-switcher'); ?></li>
                    <li><?php esc_html_e('Select base currency and enabled currencies', 'pro-currency-switcher'); ?></li>
                    <li><?php esc_html_e('Enable "Auto Detect Currency" option for GeoIP detection', 'pro-currency-switcher'); ?></li>
                    <li><?php esc_html_e('Currency switcher will appear on the frontend', 'pro-currency-switcher'); ?></li>
                </ol>

                <h3><?php esc_html_e('License Activation (Pro/Enterprise)', 'pro-currency-switcher'); ?></h3>
                <ol>
                    <li><?php esc_html_e('Go to PCS Settings > License Management', 'pro-currency-switcher'); ?></li>
                    <li><?php esc_html_e('Enter your license key (format: PCS-P-XXXX-XXXX)', 'pro-currency-switcher'); ?></li>
                    <li><?php esc_html_e('Click "Activate License"', 'pro-currency-switcher'); ?></li>
                    <li><?php esc_html_e('After activation, advanced features like GeoIP auto-detection will be unlocked', 'pro-currency-switcher'); ?></li>
                </ol>

                <h3><?php esc_html_e('Features', 'pro-currency-switcher'); ?></h3>
                <ul>
                    <li><?php esc_html_e('Base Currency: Your store default pricing currency', 'pro-currency-switcher'); ?></li>
                    <li><?php esc_html_e('Enabled Currencies: Switchable currency list on frontend', 'pro-currency-switcher'); ?></li>
                    <li><?php esc_html_e('Auto Detection: Show local currency based on visitor IP (requires valid license)', 'pro-currency-switcher'); ?></li>
                    <li><?php esc_html_e('Exchange Rates: Manual input or auto-fetch via API', 'pro-currency-switcher'); ?></li>
                    <li><?php esc_html_e('Currency Switcher: Dropdown, floating button and more styles', 'pro-currency-switcher'); ?></li>
                    <li><?php esc_html_e('Cache Compatible: Auto-adapts to WP Rocket, LiteSpeed and other cache plugins', 'pro-currency-switcher'); ?></li>
                </ul>

                <h3><?php esc_html_e('FAQ', 'pro-currency-switcher'); ?></h3>
                <div class="pcs-faq-item">
                    <div class="pcs-faq-q"><?php esc_html_e('Q: Will updating the plugin lose my settings?', 'pro-currency-switcher'); ?></div>
                    <div class="pcs-faq-a"><?php esc_html_e('A: No. Updates only add new options and will not overwrite your saved settings.', 'pro-currency-switcher'); ?></div>
                </div>
                <div class="pcs-faq-item">
                    <div class="pcs-faq-q"><?php esc_html_e('Q: Will the plugin still work after license expires?', 'pro-currency-switcher'); ?></div>
                    <div class="pcs-faq-a"><?php esc_html_e('A: Yes. GeoIP auto-detection will be disabled, but manual currency switching and cookie features will continue to work.', 'pro-currency-switcher'); ?></div>
                </div>
                <div class="pcs-faq-item">
                    <div class="pcs-faq-q"><?php esc_html_e('Q: How to add custom currency?', 'pro-currency-switcher'); ?></div>
                    <div class="pcs-faq-a"><?php esc_html_e('A: Enter the currency code (e.g. JPY, GBP) in the "Enabled Currencies" list and set the exchange rate.', 'pro-currency-switcher'); ?></div>
                </div>
                <div class="pcs-faq-item">
                    <div class="pcs-faq-q"><?php esc_html_e('Q: How often are exchange rates updated?', 'pro-currency-switcher'); ?></div>
                    <div class="pcs-faq-a"><?php esc_html_e('A: API rates auto-update every 4 hours. Manual rates do not auto-update.', 'pro-currency-switcher'); ?></div>
                </div>

                <div class="pcs-support-box">
                    <p><strong><?php esc_html_e('Technical Support', 'pro-currency-switcher'); ?></strong></p>
                    <p><?php esc_html_e('Website:', 'pro-currency-switcher'); ?> <a href="https://hb.woocross.com" target="_blank">hb.woocross.com</a></p>
                    <p class="pcs-version"><?php esc_html_e('Current Version:', 'pro-currency-switcher'); ?> Free v<?php echo esc_html($version); ?></p>
                </div>
            </div>

            <!-- English Guide -->
            <div id="pcs-help-en" class="pcs-help-content" style="display:none;">
                <h2>Pro Currency Switcher User Guide</h2>

                <h3>Quick Start</h3>
                <ol>
                    <li>Install and activate the plugin</li>
                    <li>Go to Settings > PCS Multi-Currency to configure basic settings</li>
                    <li>Select your base currency and enabled currencies</li>
                    <li>Enable "Auto Detect Currency" for GeoIP-based detection (requires valid license)</li>
                    <li>The currency switcher will appear on the frontend</li>
                </ol>

                <h3>License Activation (Pro/Enterprise)</h3>
                <ol>
                    <li>Go to PCS Settings > License Management</li>
                    <li>Enter your license key (format: <code>PCS-P-XXXX-XXXX</code>)</li>
                    <li>Click "Activate License"</li>
                    <li>Once activated, GeoIP auto-detection and other premium features will be unlocked</li>
                </ol>

                <h3>Features</h3>
                <ul>
                    <li><strong>Base Currency:</strong> Your store's default pricing currency</li>
                    <li><strong>Enabled Currencies:</strong> List of switchable currencies shown on frontend</li>
                    <li><strong>Auto Detection:</strong> Automatically display local currency based on visitor IP (requires valid license)</li>
                    <li><strong>Exchange Rates:</strong> Manual input or API automatic fetching</li>
                    <li><strong>Currency Selector:</strong> Dropdown, floating button, and more styles</li>
                    <li><strong>Cache Compatibility:</strong> Auto-adapts to WP Rocket, LiteSpeed, and other cache plugins</li>
                </ul>

                <h3>FAQ</h3>
                <div class="pcs-faq-item">
                    <div class="pcs-faq-q">Q: Will updating the plugin lose my settings?</div>
                    <div class="pcs-faq-a">A: No. Updates only add new options and never overwrite your saved settings.</div>
                </div>
                <div class="pcs-faq-item">
                    <div class="pcs-faq-q">Q: Does the plugin still work after license expires?</div>
                    <div class="pcs-faq-a">A: Yes. GeoIP auto-detection will be disabled, but manual currency switching and cookies continue to work.</div>
                </div>
                <div class="pcs-faq-item">
                    <div class="pcs-faq-q">Q: How to add custom currencies?</div>
                    <div class="pcs-faq-a">A: Enter the currency code (e.g., <code>THB</code>, <code>VND</code>) in the "Enabled Currencies" list and click add.</div>
                </div>
                <div class="pcs-faq-item">
                    <div class="pcs-faq-q">Q: How often are exchange rates updated?</div>
                    <div class="pcs-faq-a">A: API rates update automatically every 4 hours. Manual rates do not auto-update.</div>
                </div>

                <div class="pcs-support-box">
                    <p><strong>Technical Support</strong></p>
                    <p>Website: <a href="https://hb.woocross.com" target="_blank">https://hb.woocross.com</a></p>
                    <p class="pcs-version">Current Version: Free v<?php echo esc_html($version); ?></p>
                </div>
            </div>
        </div>

        <script>
        function pcsSwitchHelpTab(lang, el) {
            document.querySelectorAll('.pcs-help-tab').forEach(function(tab) {
                tab.classList.remove('pcs-help-tab-active');
            });
            el.classList.add('pcs-help-tab-active');
            document.getElementById('pcs-help-zh').style.display = (lang === 'zh') ? 'block' : 'none';
            document.getElementById('pcs-help-en').style.display = (lang === 'en') ? 'block' : 'none';
        }
        </script>
        <?php
    }

    // --------------------------------------------------------
    // JS 前端调用的 AJAX handlers
    // --------------------------------------------------------

    /**
     * 切换货币启用/禁用状态
     */
    public function ajax_toggle_currency() {
        check_ajax_referer('pcs_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Insufficient permissions', 'pro-currency-switcher'));
        }

        $currency = sanitize_text_field(wp_unslash($_POST['currency'] ?? ''));
        $enabled = filter_var($_POST['enabled'] ?? false, FILTER_VALIDATE_BOOLEAN);

        $enabled_currencies = get_option('pcs_enabled_currencies', []);
        if (!is_array($enabled_currencies)) {
            $enabled_currencies = [];
        }

        if ($enabled) {
            if (!in_array($currency, $enabled_currencies)) {
                $enabled_currencies[] = $currency;
            }
        } else {
            $enabled_currencies = array_values(array_diff($enabled_currencies, [$currency]));
        }

        update_option('pcs_enabled_currencies', $enabled_currencies);
        wp_send_json_success(__('Currency status updated', 'pro-currency-switcher'));
    }

    /**
     * 设置基准货币
     */
    public function ajax_set_base_currency() {
        check_ajax_referer('pcs_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Insufficient permissions', 'pro-currency-switcher'));
        }

        $currency = sanitize_text_field(wp_unslash($_POST['currency'] ?? ''));

        update_option('pcs_base_currency', $currency);
        wp_send_json_success(__('Base currency updated', 'pro-currency-switcher'));
    }

    /**
     * 批量货币操作
     */
    public function ajax_bulk_currency_action() {
        check_ajax_referer('pcs_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Insufficient permissions', 'pro-currency-switcher'));
        }

        $operation = sanitize_text_field(wp_unslash($_POST['operation'] ?? ''));
        $currencies = isset($_POST['currencies']) ? (array) $_POST['currencies'] : [];

        if (empty($currencies)) {
            wp_send_json_error(__('No currencies selected', 'pro-currency-switcher'));
        }

        $enabled_currencies = get_option('pcs_enabled_currencies', []);
        if (!is_array($enabled_currencies)) {
            $enabled_currencies = [];
        }

        switch ($operation) {
            case 'enable':
                $enabled_currencies = array_unique(array_merge($enabled_currencies, $currencies));
                break;
            case 'disable':
                $enabled_currencies = array_values(array_diff($enabled_currencies, $currencies));
                break;
            default:
                wp_send_json_error(__('Invalid operation', 'pro-currency-switcher'));
        }

        update_option('pcs_enabled_currencies', $enabled_currencies);
        wp_send_json_success(__('Bulk operation completed', 'pro-currency-switcher'));
    }
}
