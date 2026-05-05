<?php
/**
 * 在线客服前端组件
 * 在非后台页面渲染浮标客服面板
 *
 * @package ProCurrencySwitcher
 * @since 7.0.0
 */

namespace ProCurrencySwitcher\Frontend;

if (!defined('ABSPATH')) {
    exit;
}

class ContactWidget {

    /** @var string 选项存储 key */
    private $option_key = 'pcs_contact_widget_settings';

    public function __construct() {
        // 只在非后台页面显示
        if (is_admin()) {
            return;
        }

        // 注册 AJAX 留言提交（登录和未登录用户均可）
        add_action('wp_ajax_pcs_submit_contact_form', [$this, 'ajax_submit_contact_form']);
        add_action('wp_ajax_nopriv_pcs_submit_contact_form', [$this, 'ajax_submit_contact_form']);

        // 在页面底部输出浮标 HTML
        add_action('wp_footer', [$this, 'render_widget'], 99);

        // 加载前端资源
        add_action('wp_enqueue_scripts', [$this, 'enqueue_assets']);
    }

    /**
     * 加载前端 CSS/JS
     */
    public function enqueue_assets(): void {
        $settings = $this->get_settings();
        if (!$this->has_active_channels($settings)) {
            return;
        }

        wp_enqueue_style('pcs-contact-widget', PCS_PLUGIN_URL . 'assets/css/contact-widget.css', [], PCS_VERSION);
        wp_enqueue_script('pcs-contact-widget', PCS_PLUGIN_URL . 'assets/js/contact-widget.js', [], PCS_VERSION, true);

        wp_localize_script('pcs-contact-widget', 'pcs_contact', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce'    => wp_create_nonce('pcs_contact_nonce'),
            'i18n'     => [
                'submitting' => __('Submitting...', 'pro-currency-switcher'),
                'success'    => __('Message submitted successfully! We will reply as soon as possible.', 'pro-currency-switcher'),
                'error'      => __('Submission failed, please try again later.', 'pro-currency-switcher'),
                'required'   => __('Please fill in the required fields', 'pro-currency-switcher'),
            ],
        ]);
    }

    /**
     * 获取设置
     */
    private function get_settings(): array {
        $saved = get_option($this->option_key, []);
        $defaults = [
            'qq_enabled' => false, 'qq_items' => [],
            'wechat_enabled' => false, 'wechat_id' => '', 'wechat_qrcode' => '',
            'wechat_official' => '', 'wechat_official_qrcode' => '',
            'wechat_miniapp' => '', 'wechat_miniapp_qrcode' => '',
            'phone_enabled' => false, 'phone_sales' => '', 'phone_after_sales' => '', 'phone_tech_support' => '',
            'email_enabled' => false, 'email_address' => '',
            'form_enabled' => false,
            'whatsapp_enabled' => false, 'whatsapp_number' => '',
            'telegram_enabled' => false, 'telegram_username' => '',
            'line_enabled' => false, 'line_id' => '',
            'messenger_enabled' => false, 'messenger_id' => '',
            'viber_enabled' => false, 'viber_number' => '',
            'signal_enabled' => false, 'signal_number' => '',
            'widget_position' => 'bottom-right',
            'widget_icon_style' => 'chat-bubble',
            'widget_text' => '',
            'agent_avatar' => '',
            'agent_name' => '',
            'welcome_message' => '',
            'theme_color' => '#2271b1',
        ];
        return wp_parse_args(is_array($saved) ? $saved : [], $defaults);
    }

    /**
     * 检查是否有已启用的渠道
     */
    private function has_active_channels(array $settings): bool {
        // 国内渠道
        if (!empty($settings['qq_enabled']) && !empty($settings['qq_items'])) return true;
        if (!empty($settings['wechat_enabled']) && (!empty($settings['wechat_id']) || !empty($settings['wechat_qrcode']))) return true;
        if (!empty($settings['phone_enabled']) && (!empty($settings['phone_sales']) || !empty($settings['phone_after_sales']) || !empty($settings['phone_tech_support']))) return true;
        if (!empty($settings['email_enabled']) && !empty($settings['email_address'])) return true;
        if (!empty($settings['form_enabled'])) return true;

        // 海外渠道
        if (!empty($settings['whatsapp_enabled']) && !empty($settings['whatsapp_number'])) return true;
        if (!empty($settings['telegram_enabled']) && !empty($settings['telegram_username'])) return true;
        if (!empty($settings['line_enabled']) && !empty($settings['line_id'])) return true;
        if (!empty($settings['messenger_enabled']) && !empty($settings['messenger_id'])) return true;
        if (!empty($settings['viber_enabled']) && !empty($settings['viber_number'])) return true;
        if (!empty($settings['signal_enabled']) && !empty($settings['signal_number'])) return true;

        return false;
    }

    /**
     * 渲染浮标组件
     */
    public function render_widget(): void {
        $settings = $this->get_settings();
        if (!$this->has_active_channels($settings)) {
            return;
        }

        $position = sanitize_html_class($settings['widget_position']);
        $icon_style = sanitize_html_class($settings['widget_icon_style']);
        $theme_color = esc_attr($settings['theme_color']);
        $widget_text = esc_html($settings['widget_text']);
        $agent_avatar = esc_url($settings['agent_avatar']);
        $agent_name = esc_html($settings['agent_name']);
        $welcome_message = esc_html($settings['welcome_message']);

        // 构建渠道列表
        $channels = $this->build_channels($settings);
        ?>
        <div class="pcs-contact-widget pcs-position-<?php echo $position; ?>" id="pcs-contact-widget" data-theme-color="<?php echo $theme_color; ?>">
            <!-- 浮标按钮 -->
            <div class="pcs-cw-fab" id="pcs-cw-fab" style="background-color: <?php echo $theme_color; ?>;">
                <?php echo $this->get_icon_svg($icon_style, 28, '#ffffff'); ?>
                <?php if (!empty($widget_text)): ?>
                    <span class="pcs-cw-fab-text"><?php echo $widget_text; ?></span>
                <?php endif; ?>
                <span class="pcs-cw-fab-badge" id="pcs-cw-fab-close" style="display:none;">&times;</span>
            </div>

            <!-- 面板 -->
            <div class="pcs-cw-panel" id="pcs-cw-panel" style="display:none;">
                <!-- 面板头部 -->
                <div class="pcs-cw-panel-header" style="background-color: <?php echo $theme_color; ?>;">
                    <?php if (!empty($agent_avatar)): ?>
                        <img src="<?php echo $agent_avatar; ?>" alt="<?php echo $agent_name; ?>" class="pcs-cw-agent-avatar">
                    <?php endif; ?>
                    <div class="pcs-cw-agent-info">
                        <?php if (!empty($agent_name)): ?>
                            <div class="pcs-cw-agent-name"><?php echo $agent_name; ?></div>
                        <?php endif; ?>
                        <?php if (!empty($welcome_message)): ?>
                            <div class="pcs-cw-welcome-msg"><?php echo $welcome_message; ?></div>
                        <?php endif; ?>
                    </div>
                    <button type="button" class="pcs-cw-panel-close" id="pcs-cw-panel-close">&times;</button>
                </div>

                <!-- 渠道列表 -->
                <div class="pcs-cw-channels">
                    <?php foreach ($channels as $channel): ?>
                        <?php echo $this->render_channel_item($channel, $theme_color); ?>
                    <?php endforeach; ?>
                </div>

                <!-- 留言表单 -->
                <?php if (!empty($settings['form_enabled'])): ?>
                    <div class="pcs-cw-form-section" id="pcs-cw-form-section" style="display:none;">
                        <div class="pcs-cw-form-toggle" id="pcs-cw-form-toggle" style="background-color: <?php echo $theme_color; ?>;">
                            <?php esc_html_e('Leave a Message', 'pro-currency-switcher'); ?>
                        </div>
                        <form id="pcs-cw-contact-form" class="pcs-cw-contact-form">
                            <div class="pcs-cw-form-field">
                                <input type="text" name="name" required placeholder="<?php esc_attr_e('Your Name *', 'pro-currency-switcher'); ?>">
                            </div>
                            <div class="pcs-cw-form-field">
                                <input type="email" name="email" required placeholder="<?php esc_attr_e('Email Address *', 'pro-currency-switcher'); ?>">
                            </div>
                            <div class="pcs-cw-form-field">
                                <input type="tel" name="phone" placeholder="<?php esc_attr_e('Phone Number', 'pro-currency-switcher'); ?>">
                            </div>
                            <div class="pcs-cw-form-field">
                                <textarea name="message" rows="3" required placeholder="<?php esc_attr_e('Your Message *', 'pro-currency-switcher'); ?>"></textarea>
                            </div>
                            <button type="submit" class="pcs-cw-form-submit" style="background-color: <?php echo $theme_color; ?>;">
                                <?php esc_html_e('Submit', 'pro-currency-switcher'); ?>
                            </button>
                        </form>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        <?php
    }

    /**
     * 构建渠道数据
     */
    private function build_channels(array $settings): array {
        $channels = [];

        // QQ
        if (!empty($settings['qq_enabled']) && !empty($settings['qq_items'])) {
            foreach ($settings['qq_items'] as $item) {
                $label = !empty($item['label']) ? $item['label'] : __('QQ', 'pro-currency-switcher');
                $channels[] = [
                    'type'  => 'qq',
                    'label' => $label,
                    'url'   => 'tencent://message/?uin=' . urlencode($item['number']) . '&Site=&Menu=yes',
                ];
            }
        }

        // 微信
        if (!empty($settings['wechat_enabled'])) {
            // 微信号（有二维码则弹窗，无二维码则显示微信号）
            if (!empty($settings['wechat_id']) || !empty($settings['wechat_qrcode'])) {
                $channels[] = [
                    'type'   => 'wechat',
                    'label'  => !empty($settings['wechat_id']) ? $settings['wechat_id'] : __('WeChat', 'pro-currency-switcher'),
                    'qrcode' => $settings['wechat_qrcode'] ?? '',
                ];
            }
            if (!empty($settings['wechat_official']) || !empty($settings['wechat_official_qrcode'])) {
                $channels[] = [
                    'type'   => 'wechat-official',
                    'label'  => !empty($settings['wechat_official']) ? $settings['wechat_official'] : __('WeChat Official Account', 'pro-currency-switcher'),
                    'qrcode' => $settings['wechat_official_qrcode'] ?? '',
                ];
            }
            if (!empty($settings['wechat_miniapp']) || !empty($settings['wechat_miniapp_qrcode'])) {
                $channels[] = [
                    'type'   => 'wechat-miniapp',
                    'label'  => !empty($settings['wechat_miniapp']) ? $settings['wechat_miniapp'] : __('WeChat Mini Program', 'pro-currency-switcher'),
                    'qrcode' => $settings['wechat_miniapp_qrcode'] ?? '',
                ];
            }
        }

        // 电话
        if (!empty($settings['phone_enabled'])) {
            $phone_numbers = [
                ['label' => __('Sales Phone', 'pro-currency-switcher'), 'number' => $settings['phone_sales']],
                ['label' => __('After-sales Phone', 'pro-currency-switcher'), 'number' => $settings['phone_after_sales']],
                ['label' => __('Tech Support', 'pro-currency-switcher'), 'number' => $settings['phone_tech_support']],
            ];
            foreach ($phone_numbers as $p) {
                if (!empty($p['number'])) {
                    $channels[] = [
                        'type'  => 'phone',
                        'label' => $p['label'],
                        'url'   => 'tel:' . $p['number'],
                    ];
                }
            }
        }

        // 邮箱
        if (!empty($settings['email_enabled']) && !empty($settings['email_address'])) {
            $channels[] = [
                'type'  => 'email',
                'label' => __('Email', 'pro-currency-switcher'),
                'url'   => 'mailto:' . $settings['email_address'],
            ];
        }

        // 留言表单（作为特殊渠道，不在这里渲染，由 JS 控制）
        // 不添加到 channels，由面板中的表单区域单独处理

        // WhatsApp
        if (!empty($settings['whatsapp_enabled']) && !empty($settings['whatsapp_number'])) {
            $channels[] = [
                'type'  => 'whatsapp',
                'label' => 'WhatsApp',
                'url'   => 'https://wa.me/' . preg_replace('/[^0-9]/', '', $settings['whatsapp_number']),
            ];
        }

        // Telegram
        if (!empty($settings['telegram_enabled']) && !empty($settings['telegram_username'])) {
            $channels[] = [
                'type'  => 'telegram',
                'label' => 'Telegram',
                'url'   => 'https://t.me/' . $settings['telegram_username'],
            ];
        }

        // Line
        if (!empty($settings['line_enabled']) && !empty($settings['line_id'])) {
            $channels[] = [
                'type'  => 'line',
                'label' => 'Line',
                'url'   => 'https://line.me/ti/p/~' . $settings['line_id'],
            ];
        }

        // Messenger
        if (!empty($settings['messenger_enabled']) && !empty($settings['messenger_id'])) {
            $channels[] = [
                'type'  => 'messenger',
                'label' => 'Messenger',
                'url'   => 'https://m.me/' . $settings['messenger_id'],
            ];
        }

        // Viber
        if (!empty($settings['viber_enabled']) && !empty($settings['viber_number'])) {
            $channels[] = [
                'type'  => 'viber',
                'label' => 'Viber',
                'url'   => 'viber://chat?number=' . urlencode($settings['viber_number']),
            ];
        }

        // Signal
        if (!empty($settings['signal_enabled']) && !empty($settings['signal_number'])) {
            $channels[] = [
                'type'  => 'signal',
                'label' => 'Signal',
                'url'   => 'https://signal.me/#p/' . urlencode($settings['signal_number']),
            ];
        }

        return $channels;
    }

    /**
     * 渲染单个渠道项
     */
    private function render_channel_item(array $channel, string $theme_color): string {
        $icon = $this->get_channel_icon($channel['type'], 24, $theme_color);
        $label = esc_html($channel['label']);

        // 微信类型：有二维码则弹窗，无二维码则显示微信号
        if (in_array($channel['type'], ['wechat', 'wechat-official', 'wechat-miniapp'], true)) {
            if (!empty($channel['qrcode'])) {
                return '<div class="pcs-cw-channel pcs-cw-channel-qrcode" data-qrcode="' . esc_url($channel['qrcode']) . '">'
                    . $icon
                    . '<span class="pcs-cw-channel-label">' . $label . '</span>'
                    . '</div>';
            } else {
                // 无二维码，显示微信号（不可点击）
                return '<div class="pcs-cw-channel pcs-cw-channel-info">'
                    . $icon
                    . '<span class="pcs-cw-channel-label">' . $label . '</span>'
                    . '</div>';
            }
        }

        $url = $channel['url'];
        // 对自定义协议（tencent://, viber:// 等）不做 esc_url 过滤，因为 esc_url 会清除非标准协议
        $allowed_protocols = ['http', 'https', 'ftp', 'ftps', 'mailto', 'tel', 'tencent', 'viber'];
        $url = esc_url($url, $allowed_protocols);
        $target = in_array($channel['type'], ['phone', 'email'], true) ? '' : ' target="_blank" rel="noopener noreferrer"';

        return '<a href="' . $url . '" class="pcs-cw-channel pcs-cw-channel-link"' . $target . '>'
            . $icon
            . '<span class="pcs-cw-channel-label">' . $label . '</span>'
            . '</a>';
    }

    /**
     * 获取渠道图标 SVG
     */
    private function get_channel_icon(string $type, int $size = 24, string $color = '#2271b1'): string {
        $icons = [
            // QQ 企鹅图标
            'qq' => '<svg width="' . $size . '" height="' . $size . '" viewBox="0 0 24 24" fill="' . $color . '"><path d="M12 2C8.13 2 5 5.13 5 9c0 1.93.78 3.68 2.05 4.95L5 18.1c-.07.28.14.47.39.36l3.17-1.44c.93.31 1.93.48 2.97.48h.44c3.87 0 7-3.13 7-7s-3.13-7-7-7h-.97zM9.5 8.5a1 1 0 110-2 1 1 0 010 2zm5 0a1 1 0 110-2 1 1 0 010 2zm-2.5 4c-1.1 0-2-.67-2-1.5h4c0 .83-.9 1.5-2 1.5z"/></svg>',
            // 微信图标
            'wechat' => '<svg width="' . $size . '" height="' . $size . '" viewBox="0 0 24 24" fill="' . $color . '"><path d="M8.691 2.188C3.891 2.188 0 5.476 0 9.53c0 2.212 1.17 4.203 3.002 5.55a.59.59 0 0 1 .213.665l-.39 1.48c-.019.07-.048.141-.048.213 0 .163.13.295.29.295a.326.326 0 0 0 .167-.054l1.903-1.114a.864.864 0 0 1 .717-.098 10.16 10.16 0 0 0 2.837.403c.276 0 .543-.027.811-.05-.857-2.578.157-4.972 1.932-6.446 1.703-1.415 3.882-1.98 5.853-1.838-.576-3.583-4.196-6.348-8.596-6.348zM5.785 5.991c.642 0 1.162.529 1.162 1.18a1.17 1.17 0 0 1-1.162 1.178A1.17 1.17 0 0 1 4.623 7.17c0-.651.52-1.18 1.162-1.18zm5.813 0c.642 0 1.162.529 1.162 1.18a1.17 1.17 0 0 1-1.162 1.178 1.17 1.17 0 0 1-1.162-1.178c0-.651.52-1.18 1.162-1.18zm3.2 4.127c-2.614 0-4.796 1.38-5.715 3.26-.928 1.9-.786 4.15.393 5.798 1.174 1.642 3.063 2.596 5.322 2.596.59 0 1.163-.085 1.705-.244a.523.523 0 0 1 .434.06l1.158.677a.2.2 0 0 0 .1.033.178.178 0 0 0 .177-.18c0-.044-.018-.087-.029-.13l-.237-.9a.358.358 0 0 1 .13-.405c1.29-.958 2.09-2.395 2.09-3.985 0-3.583-2.756-6.48-5.528-6.48zm-2.07 3.285c.39 0 .707.322.707.718a.713.713 0 0 1-.707.717.713.713 0 0 1-.707-.717c0-.396.317-.718.707-.718zm4.14 0c.39 0 .707.322.707.718a.713.713 0 0 1-.707.717.713.713 0 0 1-.707-.717c0-.396.317-.718.707-.718z"/></svg>',
            // 微信公众号图标
            'wechat-official' => '<svg width="' . $size . '" height="' . $size . '" viewBox="0 0 24 24" fill="' . $color . '"><path d="M20 2H4c-1.1 0-2 .9-2 2v18l4-4h14c1.1 0 2-.9 2-2V4c0-1.1-.9-2-2-2zm0 14H6l-2 2V4h16v12z"/><path d="M7 9h10v2H7zm0 4h7v2H7z"/></svg>',
            // 微信小程序图标
            'wechat-miniapp' => '<svg width="' . $size . '" height="' . $size . '" viewBox="0 0 24 24" fill="' . $color . '"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm0 18c-4.42 0-8-3.58-8-8s3.58-8 8-8 8 3.58 8 8-3.58 8-8 8z"/><path d="M12 6v6l4 2"/></svg>',
            // 电话图标
            'phone' => '<svg width="' . $size . '" height="' . $size . '" viewBox="0 0 24 24" fill="' . $color . '"><path d="M6.62 10.79c1.44 2.83 3.76 5.14 6.59 6.59l2.2-2.2c.27-.27.67-.36 1.02-.24 1.12.37 2.33.57 3.57.57.55 0 1 .45 1 1V20c0 .55-.45 1-1 1-9.39 0-17-7.61-17-17 0-.55.45-1 1-1h3.5c.55 0 1 .45 1 1 0 1.25.2 2.45.57 3.57.11.35.03.74-.25 1.02l-2.2 2.2z"/></svg>',
            // 邮箱图标
            'email' => '<svg width="' . $size . '" height="' . $size . '" viewBox="0 0 24 24" fill="' . $color . '"><path d="M20 4H4c-1.1 0-1.99.9-1.99 2L2 18c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V6c0-1.1-.9-2-2-2zm0 4l-8 5-8-5V6l8 5 8-5v2z"/></svg>',
            // WhatsApp图标
            'whatsapp' => '<svg width="' . $size . '" height="' . $size . '" viewBox="0 0 24 24" fill="' . $color . '"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/></svg>',
            // Telegram图标
            'telegram' => '<svg width="' . $size . '" height="' . $size . '" viewBox="0 0 24 24" fill="' . $color . '"><path d="M9.78 18.65l.28-4.23 7.68-6.92c.34-.31-.07-.46-.52-.19L7.74 13.3 3.64 12c-.88-.25-.89-.86.2-1.3l15.97-6.16c.73-.33 1.43.18 1.15 1.3l-2.72 12.81c-.19.91-.74 1.13-1.5.71L12.6 16.3l-1.99 1.93c-.23.23-.42.42-.83.42z"/></svg>',
            // Line图标
            'line' => '<svg width="' . $size . '" height="' . $size . '" viewBox="0 0 24 24" fill="' . $color . '"><path d="M19.365 9.863c.349 0 .63.285.63.631 0 .345-.281.63-.63.63H17.61v1.125h1.755c.349 0 .63.283.63.63 0 .344-.281.629-.63.629h-2.386c-.345 0-.627-.285-.627-.629V8.108c0-.345.282-.63.63-.63h2.386c.346 0 .627.285.627.63 0 .349-.281.63-.63.63H17.61v1.125h1.755zm-3.855 3.016c0 .27-.174.51-.432.596l-5.605 1.96c-.074.026-.154.04-.232.04-.349 0-.63-.285-.63-.63V8.108c0-.345.281-.63.63-.63.349 0 .63.285.63.63v5.65l4.835-1.69c.349-.122.732.07.854.418.122.349-.07.732-.42.854v.001zM6.938 4.501c.349 0 .63.285.63.63v11.25c0 .349-.281.63-.63.63-.349 0-.63-.281-.63-.63V5.131c0-.345.281-.63.63-.63zm-3.506 2.247c.349 0 .63.285.63.63v9.003c0 .349-.281.63-.63.63-.349 0-.63-.281-.63-.63V7.378c0-.345.281-.63.63-.63z"/></svg>',
            // Messenger图标
            'messenger' => '<svg width="' . $size . '" height="' . $size . '" viewBox="0 0 24 24" fill="' . $color . '"><path d="M12 2C6.36 2 2 6.13 2 11.7c0 2.91 1.2 5.42 3.15 7.1.17.14.27.36.27.59v2.59c0 .5.54.82 1 .58l2.87-1.51c.16-.08.34-.12.52-.1.73.1 1.48.16 2.24.16 5.64 0 10-4.13 10-9.7C22 5.13 17.64 2 12 2zm5.89 7.54l-2.83 4.48c-.45.72-1.39.88-2.04.36l-2.18-1.63a.75.75 0 00-.9 0l-2.95 2.24c-.43.33-.99-.2-.7-.66l2.83-4.48c.45-.72 1.39-.88 2.04-.36l2.18 1.63a.75.75 0 00.9 0l2.95-2.24c.43-.33.99.2.7.66z"/></svg>',
            // Viber图标（电话形状）
            'viber' => '<svg width="' . $size . '" height="' . $size . '" viewBox="0 0 24 24" fill="' . $color . '"><path d="M16.5 1.5c-2.27 0-4.23 1.31-5.22 3.22C10.29 2.81 8.33 1.5 6.06 1.5 2.72 1.5 0 4.22 0 7.56c0 6.01 5.64 10.19 10.02 13.58a1.5 1.5 0 001.96 0C16.36 17.75 22 13.57 22 7.56c0-3.34-2.72-6.06-6.06-6.06h.56zm-.56 2c2.24 0 4.06 1.82 4.06 4.06 0 4.63-4.5 8.24-8.5 11.44-4-3.2-8.5-6.81-8.5-11.44 0-2.24 1.82-4.06 4.06-4.06 1.63 0 3.06.97 3.69 2.37.23.5.77.79 1.31.79s1.08-.29 1.31-.79c.63-1.4 2.06-2.37 3.69-2.37h-.62z"/></svg>',
            // Signal图标
            'signal' => '<svg width="' . $size . '" height="' . $size . '" viewBox="0 0 24 24" fill="' . $color . '"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z"/></svg>',
        ];
        return $icons[$type] ?? '';
    }

    /**
     * 获取浮标图标 SVG
     */
    private function get_icon_svg(string $style, int $size = 24, string $color = '#ffffff'): string {
        $icons = [
            'chat-bubble' => '<svg width="' . $size . '" height="' . $size . '" viewBox="0 0 24 24" fill="' . $color . '"><path d="M20 2H4c-1.1 0-2 .9-2 2v18l4-4h14c1.1 0 2-.9 2-2V4c0-1.1-.9-2-2-2zm0 14H6l-2 2V4h16v12z"/></svg>',
            'headset' => '<svg width="' . $size . '" height="' . $size . '" viewBox="0 0 24 24" fill="' . $color . '"><path d="M12 1c-4.97 0-9 4.03-9 9v7c0 1.66 1.34 3 3 3h3v-8H5v-2c0-3.87 3.13-7 7-7s7 3.13 7 7v2h-4v8h3c1.66 0 3-1.34 3-3v-7c0-4.97-4.03-9-9-9z"/></svg>',
            'message' => '<svg width="' . $size . '" height="' . $size . '" viewBox="0 0 24 24" fill="' . $color . '"><path d="M20 4H4c-1.1 0-1.99.9-1.99 2L2 18c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V6c0-1.1-.9-2-2-2zm0 4l-8 5-8-5V6l8 5 8-5v2z"/></svg>',
            'support' => '<svg width="' . $size . '" height="' . $size . '" viewBox="0 0 24 24" fill="' . $color . '"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 17h-2v-2h2v2zm2.07-7.75l-.9.92C13.45 12.9 13 13.5 13 15h-2v-.5c0-1.1.45-2.1 1.17-2.83l1.24-1.26c.37-.36.59-.86.59-1.41 0-1.1-.9-2-2-2s-2 .9-2 2H8c0-2.21 1.79-4 4-4s4 1.79 4 4c0 .88-.36 1.68-.93 2.25z"/></svg>',
        ];
        return $icons[$style] ?? $icons['chat-bubble'];
    }

    /**
     * AJAX: 提交留言表单
     */
    public function ajax_submit_contact_form(): void {
        check_ajax_referer('pcs_contact_nonce', 'nonce');

        // 频率限制：同一 IP 每分钟最多提交 3 次
        $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        $rate_key = 'pcs_contact_rate_' . md5($ip);
        $rate_count = intval(get_transient($rate_key));
        if ($rate_count >= 3) {
            wp_send_json_error(['message' => __('Submitted too frequently, please try again later', 'pro-currency-switcher')]);
            return;
        }
        set_transient($rate_key, $rate_count + 1, 60);

        $name = sanitize_text_field(wp_unslash($_POST['name'] ?? ''));
        $email = sanitize_email(wp_unslash($_POST['email'] ?? ''));
        $phone = sanitize_text_field(wp_unslash($_POST['phone'] ?? ''));
        $message = sanitize_textarea_field(wp_unslash($_POST['message'] ?? ''));

        // 验证必填项
        if (empty($name) || empty($email) || empty($message)) {
            wp_send_json_error(['message' => __('Please fill in your name, email and message', 'pro-currency-switcher')]);
            return;
        }

        if (!is_email($email)) {
            wp_send_json_error(['message' => __('Invalid email format', 'pro-currency-switcher')]);
            return;
        }

        global $wpdb;
        $table = $wpdb->prefix . 'pcs_contact_messages';

        // 确保表存在
        \ProCurrencySwitcher\Admin\ContactWidgetSettings::ensure_messages_table();

        $inserted = $wpdb->insert($table, [
            'name'       => $name,
            'email'      => $email,
            'phone'      => $phone,
            'message'    => $message,
            'created_at' => current_time('mysql'),
        ], ['%s', '%s', '%s', '%s', '%s']);

        if ($inserted === false) {
            wp_send_json_error(['message' => __('Message save failed, please try again later', 'pro-currency-switcher')]);
            return;
        }

        wp_send_json_success(['message' => __('Message submitted successfully! We will reply as soon as possible.', 'pro-currency-switcher')]);
    }
}
