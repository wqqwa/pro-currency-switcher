<?php
/**
 * 在线客服设置页面
 * 管理浮标客服组件的渠道配置、外观设置、留言管理
 *
 * @package ProCurrencySwitcher
 * @since 7.0.0
 */

namespace ProCurrencySwitcher\Admin;

if (!defined('ABSPATH')) {
    exit;
}

class ContactWidgetSettings {

    /** @var string 选项存储 key */
    private $option_key = 'pcs_contact_widget_settings';

    /** @var string 子菜单 slug */
    private $page_slug = 'pcs-contact-widget';

    /** @var string 留言表名 */
    private $messages_table = 'pcs_contact_messages';

    /** @var array 默认设置 */
    private $defaults = [
        // 国内渠道
        'qq_enabled'           => false,
        'qq_items'             => [],
        'wechat_enabled'       => false,
        'wechat_id'            => '',
        'wechat_qrcode'        => '',
        'wechat_official'      => '',
        'wechat_official_qrcode' => '',
        'wechat_miniapp'       => '',
        'wechat_miniapp_qrcode' => '',
        'phone_enabled'        => false,
        'phone_sales'          => '',
        'phone_after_sales'    => '',
        'phone_tech_support'   => '',
        'email_enabled'        => false,
        'email_address'        => '',
        'form_enabled'         => false,
        // 海外渠道
        'whatsapp_enabled'     => false,
        'whatsapp_number'      => '',
        'telegram_enabled'     => false,
        'telegram_username'    => '',
        'line_enabled'         => false,
        'line_id'              => '',
        'messenger_enabled'    => false,
        'messenger_id'         => '',
        'viber_enabled'        => false,
        'viber_number'         => '',
        'signal_enabled'       => false,
        'signal_number'        => '',
        // 外观设置
        'widget_position'      => 'bottom-right',
        'widget_icon_style'    => 'chat-bubble',
        'widget_text'          => '',
        'agent_avatar'         => '',
        'agent_name'           => '',
        'welcome_message'      => '',
        'theme_color'          => '#2271b1',
    ];

    public function __construct() {
        add_action('admin_menu', [$this, 'add_menu_page']);
        add_action('admin_init', [$this, 'handle_form_submission']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_assets']);

        // AJAX: 删除留言
        add_action('wp_ajax_pcs_delete_contact_message', [$this, 'ajax_delete_message']);
        // AJAX: 查看留言详情
        add_action('wp_ajax_pcs_view_contact_message', [$this, 'ajax_view_message']);
    }

    /**
     * 注册子菜单
     */
    public function add_menu_page() {
        add_submenu_page(
            'pro-currency-switcher',
            __('Customer Service Settings', 'pro-currency-switcher'),
            __('Customer Service', 'pro-currency-switcher'),
            'manage_options',
            $this->page_slug,
            [$this, 'render_page']
        );
    }

    /**
     * 加载后台资源
     */
    public function enqueue_admin_assets(string $hook) {
        if (strpos($hook, $this->page_slug) === false) {
            return;
        }
        wp_enqueue_media();
        wp_enqueue_style('pcs-contact-widget-admin', PCS_PLUGIN_URL . 'assets/css/contact-widget.css', [], PCS_VERSION);
        wp_enqueue_script('pcs-contact-widget-admin', PCS_PLUGIN_URL . 'assets/js/contact-widget.js', ['jquery'], PCS_VERSION, true);
    }

    /**
     * 获取设置
     */
    private function get_settings(): array {
        $saved = get_option($this->option_key, []);
        return wp_parse_args(is_array($saved) ? $saved : [], $this->defaults);
    }

    /**
     * 处理表单提交
     */
    public function handle_form_submission() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            return;
        }
        if (!isset($_POST['pcs_contact_widget_save'])) {
            return;
        }
        if (!current_user_can('manage_options')) {
            wp_die(__('Insufficient permissions', 'pro-currency-switcher'));
        }
        if (!isset($_POST['_wpnonce']) || !wp_verify_nonce($_POST['_wpnonce'], 'pcs_contact_widget_settings')) {
            wp_die(__('Security verification failed', 'pro-currency-switcher'));
        }

        $settings = $this->get_settings();
        $section = sanitize_text_field($_POST['section'] ?? 'domestic');

        switch ($section) {
            case 'domestic':
                $this->save_domestic_settings($settings);
                break;
            case 'overseas':
                $this->save_overseas_settings($settings);
                break;
            case 'appearance':
                $this->save_appearance_settings($settings);
                break;
        }

        update_option($this->option_key, $settings);

        wp_redirect(admin_url('admin.php?page=' . $this->page_slug . '&section=' . $section . '&saved=1'));
        exit;
    }

    /**
     * 保存国内渠道设置
     */
    private function save_domestic_settings(array &$settings): void {
        // QQ
        $settings['qq_enabled'] = !empty($_POST['qq_enabled']);
        $qq_items = [];
        if (!empty($_POST['qq_items']) && is_array($_POST['qq_items'])) {
            foreach ($_POST['qq_items'] as $item) {
                $label = sanitize_text_field($item['label'] ?? '');
                $number = sanitize_text_field($item['number'] ?? '');
                if (!empty($number)) {
                    $qq_items[] = ['label' => $label, 'number' => $number];
                }
            }
        }
        $settings['qq_items'] = $qq_items;

        // 微信
        $settings['wechat_enabled'] = !empty($_POST['wechat_enabled']);
        $settings['wechat_id'] = sanitize_text_field($_POST['wechat_id'] ?? '');
        $settings['wechat_qrcode'] = esc_url_raw($_POST['wechat_qrcode'] ?? '');
        $settings['wechat_official'] = sanitize_text_field($_POST['wechat_official'] ?? '');
        $settings['wechat_official_qrcode'] = esc_url_raw($_POST['wechat_official_qrcode'] ?? '');
        $settings['wechat_miniapp'] = sanitize_text_field($_POST['wechat_miniapp'] ?? '');
        $settings['wechat_miniapp_qrcode'] = esc_url_raw($_POST['wechat_miniapp_qrcode'] ?? '');

        // 电话
        $settings['phone_enabled'] = !empty($_POST['phone_enabled']);
        $settings['phone_sales'] = sanitize_text_field($_POST['phone_sales'] ?? '');
        $settings['phone_after_sales'] = sanitize_text_field($_POST['phone_after_sales'] ?? '');
        $settings['phone_tech_support'] = sanitize_text_field($_POST['phone_tech_support'] ?? '');

        // 邮箱
        $settings['email_enabled'] = !empty($_POST['email_enabled']);
        $settings['email_address'] = sanitize_email($_POST['email_address'] ?? '');

        // 留言表单
        $settings['form_enabled'] = !empty($_POST['form_enabled']);
    }

    /**
     * 保存海外渠道设置
     */
    private function save_overseas_settings(array &$settings): void {
        $settings['whatsapp_enabled'] = !empty($_POST['whatsapp_enabled']);
        $settings['whatsapp_number'] = preg_replace('/[^0-9+]/', '', sanitize_text_field($_POST['whatsapp_number'] ?? ''));

        $settings['telegram_enabled'] = !empty($_POST['telegram_enabled']);
        $settings['telegram_username'] = sanitize_text_field($_POST['telegram_username'] ?? '');

        $settings['line_enabled'] = !empty($_POST['line_enabled']);
        $settings['line_id'] = sanitize_text_field($_POST['line_id'] ?? '');

        $settings['messenger_enabled'] = !empty($_POST['messenger_enabled']);
        $settings['messenger_id'] = sanitize_text_field($_POST['messenger_id'] ?? '');

        $settings['viber_enabled'] = !empty($_POST['viber_enabled']);
        $settings['viber_number'] = preg_replace('/[^0-9+]/', '', sanitize_text_field($_POST['viber_number'] ?? ''));

        $settings['signal_enabled'] = !empty($_POST['signal_enabled']);
        $settings['signal_number'] = preg_replace('/[^0-9+]/', '', sanitize_text_field($_POST['signal_number'] ?? ''));
    }

    /**
     * 保存外观设置
     */
    private function save_appearance_settings(array &$settings): void {
        $positions = ['top-left', 'center-left', 'bottom-left', 'top-right', 'center-right', 'bottom-right'];
        $settings['widget_position'] = in_array($_POST['widget_position'] ?? '', $positions, true)
            ? sanitize_text_field($_POST['widget_position'])
            : 'bottom-right';

        $styles = ['chat-bubble', 'headset', 'message', 'support'];
        $settings['widget_icon_style'] = in_array($_POST['widget_icon_style'] ?? '', $styles, true)
            ? sanitize_text_field($_POST['widget_icon_style'])
            : 'chat-bubble';

        $settings['widget_text'] = sanitize_text_field($_POST['widget_text'] ?? '');
        $settings['agent_avatar'] = esc_url_raw($_POST['agent_avatar'] ?? '');
        $settings['agent_name'] = sanitize_text_field($_POST['agent_name'] ?? '');
        $settings['welcome_message'] = sanitize_textarea_field($_POST['welcome_message'] ?? '');
        $settings['theme_color'] = sanitize_hex_color($_POST['theme_color'] ?? '#2271b1');
    }

    /**
     * 渲染设置页面
     */
    public function render_page() {
        $settings = $this->get_settings();
        $current_section = sanitize_text_field($_GET['section'] ?? 'domestic');
        $saved = !empty($_GET['saved']);
        ?>
        <div class="wrap pcs-contact-widget-admin">
            <h1><?php esc_html_e('Customer Service Settings', 'pro-currency-switcher'); ?></h1>

            <?php if ($saved): ?>
                <div class="notice notice-success is-dismissible">
                    <p><?php esc_html_e('Settings saved.', 'pro-currency-switcher'); ?></p>
                </div>
            <?php endif; ?>

            <!-- 标签导航 -->
            <nav class="nav-tab-wrapper">
                <a href="<?php echo esc_url(admin_url('admin.php?page=' . $this->page_slug . '&section=domestic')); ?>"
                   class="nav-tab <?php echo $current_section === 'domestic' ? 'nav-tab-active' : ''; ?>">
                    <?php esc_html_e('Domestic Channels', 'pro-currency-switcher'); ?>
                </a>
                <a href="<?php echo esc_url(admin_url('admin.php?page=' . $this->page_slug . '&section=overseas')); ?>"
                   class="nav-tab <?php echo $current_section === 'overseas' ? 'nav-tab-active' : ''; ?>">
                    <?php esc_html_e('International Channels', 'pro-currency-switcher'); ?>
                </a>
                <a href="<?php echo esc_url(admin_url('admin.php?page=' . $this->page_slug . '&section=appearance')); ?>"
                   class="nav-tab <?php echo $current_section === 'appearance' ? 'nav-tab-active' : ''; ?>">
                    <?php esc_html_e('Appearance', 'pro-currency-switcher'); ?>
                </a>
                <a href="<?php echo esc_url(admin_url('admin.php?page=' . $this->page_slug . '&section=messages')); ?>"
                   class="nav-tab <?php echo $current_section === 'messages' ? 'nav-tab-active' : ''; ?>">
                    <?php esc_html_e('Messages', 'pro-currency-switcher'); ?>
                </a>
            </nav>

            <div class="pcs-settings-content">
                <?php
                switch ($current_section) {
                    case 'domestic':
                        $this->render_domestic_section($settings);
                        break;
                    case 'overseas':
                        $this->render_overseas_section($settings);
                        break;
                    case 'appearance':
                        $this->render_appearance_section($settings);
                        break;
                    case 'messages':
                        $this->render_messages_section();
                        break;
                }
                ?>
            </div>
        </div>
        <?php
    }

    /**
     * 渲染国内渠道设置
     */
    private function render_domestic_section(array $settings): void {
        ?>
        <form method="post" action="">
            <?php wp_nonce_field('pcs_contact_widget_settings'); ?>
            <input type="hidden" name="section" value="domestic">
            <input type="hidden" name="pcs_contact_widget_save" value="1">

            <!-- QQ -->
            <div class="pcs-settings-section">
                <h2><?php esc_html_e('QQ', 'pro-currency-switcher'); ?></h2>
                <table class="form-table">
                    <tr>
                        <th scope="row"><?php esc_html_e('Enable', 'pro-currency-switcher'); ?></th>
                        <td>
                            <label><input type="checkbox" name="qq_enabled" value="1" <?php checked($settings['qq_enabled']); ?>>
                            <?php esc_html_e('Enable QQ contact', 'pro-currency-switcher'); ?></label>
                        </td>
                    </tr>
                </table>
                <div class="pcs-qq-items" id="pcs-qq-items">
                    <?php if (!empty($settings['qq_items'])): ?>
                        <?php foreach ($settings['qq_items'] as $i => $item): ?>
                            <div class="pcs-repeatable-item">
                                <input type="text" name="qq_items[<?php echo $i; ?>][label]" value="<?php echo esc_attr($item['label'] ?? ''); ?>" placeholder="<?php esc_attr_e('Label (e.g. Pre-sales QQ)', 'pro-currency-switcher'); ?>" class="regular-text">
                                <input type="text" name="qq_items[<?php echo $i; ?>][number]" value="<?php echo esc_attr($item['number'] ?? ''); ?>" placeholder="<?php esc_attr_e('QQ Number', 'pro-currency-switcher'); ?>" class="regular-text" required>
                                <button type="button" class="button pcs-remove-item" title="<?php esc_attr_e('Delete', 'pro-currency-switcher'); ?>">-</button>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
                <p>
                    <button type="button" class="button" id="pcs-add-qq"><?php esc_html_e('Add QQ Number', 'pro-currency-switcher'); ?></button>
                </p>
            </div>

            <!-- 微信 -->
            <div class="pcs-settings-section">
                <h2><?php esc_html_e('WeChat', 'pro-currency-switcher'); ?></h2>
                <table class="form-table">
                    <tr>
                        <th scope="row"><?php esc_html_e('Enable', 'pro-currency-switcher'); ?></th>
                        <td>
                            <label><input type="checkbox" name="wechat_enabled" value="1" <?php checked($settings['wechat_enabled']); ?>>
                            <?php esc_html_e('Enable WeChat contact', 'pro-currency-switcher'); ?></label>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('WeChat ID', 'pro-currency-switcher'); ?></th>
                        <td>
                            <input type="text" name="wechat_id" value="<?php echo esc_attr($settings['wechat_id']); ?>" class="regular-text" placeholder="<?php esc_attr_e('WeChat ID', 'pro-currency-switcher'); ?>">
                            <p class="description"><?php esc_html_e('Displayed for users to scan and add', 'pro-currency-switcher'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('WeChat QR Code', 'pro-currency-switcher'); ?></th>
                        <td>
                            <input type="text" name="wechat_qrcode" id="wechat_qrcode" value="<?php echo esc_attr($settings['wechat_qrcode']); ?>" class="regular-text pcs-media-field">
                            <button type="button" class="button pcs-upload-media" data-target="wechat_qrcode"><?php esc_html_e('Upload Image', 'pro-currency-switcher'); ?></button>
                            <?php if (!empty($settings['wechat_qrcode'])): ?>
                                <img src="<?php echo esc_url($settings['wechat_qrcode']); ?>" class="pcs-media-preview" style="max-width:100px;max-height:100px;margin-top:5px;">
                            <?php endif; ?>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('WeChat Official Account', 'pro-currency-switcher'); ?></th>
                        <td>
                            <input type="text" name="wechat_official" value="<?php echo esc_attr($settings['wechat_official']); ?>" class="regular-text" placeholder="<?php esc_attr_e('Official Account Name', 'pro-currency-switcher'); ?>">
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('Official Account QR Code', 'pro-currency-switcher'); ?></th>
                        <td>
                            <input type="text" name="wechat_official_qrcode" id="wechat_official_qrcode" value="<?php echo esc_attr($settings['wechat_official_qrcode']); ?>" class="regular-text pcs-media-field">
                            <button type="button" class="button pcs-upload-media" data-target="wechat_official_qrcode"><?php esc_html_e('Upload Image', 'pro-currency-switcher'); ?></button>
                            <?php if (!empty($settings['wechat_official_qrcode'])): ?>
                                <img src="<?php echo esc_url($settings['wechat_official_qrcode']); ?>" class="pcs-media-preview" style="max-width:100px;max-height:100px;margin-top:5px;">
                            <?php endif; ?>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('WeChat Mini Program', 'pro-currency-switcher'); ?></th>
                        <td>
                            <input type="text" name="wechat_miniapp" value="<?php echo esc_attr($settings['wechat_miniapp']); ?>" class="regular-text" placeholder="<?php esc_attr_e('Mini Program Name', 'pro-currency-switcher'); ?>">
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('Mini Program QR Code', 'pro-currency-switcher'); ?></th>
                        <td>
                            <input type="text" name="wechat_miniapp_qrcode" id="wechat_miniapp_qrcode" value="<?php echo esc_attr($settings['wechat_miniapp_qrcode']); ?>" class="regular-text pcs-media-field">
                            <button type="button" class="button pcs-upload-media" data-target="wechat_miniapp_qrcode"><?php esc_html_e('Upload Image', 'pro-currency-switcher'); ?></button>
                            <?php if (!empty($settings['wechat_miniapp_qrcode'])): ?>
                                <img src="<?php echo esc_url($settings['wechat_miniapp_qrcode']); ?>" class="pcs-media-preview" style="max-width:100px;max-height:100px;margin-top:5px;">
                            <?php endif; ?>
                        </td>
                    </tr>
                </table>
            </div>

            <!-- 电话 -->
            <div class="pcs-settings-section">
                <h2><?php esc_html_e('Phone', 'pro-currency-switcher'); ?></h2>
                <table class="form-table">
                    <tr>
                        <th scope="row"><?php esc_html_e('Enable', 'pro-currency-switcher'); ?></th>
                        <td>
                            <label><input type="checkbox" name="phone_enabled" value="1" <?php checked($settings['phone_enabled']); ?>>
                            <?php esc_html_e('Enable phone contact', 'pro-currency-switcher'); ?></label>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('Sales Phone', 'pro-currency-switcher'); ?></th>
                        <td>
                            <input type="tel" name="phone_sales" value="<?php echo esc_attr($settings['phone_sales']); ?>" class="regular-text" placeholder="<?php esc_attr_e('Sales phone number', 'pro-currency-switcher'); ?>">
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('After-sales Phone', 'pro-currency-switcher'); ?></th>
                        <td>
                            <input type="tel" name="phone_after_sales" value="<?php echo esc_attr($settings['phone_after_sales']); ?>" class="regular-text" placeholder="<?php esc_attr_e('After-sales phone number', 'pro-currency-switcher'); ?>">
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('Tech Support Phone', 'pro-currency-switcher'); ?></th>
                        <td>
                            <input type="tel" name="phone_tech_support" value="<?php echo esc_attr($settings['phone_tech_support']); ?>" class="regular-text" placeholder="<?php esc_attr_e('Tech support phone number', 'pro-currency-switcher'); ?>">
                        </td>
                    </tr>
                </table>
            </div>

            <!-- 邮箱 -->
            <div class="pcs-settings-section">
                <h2><?php esc_html_e('Email', 'pro-currency-switcher'); ?></h2>
                <table class="form-table">
                    <tr>
                        <th scope="row"><?php esc_html_e('Enable', 'pro-currency-switcher'); ?></th>
                        <td>
                            <label><input type="checkbox" name="email_enabled" value="1" <?php checked($settings['email_enabled']); ?>>
                            <?php esc_html_e('Enable email contact', 'pro-currency-switcher'); ?></label>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('Email Address', 'pro-currency-switcher'); ?></th>
                        <td>
                            <input type="email" name="email_address" value="<?php echo esc_attr($settings['email_address']); ?>" class="regular-text" placeholder="<?php esc_attr_e('Contact email', 'pro-currency-switcher'); ?>">
                        </td>
                    </tr>
                </table>
            </div>

            <!-- 留言表单 -->
            <div class="pcs-settings-section">
                <h2><?php esc_html_e('Contact Form', 'pro-currency-switcher'); ?></h2>
                <table class="form-table">
                    <tr>
                        <th scope="row"><?php esc_html_e('Enable', 'pro-currency-switcher'); ?></th>
                        <td>
                            <label><input type="checkbox" name="form_enabled" value="1" <?php checked($settings['form_enabled']); ?>>
                            <?php esc_html_e('Enable contact form', 'pro-currency-switcher'); ?></label>
                            <p class="description"><?php esc_html_e('When enabled, users can submit messages via the form in the widget panel. Messages will be saved to the database.', 'pro-currency-switcher'); ?></p>
                        </td>
                    </tr>
                </table>
            </div>

            <?php submit_button(__('Save Domestic Channel Settings', 'pro-currency-switcher')); ?>
        </form>
        <?php
    }

    /**
     * 渲染海外渠道设置
     */
    private function render_overseas_section(array $settings): void {
        ?>
        <form method="post" action="">
            <?php wp_nonce_field('pcs_contact_widget_settings'); ?>
            <input type="hidden" name="section" value="overseas">
            <input type="hidden" name="pcs_contact_widget_save" value="1">

            <!-- WhatsApp -->
            <div class="pcs-settings-section">
                <h2><?php esc_html_e('WhatsApp', 'pro-currency-switcher'); ?></h2>
                <table class="form-table">
                    <tr>
                        <th scope="row"><?php esc_html_e('Enable', 'pro-currency-switcher'); ?></th>
                        <td>
                            <label><input type="checkbox" name="whatsapp_enabled" value="1" <?php checked($settings['whatsapp_enabled']); ?>>
                            <?php esc_html_e('Enable WhatsApp', 'pro-currency-switcher'); ?></label>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('Number', 'pro-currency-switcher'); ?></th>
                        <td>
                            <input type="text" name="whatsapp_number" value="<?php echo esc_attr($settings['whatsapp_number']); ?>" class="regular-text" placeholder="<?php esc_attr_e('International format, e.g. +8613800138000', 'pro-currency-switcher'); ?>">
                        </td>
                    </tr>
                </table>
            </div>

            <!-- Telegram -->
            <div class="pcs-settings-section">
                <h2><?php esc_html_e('Telegram', 'pro-currency-switcher'); ?></h2>
                <table class="form-table">
                    <tr>
                        <th scope="row"><?php esc_html_e('Enable', 'pro-currency-switcher'); ?></th>
                        <td>
                            <label><input type="checkbox" name="telegram_enabled" value="1" <?php checked($settings['telegram_enabled']); ?>>
                            <?php esc_html_e('Enable Telegram', 'pro-currency-switcher'); ?></label>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('Username', 'pro-currency-switcher'); ?></th>
                        <td>
                            <input type="text" name="telegram_username" value="<?php echo esc_attr($settings['telegram_username']); ?>" class="regular-text" placeholder="<?php esc_attr_e('Without @ symbol', 'pro-currency-switcher'); ?>">
                        </td>
                    </tr>
                </table>
            </div>

            <!-- Line -->
            <div class="pcs-settings-section">
                <h2><?php esc_html_e('Line', 'pro-currency-switcher'); ?></h2>
                <table class="form-table">
                    <tr>
                        <th scope="row"><?php esc_html_e('Enable', 'pro-currency-switcher'); ?></th>
                        <td>
                            <label><input type="checkbox" name="line_enabled" value="1" <?php checked($settings['line_enabled']); ?>>
                            <?php esc_html_e('Enable Line', 'pro-currency-switcher'); ?></label>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('Line ID', 'pro-currency-switcher'); ?></th>
                        <td>
                            <input type="text" name="line_id" value="<?php echo esc_attr($settings['line_id']); ?>" class="regular-text" placeholder="<?php esc_attr_e('Line ID', 'pro-currency-switcher'); ?>">
                        </td>
                    </tr>
                </table>
            </div>

            <!-- Messenger -->
            <div class="pcs-settings-section">
                <h2><?php esc_html_e('Messenger', 'pro-currency-switcher'); ?></h2>
                <table class="form-table">
                    <tr>
                        <th scope="row"><?php esc_html_e('Enable', 'pro-currency-switcher'); ?></th>
                        <td>
                            <label><input type="checkbox" name="messenger_enabled" value="1" <?php checked($settings['messenger_enabled']); ?>>
                            <?php esc_html_e('Enable Messenger', 'pro-currency-switcher'); ?></label>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('Facebook Page ID', 'pro-currency-switcher'); ?></th>
                        <td>
                            <input type="text" name="messenger_id" value="<?php echo esc_attr($settings['messenger_id']); ?>" class="regular-text" placeholder="<?php esc_attr_e('Facebook Page ID or username', 'pro-currency-switcher'); ?>">
                        </td>
                    </tr>
                </table>
            </div>

            <!-- Viber -->
            <div class="pcs-settings-section">
                <h2><?php esc_html_e('Viber', 'pro-currency-switcher'); ?></h2>
                <table class="form-table">
                    <tr>
                        <th scope="row"><?php esc_html_e('Enable', 'pro-currency-switcher'); ?></th>
                        <td>
                            <label><input type="checkbox" name="viber_enabled" value="1" <?php checked($settings['viber_enabled']); ?>>
                            <?php esc_html_e('Enable Viber', 'pro-currency-switcher'); ?></label>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('Number', 'pro-currency-switcher'); ?></th>
                        <td>
                            <input type="text" name="viber_number" value="<?php echo esc_attr($settings['viber_number']); ?>" class="regular-text" placeholder="<?php esc_attr_e('International format, e.g. +8613800138000', 'pro-currency-switcher'); ?>">
                        </td>
                    </tr>
                </table>
            </div>

            <!-- Signal -->
            <div class="pcs-settings-section">
                <h2><?php esc_html_e('Signal', 'pro-currency-switcher'); ?></h2>
                <table class="form-table">
                    <tr>
                        <th scope="row"><?php esc_html_e('Enable', 'pro-currency-switcher'); ?></th>
                        <td>
                            <label><input type="checkbox" name="signal_enabled" value="1" <?php checked($settings['signal_enabled']); ?>>
                            <?php esc_html_e('Enable Signal', 'pro-currency-switcher'); ?></label>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('Number', 'pro-currency-switcher'); ?></th>
                        <td>
                            <input type="text" name="signal_number" value="<?php echo esc_attr($settings['signal_number']); ?>" class="regular-text" placeholder="<?php esc_attr_e('International format, e.g. +8613800138000', 'pro-currency-switcher'); ?>">
                        </td>
                    </tr>
                </table>
            </div>

            <?php submit_button(__('Save International Channel Settings', 'pro-currency-switcher')); ?>
        </form>
        <?php
    }

    /**
     * 渲染外观设置
     */
    private function render_appearance_section(array $settings): void {
        $positions = [
            'top-left'      => __('Top Left', 'pro-currency-switcher'),
            'center-left'   => __('Middle Left', 'pro-currency-switcher'),
            'bottom-left'   => __('Bottom Left', 'pro-currency-switcher'),
            'top-right'     => __('Top Right', 'pro-currency-switcher'),
            'center-right'  => __('Middle Right', 'pro-currency-switcher'),
            'bottom-right'  => __('Bottom Right', 'pro-currency-switcher'),
        ];
        $icon_styles = [
            'chat-bubble' => __('Chat Bubble', 'pro-currency-switcher'),
            'headset'     => __('Headset', 'pro-currency-switcher'),
            'message'     => __('Envelope', 'pro-currency-switcher'),
            'support'     => __('Online Support', 'pro-currency-switcher'),
        ];
        ?>
        <form method="post" action="">
            <?php wp_nonce_field('pcs_contact_widget_settings'); ?>
            <input type="hidden" name="section" value="appearance">
            <input type="hidden" name="pcs_contact_widget_save" value="1">

            <table class="form-table">
                <tr>
                    <th scope="row"><?php esc_html_e('Widget Position', 'pro-currency-switcher'); ?></th>
                    <td>
                        <select name="widget_position">
                            <?php foreach ($positions as $value => $label): ?>
                                <option value="<?php echo esc_attr($value); ?>" <?php selected($settings['widget_position'], $value); ?>>
                                    <?php echo esc_html($label); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php esc_html_e('Widget Icon', 'pro-currency-switcher'); ?></th>
                    <td>
                        <select name="widget_icon_style">
                            <?php foreach ($icon_styles as $value => $label): ?>
                                <option value="<?php echo esc_attr($value); ?>" <?php selected($settings['widget_icon_style'], $value); ?>>
                                    <?php echo esc_html($label); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <div class="pcs-icon-preview" style="margin-top:10px;">
                            <?php echo $this->get_icon_svg($settings['widget_icon_style'], 40, $settings['theme_color']); ?>
                        </div>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php esc_html_e('Widget Text', 'pro-currency-switcher'); ?></th>
                    <td>
                        <input type="text" name="widget_text" value="<?php echo esc_attr($settings['widget_text']); ?>" class="regular-text" placeholder="<?php esc_attr_e('e.g. Live Chat', 'pro-currency-switcher'); ?>">
                        <p class="description"><?php esc_html_e('Leave empty to hide text', 'pro-currency-switcher'); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php esc_html_e('Agent Avatar', 'pro-currency-switcher'); ?></th>
                    <td>
                        <input type="text" name="agent_avatar" id="agent_avatar" value="<?php echo esc_attr($settings['agent_avatar']); ?>" class="regular-text pcs-media-field">
                        <button type="button" class="button pcs-upload-media" data-target="agent_avatar"><?php esc_html_e('Upload Image', 'pro-currency-switcher'); ?></button>
                        <?php if (!empty($settings['agent_avatar'])): ?>
                            <img src="<?php echo esc_url($settings['agent_avatar']); ?>" class="pcs-media-preview" style="max-width:60px;max-height:60px;margin-top:5px;border-radius:50%;">
                        <?php endif; ?>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php esc_html_e('Agent Name', 'pro-currency-switcher'); ?></th>
                    <td>
                        <input type="text" name="agent_name" value="<?php echo esc_attr($settings['agent_name']); ?>" class="regular-text" placeholder="<?php esc_attr_e('Agent Name', 'pro-currency-switcher'); ?>">
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php esc_html_e('Welcome Message', 'pro-currency-switcher'); ?></th>
                    <td>
                        <textarea name="welcome_message" rows="3" class="large-text" placeholder="<?php esc_attr_e('Hello! How can we help you?', 'pro-currency-switcher'); ?>"><?php echo esc_textarea($settings['welcome_message']); ?></textarea>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php esc_html_e('Theme Color', 'pro-currency-switcher'); ?></th>
                    <td>
                        <input type="color" name="theme_color" value="<?php echo esc_attr($settings['theme_color']); ?>" id="pcs-theme-color">
                        <span id="pcs-theme-color-hex"><?php echo esc_html($settings['theme_color']); ?></span>
                    </td>
                </tr>
            </table>

            <?php submit_button(__('Save Appearance Settings', 'pro-currency-switcher')); ?>
        </form>
        <?php
    }

    /**
     * 渲染留言管理
     */
    private function render_messages_section(): void {
        global $wpdb;
        $table = $wpdb->prefix . $this->messages_table;

        // 确保表存在
        $this->ensure_messages_table();

        // 分页
        $per_page = 20;
        $current_page = max(1, intval($_GET['paged'] ?? 1));
        $offset = ($current_page - 1) * $per_page;

        $total = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$table}");
        $messages = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$table} ORDER BY created_at DESC LIMIT %d OFFSET %d",
            $per_page,
            $offset
        ));
        ?>
        <div class="pcs-messages-section">
            <h2><?php esc_html_e('Message List', 'pro-currency-switcher'); ?>
                <span class="pcs-message-count">(<?php echo esc_html($total); ?>)</span>
            </h2>

            <?php if (empty($messages)): ?>
                <p><?php esc_html_e('No messages yet.', 'pro-currency-switcher'); ?></p>
            <?php else: ?>
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th style="width:40px;"><?php esc_html_e('ID', 'pro-currency-switcher'); ?></th>
                            <th><?php esc_html_e('Name', 'pro-currency-switcher'); ?></th>
                            <th><?php esc_html_e('Email', 'pro-currency-switcher'); ?></th>
                            <th><?php esc_html_e('Phone', 'pro-currency-switcher'); ?></th>
                            <th><?php esc_html_e('Message', 'pro-currency-switcher'); ?></th>
                            <th style="width:160px;"><?php esc_html_e('Time', 'pro-currency-switcher'); ?></th>
                            <th style="width:80px;"><?php esc_html_e('Action', 'pro-currency-switcher'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($messages as $msg): ?>
                            <tr>
                                <td><?php echo esc_html($msg->id); ?></td>
                                <td><?php echo esc_html($msg->name); ?></td>
                                <td><?php echo esc_html($msg->email); ?></td>
                                <td><?php echo esc_html($msg->phone); ?></td>
                                <td>
                                    <button type="button" class="button button-small pcs-view-message" data-id="<?php echo esc_attr($msg->id); ?>">
                                        <?php esc_html_e('View', 'pro-currency-switcher'); ?>
                                    </button>
                                </td>
                                <td><?php echo esc_html($msg->created_at); ?></td>
                                <td>
                                    <button type="button" class="button button-small pcs-delete-message" data-id="<?php echo esc_attr($msg->id); ?>" data-nonce="<?php echo esc_attr(wp_create_nonce('pcs_delete_message_' . $msg->id)); ?>">
                                        <?php esc_html_e('Delete', 'pro-currency-switcher'); ?>
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>

                <!-- 分页 -->
                <?php if ($total > $per_page): ?>
                    <div class="tablenav bottom">
                        <div class="tablenav-pages">
                            <?php
                            echo paginate_links([
                                'base'      => add_query_arg('paged', '%#%'),
                                'format'    => '',
                                'total'     => ceil($total / $per_page),
                                'current'   => $current_page,
                                'prev_text' => '&laquo;',
                                'next_text' => '&raquo;',
                            ]);
                            ?>
                        </div>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>

        <!-- 留言详情弹窗 -->
        <div id="pcs-message-modal" style="display:none;">
            <div class="pcs-modal-backdrop"></div>
            <div class="pcs-modal-content">
                <div class="pcs-modal-header">
                    <h3><?php esc_html_e('Message Details', 'pro-currency-switcher'); ?></h3>
                    <button type="button" class="pcs-modal-close">&times;</button>
                </div>
                <div class="pcs-modal-body" id="pcs-message-detail"></div>
            </div>
        </div>
        <?php
    }

    /**
     * AJAX: 查看留言详情
     */
    public function ajax_view_message(): void {
        check_ajax_referer('pcs_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Insufficient permissions', 'pro-currency-switcher')]);
            return;
        }

        $id = intval($_POST['message_id'] ?? 0);
        if ($id <= 0) {
            wp_send_json_error(['message' => __('Invalid message ID', 'pro-currency-switcher')]);
            return;
        }

        global $wpdb;
        $table = $wpdb->prefix . $this->messages_table;
        $msg = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE id = %d", $id));

        if (!$msg) {
            wp_send_json_error(['message' => __('Message not found', 'pro-currency-switcher')]);
            return;
        }

        wp_send_json_success([
            'id'         => intval($msg->id),
            'name'       => esc_html($msg->name),
            'email'      => esc_html($msg->email),
            'phone'      => esc_html($msg->phone),
            'message'    => esc_html($msg->message),
            'created_at' => esc_html($msg->created_at),
        ]);
    }

    /**
     * AJAX: 删除留言
     */
    public function ajax_delete_message(): void {
        $id = intval($_POST['message_id'] ?? 0);
        if ($id <= 0) {
            wp_send_json_error(['message' => __('Invalid message ID', 'pro-currency-switcher')]);
            return;
        }

        check_ajax_referer('pcs_delete_message_' . $id, 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Insufficient permissions', 'pro-currency-switcher')]);
            return;
        }

        global $wpdb;
        $table = $wpdb->prefix . $this->messages_table;
        $wpdb->delete($table, ['id' => $id], ['%d']);

        wp_send_json_success(['message' => __('Message deleted', 'pro-currency-switcher')]);
    }

    /**
     * 确保留言表存在
     */
    public static function ensure_messages_table(): void {
        global $wpdb;
        $table = $wpdb->prefix . 'pcs_contact_messages';
        $charset = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE {$table} (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            name varchar(100) NOT NULL DEFAULT '',
            email varchar(100) NOT NULL DEFAULT '',
            phone varchar(50) NOT NULL DEFAULT '',
            message text NOT NULL,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY created_at (created_at)
        ) $charset;";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql);
    }

    /**
     * 获取图标 SVG
     */
    private function get_icon_svg(string $style, int $size = 24, string $color = '#2271b1'): string {
        $icons = [
            'chat-bubble' => '<svg width="' . $size . '" height="' . $size . '" viewBox="0 0 24 24" fill="' . $color . '"><path d="M20 2H4c-1.1 0-2 .9-2 2v18l4-4h14c1.1 0 2-.9 2-2V4c0-1.1-.9-2-2-2zm0 14H6l-2 2V4h16v12z"/></svg>',
            'headset' => '<svg width="' . $size . '" height="' . $size . '" viewBox="0 0 24 24" fill="' . $color . '"><path d="M12 1c-4.97 0-9 4.03-9 9v7c0 1.66 1.34 3 3 3h3v-8H5v-2c0-3.87 3.13-7 7-7s7 3.13 7 7v2h-4v8h3c1.66 0 3-1.34 3-3v-7c0-4.97-4.03-9-9-9z"/></svg>',
            'message' => '<svg width="' . $size . '" height="' . $size . '" viewBox="0 0 24 24" fill="' . $color . '"><path d="M20 4H4c-1.1 0-1.99.9-1.99 2L2 18c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V6c0-1.1-.9-2-2-2zm0 4l-8 5-8-5V6l8 5 8-5v2z"/></svg>',
            'support' => '<svg width="' . $size . '" height="' . $size . '" viewBox="0 0 24 24" fill="' . $color . '"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 17h-2v-2h2v2zm2.07-7.75l-.9.92C13.45 12.9 13 13.5 13 15h-2v-.5c0-1.1.45-2.1 1.17-2.83l1.24-1.26c.37-.36.59-.86.59-1.41 0-1.1-.9-2-2-2s-2 .9-2 2H8c0-2.21 1.79-4 4-4s4 1.79 4 4c0 .88-.36 1.68-.93 2.25z"/></svg>',
        ];
        return $icons[$style] ?? $icons['chat-bubble'];
    }
}
