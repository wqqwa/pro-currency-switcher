<?php
/**
 * 货币选择器组件
 * 在产品页面、购物车、结账页面显示货币切换下拉框
 *
 * @package ProCurrencySwitcher
 * @since 1.0.0
 */

namespace ProCurrencySwitcher\Frontend;

use ProCurrencySwitcher\Core\CurrencyService;

if (!defined('ABSPATH')) {
    exit;
}

class CurrencySelector {

    public function __construct() {
        // 商品详情页（只挂一个钩子，避免重复显示）
        add_action('woocommerce_before_add_to_cart_form', [$this, 'display_currency_selector_near_price'], 5);

        // 商品列表页
        add_action('woocommerce_after_shop_loop_item_title', [$this, 'display_currency_selector_near_price'], 5);

        // 购物车页面
        add_action('woocommerce_cart_totals_before_order_total', [$this, 'display_currency_selector_cart']);

        // 结账页面
        add_action('woocommerce_review_order_before_order_total', [$this, 'display_currency_selector_checkout']);
    }

    public function display_currency_selector_near_price() {
        if (!class_exists(CurrencyService::class)) {
            return;
        }

        try {
            $service = CurrencyService::get_instance();
            $currencies = $service->get_enabled_currencies();
            $current_currency = $service->get_current_currency();

            if (empty($currencies) || count($currencies) < 2) {
                return;
            }

            $this->output_selector_html($currencies, $current_currency, 'inline');
        } catch (Exception $e) {
            error_log('CurrencySelector error: ' . $e->getMessage());
        }
    }

    public function display_currency_selector_cart() {
        $this->display_currency_selector_near_price();
    }

    public function display_currency_selector_checkout() {
        $this->display_currency_selector_near_price();
    }

    private function output_selector_html($currencies, $current_currency, $style = 'inline') {
        if (!class_exists(CurrencyService::class)) {
            return;
        }

        $service = CurrencyService::get_instance();
        $currencies_data = pcs_get_all_currencies_data();
        ob_start();
        ?>
        <div class="pcs-currency-selector pcs-<?php echo esc_attr($style); ?>-selector">
            <label for="pcs-currency-dropdown" style="display: inline-block; margin-right: 10px;">
                <strong><?php esc_html_e('Currency:', 'pro-currency-switcher'); ?></strong>
            </label>
            <select id="pcs-currency-dropdown" class="pcs-currency-dropdown">
                <?php foreach ($currencies as $currency):
                    $currency_name = $currencies_data[$currency]['name'] ?? $currency;
                    $currency_symbol = $currencies_data[$currency]['symbol'] ?? $currency;
                    ?>
                    <option value="<?php echo esc_attr($currency); ?>"
                            <?php selected($currency, $current_currency); ?>>
                        <?php echo esc_html($currency_symbol . ' ' . $currency_name); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <style>
        .pcs-inline-selector {
            display: inline-block;
            margin: 10px 0;
            padding: 10px;
            background: #f8f8f8;
            border-radius: 4px;
        }

        .pcs-currency-dropdown {
            padding: 5px 10px;
            border: 1px solid #ddd;
            border-radius: 3px;
            background: white;
        }

        /* 在价格旁边显示 */
        .price + .pcs-currency-selector,
        .woocommerce-Price-amount + .pcs-currency-selector {
            display: inline-block;
            margin-left: 15px;
            vertical-align: middle;
        }
        </style>

        <script>
        jQuery(document).ready(function($) {
            $('#pcs-currency-dropdown').change(function() {
                var currency = $(this).val();
                var currentUrl = window.location.href;
                var newUrl;

                // 如果URL中已有currency参数，替换它
                if (currentUrl.indexOf('currency=') > -1) {
                    newUrl = currentUrl.replace(/(currency=)[^&]+/, '$1' + currency);
                } else {
                    // 添加currency参数
                    var separator = currentUrl.indexOf('?') > -1 ? '&' : '?';
                    newUrl = currentUrl + separator + 'currency=' + currency;
                }

                // 使用URL参数方式切换货币，避免AJAX复杂性
                window.location.href = newUrl;
            });
        });
        </script>
        <?php
        $html = ob_get_clean();
        echo apply_filters('pcs_currency_selector_html', $html);
    }
}
