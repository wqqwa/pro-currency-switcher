<?php
/**
 * 授权管理后台页面
 *
 * 功能：
 * - 输入授权码激活
 * - 查看授权状态
 * - 一键升级到付费版（覆盖安装）
 * - 停用授权
 * - 到期后显示续费入口
 *
 * @package ProCurrencySwitcher
 * @since 7.5.0
 */

namespace ProCurrencySwitcher\License;

if (!defined('ABSPATH')) {
    exit;
}

class LicenseAdmin {

    private $manager;

    public function __construct() {
        $this->manager = LicenseManager::get_instance();

        add_action('admin_menu', [$this, 'add_submenu'], 999);
        add_action('admin_init', [$this, 'handle_activate']);
        add_action('admin_init', [$this, 'handle_deactivate']);
        add_action('admin_init', [$this, 'handle_force_verify']);
        add_action('wp_ajax_pcs_install_package', [$this, 'ajax_install_package']);
        add_action('wp_ajax_pcs_check_update', [$this, 'ajax_check_update']);
    }

    public function add_submenu(): void {
        add_submenu_page(
            'pro-currency-switcher',
            __('License Management', 'pro-currency-switcher'),
            __('License Management', 'pro-currency-switcher'),
            'manage_options',
            'pcs-license',
            [$this, 'render_page']
        );
    }

    // ==================== 表单处理 ====================

    public function handle_activate(): void {
        if (!isset($_POST['pcs_activate_license']) || !check_admin_referer('pcs_license_nonce', 'pcs_license_nonce_field')) {
            return;
        }
        if (!current_user_can('manage_options')) wp_die(__('Insufficient permissions', 'pro-currency-switcher'));

        $license_key = sanitize_text_field(wp_unslash($_POST['pcs_license_key'] ?? ''));
        if (empty($license_key)) {
            wp_redirect(admin_url('admin.php?page=pcs-license&message=empty_key'));
            exit;
        }

        $result = $this->manager->activate($license_key);
        $msg = $result['success'] ? 'activated' : 'activate_failed';
        wp_redirect(admin_url('admin.php?page=pcs-license&message=' . $msg));
        exit;
    }

    public function handle_deactivate(): void {
        if (!isset($_POST['pcs_deactivate_license']) || !check_admin_referer('pcs_license_nonce', 'pcs_license_nonce_field')) {
            return;
        }
        if (!current_user_can('manage_options')) wp_die(__('Insufficient permissions', 'pro-currency-switcher'));

        $this->manager->deactivate();
        wp_redirect(admin_url('admin.php?page=pcs-license&message=deactivated'));
        exit;
    }

    public function handle_force_verify(): void {
        if (!isset($_POST['pcs_force_verify']) || !check_admin_referer('pcs_license_nonce', 'pcs_license_nonce_field')) {
            return;
        }
        if (!current_user_can('manage_options')) wp_die(__('Insufficient permissions', 'pro-currency-switcher'));

        $result = $this->manager->force_verify();
        $msg = $result['success'] ? 'verified' : 'verify_failed';
        wp_redirect(admin_url('admin.php?page=pcs-license&message=' . $msg));
        exit;
    }

    /**
     * AJAX：一键安装付费版包
     */
    public function ajax_check_update(): void {
        check_ajax_referer('pcs_admin_nonce', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Insufficient permissions', 'pro-currency-switcher'));
        }

        $api_url = defined('PCS_API_URL') ? PCS_API_URL : 'https://hb.woocross.com/api/v1';
        $domain  = strtolower(wp_parse_url(home_url(), PHP_URL_HOST) ?? home_url());

        // 根据当前插件确定 slug
        $plugin_file = plugin_basename(PCS_PLUGIN_PATH . 'pro-currency-switcher.php');
        $slug_map = [
            'pro-currency-switcher/pro-currency-switcher.php'              => 'pro-currency-switcher',
            'pro-currency-switcher-pro-single/pro-currency-switcher.php'   => 'pro-currency-switcher-pro-single',
            'pro-currency-switcher-pro-multi/pro-currency-switcher.php'    => 'pro-currency-switcher-pro-multi',
            'pro-currency-switcher-pro-business/pro-currency-switcher.php' => 'pro-currency-switcher-pro-business',
            'pro-currency-switcher-pro/pro-currency-switcher.php'          => 'pro-currency-switcher-pro',
        ];
        $slug = $slug_map[$plugin_file] ?? 'pro-currency-switcher';

        $response = wp_remote_post($api_url . '/plugin/check-update', [
            'timeout' => 10,
            'body'    => [
                'license_key'     => $this->manager->get_license_key(),
                'slug'            => $slug,
                'current_version' => PCS_VERSION,
                'domain'          => $domain,
            ],
        ]);

        if (is_wp_error($response)) {
            wp_send_json_error($response->get_error_message());
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);

        if (!$body || empty($body['success'])) {
            $msg = $body['error'] ?? __('Update check failed', 'pro-currency-switcher');
            wp_send_json_error($msg);
        }

        wp_send_json_success($body['data']);
    }

    public function ajax_install_package(): void {
        check_ajax_referer('pcs_admin_nonce', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Insufficient permissions', 'pro-currency-switcher'));
        }

        // 获取用户选择的目标版本
        $target_slug = sanitize_text_field($_POST['target_slug'] ?? '');

        $installer = new PackageInstaller();

        // 获取下载链接（传入用户选择的slug）
        $dl_result = $installer->get_download_url(
            $this->manager->get_license_key(),
            $this->manager->get_plan(),
            $target_slug
        );

        if (!$dl_result['success']) {
            wp_send_json_error($dl_result['message']);
            return;
        }

        // 安装包
        $result = $installer->install_package($dl_result['download_url']);

        if ($result['success']) {
            wp_send_json_success($result['message']);
        } else {
            wp_send_json_error($result['message']);
        }
    }

    // ==================== 页面渲染 ====================

    public function render_page(): void {
        if (!current_user_can('manage_options')) {
            wp_die(__('Insufficient permissions', 'pro-currency-switcher'));
        }

        try {
            $is_active   = $this->manager->is_active();
            $plan        = $this->manager->get_plan();
            $plan_label  = $this->manager->get_plan_label();
            $expires_at  = $this->manager->get_expires_at();
            $license_key = $this->manager->get_license_key();
            $domain      = $this->manager->get_domain();
            $sites_used  = $this->manager->get_sites_used();
            $sites_max   = $this->manager->get_sites_max();

            // 检查是否已安装付费版（Premium目录存在）
            $is_premium_installed = is_dir(PCS_PLUGIN_PATH . 'includes/Premium');

            $this->show_notice();
            $this->render_license_content($is_active, $plan, $plan_label, $expires_at, $license_key, $domain, $sites_used, $sites_max, $is_premium_installed);
        } catch (\Throwable $e) {
            echo '<div class="notice notice-error"><p>';
            echo '<strong>' . esc_html__('License page rendering error:', 'pro-currency-switcher') . '</strong><br>';
            echo esc_html($e->getMessage());
            echo '</p></div>';
        }
    }

    private function render_license_content($is_active, $plan, $plan_label, $expires_at, $license_key, $domain, $sites_used, $sites_max, $is_premium_installed) {
        ?>
        <div class="wrap">
            <h1><?php echo esc_html__('License Management', 'pro-currency-switcher'); ?></h1>

            <?php if ($is_active && !$is_premium_installed): ?>
                <!-- 授权已激活但未安装付费版 → 显示一键升级按钮 -->
                <div class="card" style="border-left: 4px solid #2271b1; background: #f0f6fc;">
                    <h2 style="color: #2271b1;">✅ <?php echo esc_html__('License Activated', 'pro-currency-switcher'); ?> — <?php echo esc_html($plan_label); ?></h2>
                    <table class="form-table">
                        <tr>
                            <th><?php echo esc_html__('License Key:', 'pro-currency-switcher'); ?></th>
                            <td><code style="font-size:14px;padding:4px 8px;background:#f0f0f1;border-radius:4px;"><?php echo esc_html($license_key); ?></code></td>
                        </tr>
                        <tr>
                            <th><?php echo esc_html__('Licensed Site:', 'pro-currency-switcher'); ?></th>
                            <td><?php echo esc_html($domain); ?></td>
                        </tr>
                        <tr>
                            <th><?php echo esc_html__('Site Usage:', 'pro-currency-switcher'); ?></th>
                            <td><?php echo esc_html("{$sites_used} / {$sites_max}"); ?></td>
                        </tr>
                        <tr>
                            <th><?php echo esc_html__('Expires:', 'pro-currency-switcher'); ?></th>
                            <td>
                                <?php if (!empty($expires_at)):
                                    $days = ceil((strtotime($expires_at) - time()) / DAY_IN_SECONDS);
                                    $color = $days <= 7 ? '#d63638' : ($days <= 30 ? '#dba617' : '#00a32a');
                                    echo '<span style="color:' . $color . ';font-weight:bold;">';
                                    echo esc_html(date_i18n(get_option('date_format'), strtotime($expires_at)));
                                    echo ' (' . $days . ' ' . esc_html__('days', 'pro-currency-switcher') . ')</span>';
                                else: ?>
                                    <?php echo esc_html__('Lifetime', 'pro-currency-switcher'); ?>
                                <?php endif; ?>
                            </td>
                        </tr>
                    </table>

                    <!-- 版本更新检查 -->
                    <div id="pcs-update-check" style="margin-top:16px;padding:12px 16px;background:#fff8e1;border:1px solid #e6c84c;border-radius:4px;">
                        <span id="pcs-update-info">
                            <?php echo esc_html__('Current Version:', 'pro-currency-switcher'); ?>
                            <strong><?php echo esc_html(PCS_VERSION); ?></strong>
                            <span id="pcs-update-status" style="margin-left:12px;">
                                <span class="spinner is-inline" style="float:none;width:16px;height:16px;"></span>
                                <?php esc_html_e('Checking for updates...', 'pro-currency-switcher'); ?>
                            </span>
                        </span>
                        <span id="pcs-update-action" style="display:none;margin-left:12px;"></span>
                    </div>

                    <div style="margin-top:20px;padding:15px;background:#fff;border:1px solid #2271b1;border-radius:4px;">
                        <h3>🚀 <?php echo esc_html__('One-Click Upgrade to Premium', 'pro-currency-switcher'); ?></h3>
                        <p><?php echo esc_html__('Select the version you want to upgrade to, then click the button to automatically download and install. Installation takes about 10-30 seconds.', 'pro-currency-switcher'); ?></p>
                        <p>
                            <label for="pcs-target-slug" style="font-weight:600;margin-right:10px;"><?php echo esc_html__('Target Version:', 'pro-currency-switcher'); ?></label>
                            <select id="pcs-target-slug" style="min-width:260px;padding:6px 10px;">
                                <?php
                                $current_plan = $this->manager->get_plan();
                                $plan_order = ['free' => 0, 'pro-1' => 1, 'pro-3' => 2, 'pro-10' => 3, 'enterprise' => 4];
                                $current_level = $plan_order[$current_plan] ?? 0;
                                $all_plans = [
                                    ['slug' => 'pro-currency-switcher',              'plan' => 'free',       'label' => 'Free 免费版'],
                                    ['slug' => 'pro-currency-switcher-pro-single',   'plan' => 'pro-1',      'label' => 'Pro 单站版 (Single Site)'],
                                    ['slug' => 'pro-currency-switcher-pro-multi',    'plan' => 'pro-3',      'label' => 'Pro 多站版 (Multi Site)'],
                                    ['slug' => 'pro-currency-switcher-pro-business', 'plan' => 'pro-10',     'label' => 'Pro 商业版 (Business)'],
                                    ['slug' => 'pro-currency-switcher-pro',          'plan' => 'enterprise', 'label' => 'Enterprise 企业版 (Unlimited)'],
                                ];
                                foreach ($all_plans as $item):
                                    $item_level = $plan_order[$item['plan']] ?? 0;
                                    if ($item_level <= $current_level):
                                ?>
                                    <option value="<?php echo esc_attr($item['slug']); ?>"><?php echo esc_html($item['label']); ?></option>
                                <?php endif; endforeach; ?>
                            </select>
                        </p>
                        <p style="margin-top:10px;">
                            <button type="button" id="pcs-install-btn" class="button button-primary button-hero">
                                <?php echo esc_html__('Upgrade to Selected Version', 'pro-currency-switcher'); ?>
                            </button>
                            <span id="pcs-install-status" style="margin-left:10px;"></span>
                        </p>
                    </div>

                    <p style="margin-top:15px;">
                        <form method="post" style="display:inline;">
                            <?php wp_nonce_field('pcs_license_nonce', 'pcs_license_nonce_field'); ?>
                            <button type="submit" name="pcs_force_verify" class="button"><?php echo esc_html__('Re-verify', 'pro-currency-switcher'); ?></button>
                        </form>
                        <form method="post" style="display:inline;margin-left:10px;" onsubmit="return confirm('<?php echo esc_js(__('Are you sure you want to deactivate?', 'pro-currency-switcher')); ?>');">
                            <?php wp_nonce_field('pcs_license_nonce', 'pcs_license_nonce_field'); ?>
                            <button type="submit" name="pcs_deactivate_license" class="button button-link-delete"><?php echo esc_html__('Deactivate License', 'pro-currency-switcher'); ?></button>
                        </form>
                    </p>
                </div>

                <script>
                // 自动检查更新
                jQuery.post(ajaxurl, {
                    action: 'pcs_check_update',
                    nonce: '<?php echo wp_create_nonce("pcs_admin_nonce"); ?>'
                }, function(response) {
                    var statusEl = jQuery('#pcs-update-status');
                    var actionEl = jQuery('#pcs-update-action');
                    if (response.success && response.data) {
                        if (response.data.update_available) {
                            statusEl.html('<span style="color:#d63638;font-weight:bold;">🆕 <?php echo esc_js(__('New version available:', 'pro-currency-switcher')); ?> ' + response.data.new_version + '</span>');
                            actionEl.html('<a href="plugins.php" class="button button-primary" style="vertical-align:middle;"><?php echo esc_js(__('Go to Plugins Page to Update', 'pro-currency-switcher')); ?></a>').show();
                            jQuery('#pcs-update-check').css({'background':'#fcf0f1','border-color':'#d63638'});
                        } else {
                            statusEl.html('<span style="color:#00a32a;">✅ <?php echo esc_js(__('Current version is up to date', 'pro-currency-switcher')); ?></span>');
                            jQuery('#pcs-update-check').css({'background':'#f0fdf4','border-color':'#00a32a'});
                        }
                    } else {
                        statusEl.html('<span style="color:#6B7280;"><?php echo esc_js(__('Update check unavailable', 'pro-currency-switcher')); ?></span>');
                    }
                }).fail(function() {
                    jQuery('#pcs-update-status').html('<span style="color:#6B7280;"><?php echo esc_js(__('Update check unavailable', 'pro-currency-switcher')); ?></span>');
                });

                // 一键安装
                jQuery('#pcs-install-btn').on('click', function() {
                    var btn = jQuery(this);
                    var status = jQuery('#pcs-install-status');
                    var targetSlug = jQuery('#pcs-target-slug').val();
                    var targetLabel = jQuery('#pcs-target-slug option:selected').text();

                    if (!confirm('<?php echo esc_js(__('About to download and install', 'pro-currency-switcher')); ?> ' + targetLabel + '. <?php echo esc_js(__('Do not close the page during installation. Continue?', 'pro-currency-switcher')); ?>')) return;

                    btn.prop('disabled', true).text('<?php echo esc_js(__('Downloading and installing...', 'pro-currency-switcher')); ?>');
                    status.text('');

                    jQuery.post(ajaxurl, {
                        action: 'pcs_install_package',
                        nonce: '<?php echo wp_create_nonce("pcs_admin_nonce"); ?>',
                        target_slug: targetSlug
                    }, function(response) {
                        if (response.success) {
                            status.html('<span style="color:#00a32a;font-weight:bold;">✅ ' + response.data + '</span>');
                            setTimeout(function() { location.reload(); }, 2000);
                        } else {
                            status.html('<span style="color:#d63638;">❌ ' + (response.data || '<?php echo esc_js(__('Installation Failed', 'pro-currency-switcher')); ?>') + '</span>');
                            btn.prop('disabled', false).text('<?php echo esc_js(__('Retry Installation', 'pro-currency-switcher')); ?>');
                        }
                    }).fail(function() {
                        status.html('<span style="color:#d63638;">❌ <?php echo esc_js(__('Network Error', 'pro-currency-switcher')); ?></span>');
                        btn.prop('disabled', false).text('<?php echo esc_js(__('Retry Installation', 'pro-currency-switcher')); ?>');
                    });
                });
                </script>

            <?php elseif ($is_active && $is_premium_installed): ?>
                <!-- 已激活且已安装付费版 -->
                <div class="card" style="border-left: 4px solid #00a32a; background: #f0f6fc;">
                    <h2 style="color: #00a32a;">✅ <?php echo esc_html__('Premium Running', 'pro-currency-switcher'); ?> — <?php echo esc_html($plan_label); ?></h2>
                    <table class="form-table">
                        <tr><th><?php echo esc_html__('License Key:', 'pro-currency-switcher'); ?></th><td><code style="font-size:14px;padding:4px 8px;background:#f0f0f1;border-radius:4px;"><?php echo esc_html($license_key); ?></code></td></tr>
                        <tr><th><?php echo esc_html__('Plan:', 'pro-currency-switcher'); ?></th><td><strong><?php echo esc_html($plan_label); ?></strong></td></tr>
                        <tr><th><?php echo esc_html__('Site:', 'pro-currency-switcher'); ?></th><td><?php echo esc_html($domain); ?> (<?php echo esc_html("{$sites_used}/{$sites_max}"); ?>)</td></tr>
                        <tr>
                            <th><?php echo esc_html__('Expires:', 'pro-currency-switcher'); ?></th>
                            <td>
                                <?php if (!empty($expires_at)):
                                    $days = ceil((strtotime($expires_at) - time()) / DAY_IN_SECONDS);
                                    $color = $days <= 7 ? '#d63638' : ($days <= 30 ? '#dba617' : '#00a32a');
                                    echo '<span style="color:' . $color . ';font-weight:bold;">';
                                    echo esc_html(date_i18n(get_option('date_format'), strtotime($expires_at)));
                                    echo ' (' . $days . ' ' . esc_html__('days', 'pro-currency-switcher') . ')</span>';
                                else: ?>
                                    <?php echo esc_html__('Lifetime', 'pro-currency-switcher'); ?>
                                <?php endif; ?>
                            </td>
                        </tr>
                    </table>
                    <p style="margin-top:15px;">
                        <form method="post" style="display:inline;">
                            <?php wp_nonce_field('pcs_license_nonce', 'pcs_license_nonce_field'); ?>
                            <button type="submit" name="pcs_force_verify" class="button"><?php echo esc_html__('Re-verify', 'pro-currency-switcher'); ?></button>
                        </form>
                        <form method="post" style="display:inline;margin-left:10px;" onsubmit="return confirm('<?php echo esc_js(__('Are you sure? Premium features will be unavailable after deactivation.', 'pro-currency-switcher')); ?>');">
                            <?php wp_nonce_field('pcs_license_nonce', 'pcs_license_nonce_field'); ?>
                            <button type="submit" name="pcs_deactivate_license" class="button button-link-delete"><?php echo esc_html__('Deactivate License', 'pro-currency-switcher'); ?></button>
                        </form>
                    </p>
                </div>

            <?php else: ?>
                <!-- 未激活 → 输入授权码 -->
                <div class="card" style="border-left: 4px solid #dba617; background: #fcf9e8;">
                    <h2><?php echo esc_html__('Activate License', 'pro-currency-switcher'); ?></h2>
                    <p><?php echo esc_html__('Enter the license key from your purchase to unlock premium features.', 'pro-currency-switcher'); ?></p>
                    <form method="post">
                        <?php wp_nonce_field('pcs_license_nonce', 'pcs_license_nonce_field'); ?>
                        <table class="form-table">
                            <tr>
                                <th><label for="pcs_license_key"><?php echo esc_html__('License Key:', 'pro-currency-switcher'); ?></label></th>
                                <td>
                                    <input type="text" id="pcs_license_key" name="pcs_license_key"
                                           value="<?php echo esc_attr($license_key); ?>"
                                           placeholder="PCS-P-A1B2-C3D4"
                                           class="regular-text"
                                           style="font-family:monospace;font-size:16px;letter-spacing:1px;"
                                           required>
                                    <p class="description"><?php echo esc_html__('Format: PCS-P-XXXX-XXXX (Pro) / PCS-E-XXXX-XXXX (Enterprise)', 'pro-currency-switcher'); ?></p>
                                </td>
                            </tr>
                        </table>
                        <p><button type="submit" name="pcs_activate_license" class="button button-primary"><?php echo esc_html__('Activate License', 'pro-currency-switcher'); ?></button></p>
                    </form>
                </div>

                <!-- 购买链接 -->
                <div class="card" style="margin-top: 20px;">
                    <h2><?php echo esc_html__('Get License Key', 'pro-currency-switcher'); ?></h2>
                    <p><?php echo esc_html__('No license key? Choose your plan:', 'pro-currency-switcher'); ?></p>
                    <table class="widefat striped" style="max-width: 700px;">
                        <thead>
                            <tr>
                                <th><?php echo esc_html__('Plan', 'pro-currency-switcher'); ?></th>
                                <th><?php echo esc_html__('Price', 'pro-currency-switcher'); ?></th>
                                <th><?php echo esc_html__('Sites', 'pro-currency-switcher'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td><strong>Free</strong></td>
                                <td>$0</td>
                                <td>1 <?php echo esc_html__('site', 'pro-currency-switcher'); ?></td>
                            </tr>
                            <tr>
                                <td><strong>Pro Single</strong></td>
                                <td>$39/<?php echo esc_html__('year', 'pro-currency-switcher'); ?></td>
                                <td>1 <?php echo esc_html__('site', 'pro-currency-switcher'); ?></td>
                            </tr>
                            <tr>
                                <td><strong>Pro Multi</strong></td>
                                <td>$79/<?php echo esc_html__('year', 'pro-currency-switcher'); ?></td>
                                <td>3 <?php echo esc_html__('sites', 'pro-currency-switcher'); ?></td>
                            </tr>
                            <tr>
                                <td><strong>Pro Business</strong></td>
                                <td>$149/<?php echo esc_html__('year', 'pro-currency-switcher'); ?></td>
                                <td>10 <?php echo esc_html__('sites', 'pro-currency-switcher'); ?></td>
                            </tr>
                            <tr>
                                <td><strong>Enterprise</strong></td>
                                <td>$299/<?php echo esc_html__('year', 'pro-currency-switcher'); ?></td>
                                <td><?php echo esc_html__('Unlimited', 'pro-currency-switcher'); ?></td>
                            </tr>
                        </tbody>
                    </table>
                    <p style="margin-top: 10px;">
                        <a href="https://hb.woocross.com/pricing.php" class="button button-primary" target="_blank">
                            <?php echo esc_html__('Purchase Now', 'pro-currency-switcher'); ?>
                        </a>
                    </p>
                </div>
            <?php endif; ?>
        </div>

        <style>
        .card { background:#fff; border:1px solid #ccd0d4; border-radius:4px; padding:20px; margin:20px 0; }
        .card h2 { margin-top:0; }
        </style>
        <?php
    }

    private function show_notice(): void {
        $message = sanitize_text_field(wp_unslash($_GET['message'] ?? ''));
        $notices = [
            'activated'       => ['success', __('License activated successfully!', 'pro-currency-switcher')],
            'activate_failed' => ['error',   __('Activation failed. Please check your license key.', 'pro-currency-switcher')],
            'empty_key'       => ['error',   __('Please enter a license key.', 'pro-currency-switcher')],
            'deactivated'     => ['success', __('License deactivated.', 'pro-currency-switcher')],
            'verified'        => ['success', __('License verified successfully!', 'pro-currency-switcher')],
            'verify_failed'   => ['error',   __('License verification failed.', 'pro-currency-switcher')],
        ];
        if (isset($notices[$message])) {
            $type = $notices[$message][0];
            $text = $notices[$message][1];
            echo '<div class="notice notice-' . ($type === 'success' ? 'success' : 'error') . ' is-dismissible"><p>' . esc_html($text) . '</p></div>';
        }
    }
}
