<?php
/**
 * 自动更新检查类（免费版）
 *
 * @package ProCurrencySwitcher
 */

namespace ProCurrencySwitcher\License;

if (!defined('ABSPATH')) {
    exit;
}

class LicenseUpdater {

    /** @var string API服务器地址 */
    private $api_url = 'https://hb.woocross.com/api/v1';

    /** @var string 插件文件路径 */
    private $plugin_file = 'pro-currency-switcher/pro-currency-switcher.php';

    public function __construct() {
        add_filter('pre_set_site_transient_update_plugins', [$this, 'check_for_update']);
    }

    /**
     * 检查插件更新
     */
    public function check_for_update($transient) {
        if (!is_object($transient)) {
            $transient = new \stdClass();
        }

        $manager = LicenseManager::get_instance();

        if (!$manager->is_active()) {
            return $transient;
        }

        $response = $this->api_request('/plugin/check-update', [
            'license_key'     => $manager->get_license_key(),
            'slug'            => 'pro-currency-switcher',
            'current_version' => PCS_VERSION,
            'domain'          => strtolower(wp_parse_url(home_url(), PHP_URL_HOST) ?? home_url()),
        ]);

        if (is_wp_error($response)) {
            return $transient;
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);

        if (!$body || empty($body['success'])) {
            return $transient;
        }

        $update_data = $body['data'] ?? [];

        if (empty($update_data['update_available'])) {
            return $transient;
        }

        $transient->response[$this->plugin_file] = (object) [
            'id'          => $this->plugin_file,
            'slug'        => 'pro-currency-switcher',
            'plugin'      => $this->plugin_file,
            'new_version' => $update_data['new_version'] ?? PCS_VERSION,
            'url'         => 'https://hb.woocross.com/changelog/',
            'package'     => $update_data['download_url'] ?? '',
            'tested'      => '6.7',
            'requires'    => '5.0',
            'requires_php'=> '7.4',
            'icons'       => [],
            'banners'     => [],
            'changelog'   => $update_data['changelog'] ?? '',
        ];

        return $transient;
    }

    /**
     * 发送API请求
     */
    private function api_request(string $endpoint, array $data): array|\WP_Error {
        $url = rtrim($this->api_url, '/') . $endpoint;

        $response = wp_remote_post($url, [
            'timeout' => 10,
            'body'    => $data,
            'headers' => [
                'Content-Type' => 'application/x-www-form-urlencoded',
            ],
        ]);

        if (is_wp_error($response)) {
            return $response;
        }

        $code = wp_remote_retrieve_response_code($response);
        if ($code !== 200) {
            return new \WP_Error('api_error', sprintf(
                __('Server error (HTTP %d)', 'pro-currency-switcher'),
                $code
            ));
        }

        return $response;
    }
}
