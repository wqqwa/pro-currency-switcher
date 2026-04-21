<?php
/**
 * 一键升级安装器
 *
 * 用户激活授权后，点击"升级到专业版"按钮：
 * 1. 从私有服务器下载付费版代码包
 * 2. 使用WordPress Plugin_Upgrader覆盖安装到同一目录
 * 3. 用户无感知，刷新即用
 *
 * @package ProCurrencySwitcher
 * @since 7.5.0
 */

namespace ProCurrencySwitcher\License;

if (!defined('ABSPATH')) {
    exit;
}

class PackageInstaller {

    /**
     * 安装付费版包（覆盖当前免费版）
     *
     * @param string $download_url 付费版ZIP下载地址（带签名，有时效）
     * @return array ['success' => bool, 'message' => string]
     */
    public function install_package(string $download_url): array {
        if (empty($download_url)) {
            return ['success' => false, 'message' => __('Invalid download URL', 'pro-currency-switcher')];
        }

        // 1. 下载ZIP包到临时文件
        $temp_file = download_url($download_url, 30);

        if (is_wp_error($temp_file)) {
            return ['success' => false, 'message' => __('Download failed: ', 'pro-currency-switcher') . $temp_file->get_error_message()];
        }

        // 2. 验证ZIP文件
        $zip = new \ZipArchive();
        if ($zip->open($temp_file) !== true) {
            @unlink($temp_file);
            return ['success' => false, 'message' => __('Downloaded file format error', 'pro-currency-switcher')];
        }

        // 3. 验证包内包含正确的插件文件
        $has_main_file = false;
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = $zip->getNameIndex($i);
            if (strpos($name, 'pro-currency-switcher.php') !== false) {
                $has_main_file = true;
                break;
            }
        }
        $zip->close();

        if (!$has_main_file) {
            @unlink($temp_file);
            return ['success' => false, 'message' => __('Package format incorrect', 'pro-currency-switcher')];
        }

        // 4. 使用WordPress原生升级器安装
        require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
        require_once ABSPATH . 'wp-admin/includes/plugin-install.php';

        $skin = new \WP_Ajax_Upgrader_Skin();
        $upgrader = new \Plugin_Upgrader($skin);

        $result = $upgrader->install($temp_file, [
            'overwrite_package' => true,  // 允许覆盖已安装的插件
            'is_multi'          => false,
        ]);

        // 5. 清理临时文件
        @unlink($temp_file);

        if (is_wp_error($result)) {
            return ['success' => false, 'message' => __('Installation failed: ', 'pro-currency-switcher') . $result->get_error_message()];
        }

        // 6. 确保插件已激活
        if (!is_plugin_active('pro-currency-switcher/pro-currency-switcher.php')) {
            activate_plugin('pro-currency-switcher/pro-currency-switcher.php');
        }

        return ['success' => true, 'message' => __('Upgrade complete! Refreshed to premium version.', 'pro-currency-switcher')];
    }

    /**
     * 请求付费版下载链接
     * 对接API: POST /api/v1/plugin/download
     *
     * @param string $license_key 授权码
     * @param string $plan 套餐类型
     * @param string $target_slug 目标版本slug（可选，不传则根据plan自动选择）
     * @return array ['success' => bool, 'download_url' => string, 'message' => string]
     */
    public function get_download_url(string $license_key, string $plan, string $target_slug = ''): array {
        $api_url = defined('PCS_API_URL')
            ? PCS_API_URL
            : 'https://hb.woocross.com/api/v1';

        $timestamp = time();
        $api_secret = defined('PCS_API_SECRET')
            ? PCS_API_SECRET
            : get_option('pcs_api_secret', 'pcs_hmac_secret_key_2026');

        $signature = hash_hmac('sha256', '/plugin/download|' . $timestamp, $api_secret);

        // 如果指定了目标slug则使用，否则根据plan自动选择
        if (empty($target_slug)) {
            $slugMap = [
                'free'       => 'pro-currency-switcher',
                'pro-1'      => 'pro-currency-switcher-pro-single',
                'pro-3'      => 'pro-currency-switcher-pro-multi',
                'pro-10'     => 'pro-currency-switcher-pro-business',
                'enterprise' => 'pro-currency-switcher-pro',
            ];
            $target_slug = $slugMap[$plan] ?? 'pro-currency-switcher';
        }

        $response = wp_remote_post(rtrim($api_url, '/') . '/plugin/download', [
            'timeout' => 15,
            'body'    => [
                'license_key'     => $license_key,
                'plan'            => $plan,
                'slug'            => $target_slug,
                'current_version' => PCS_VERSION,
                'domain'          => strtolower(wp_parse_url(home_url(), PHP_URL_HOST) ?? home_url()),
            ],
            'headers' => [
                'Content-Type'    => 'application/x-www-form-urlencoded',
                'X-PCS-Signature' => $signature,
                'X-PCS-Timestamp' => (string) $timestamp,
                'X-PCS-Domain'    => strtolower(wp_parse_url(home_url(), PHP_URL_HOST) ?? ''),
            ],
        ]);

        if (is_wp_error($response)) {
            return ['success' => false, 'download_url' => '', 'message' => $response->get_error_message()];
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);

        if (!$body || empty($body['success'])) {
            $msg = $body['message'] ?? __('Failed to get download link', 'pro-currency-switcher');
            return ['success' => false, 'download_url' => '', 'message' => $msg];
        }

        return [
            'success'      => true,
            'download_url' => $body['data']['download_url'] ?? '',
            'message'      => '',
        ];
    }
}
