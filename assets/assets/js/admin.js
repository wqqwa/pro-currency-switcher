/* 专业货币切换器后台JavaScript */

(function($) {
    'use strict';

    class PCSAdmin {
        constructor() {
            this.init();
        }

        init() {
            this.bindEvents();
            this.updateCurrencyList();
        }

        bindEvents() {
            // 基础货币选择事件
            $(document).on('change', '#pcs_base_currency', this.handleBaseCurrencyChange.bind(this));
            
            // 启用货币选择事件
            $(document).on('change', '.pcs-currency-checkbox', this.handleCurrencyToggle.bind(this));
            
            // 表单提交事件
            $(document).on('submit', '#pcs-settings-form', this.handleFormSubmit.bind(this));
            
            // 全选/取消全选
            $(document).on('click', '#pcs-select-all', this.toggleSelectAll.bind(this));
        }

        handleBaseCurrencyChange(event) {
            const selectedCurrency = $(event.target).val();
            
            // 如果新选择的基础货币未被启用，自动启用它
            const $checkbox = $(`.pcs-currency-checkbox[value="${selectedCurrency}"]`);
            if (!$checkbox.prop('checked')) {
                $checkbox.prop('checked', true);
                this.showNotice(`已自动启用货币 ${selectedCurrency}`, 'success');
            }
            
            this.updateCurrencyList();
        }

        handleCurrencyToggle(event) {
            const $checkbox = $(event.target);
            const currency = $checkbox.val();
            const isChecked = $checkbox.prop('checked');
            
            // 如果取消选中基础货币，需要提示用户
            const baseCurrency = $('#pcs_base_currency').val();
            if (!isChecked && currency === baseCurrency) {
                this.showNotice('不能禁用基础货币，请先更改基础货币设置', 'error');
                $checkbox.prop('checked', true);
                return;
            }
            
            this.updateCurrencyList();
        }

        toggleSelectAll(event) {
            const $button = $(event.target);
            const $checkboxes = $('.pcs-currency-checkbox');
            const allChecked = $checkboxes.length === $checkboxes.filter(':checked').length;
            
            if (allChecked) {
                // 取消全选，但保留基础货币
                const baseCurrency = $('#pcs_base_currency').val();
                $checkboxes.each(function() {
                    if ($(this).val() !== baseCurrency) {
                        $(this).prop('checked', false);
                    }
                });
                $button.text('全选');
            } else {
                // 全选
                $checkboxes.prop('checked', true);
                $button.text('取消全选');
            }
            
            this.updateCurrencyList();
        }

        handleFormSubmit(event) {
            // 验证表单数据
            if (!this.validateForm()) {
                event.preventDefault();
                return false;
            }
            
            this.showLoading();
        }

        validateForm() {
            const baseCurrency = $('#pcs_base_currency').val();
            const enabledCurrencies = $('.pcs-currency-checkbox:checked');
            
            if (!baseCurrency) {
                this.showNotice('请选择基础货币', 'error');
                return false;
            }
            
            if (enabledCurrencies.length === 0) {
                this.showNotice('请至少启用一种货币', 'error');
                return false;
            }
            
            // 检查基础货币是否被启用
            const baseCurrencyEnabled = enabledCurrencies.filter(`[value="${baseCurrency}"]`).length > 0;
            if (!baseCurrencyEnabled) {
                this.showNotice('基础货币必须被启用', 'error');
                return false;
            }
            
            return true;
        }

        updateCurrencyList() {
            const enabledCount = $('.pcs-currency-checkbox:checked').length;
            const totalCount = $('.pcs-currency-checkbox').length;
            
            $('#pcs-enabled-count').text(enabledCount);
            $('#pcs-total-count').text(totalCount);
            
            // 更新全选按钮文本
            const $selectAllButton = $('#pcs-select-all');
            if (enabledCount === totalCount) {
                $selectAllButton.text('取消全选');
            } else {
                $selectAllButton.text('全选');
            }
        }

        showLoading() {
            $('.pcs-submit .button-primary').prop('disabled', true).text('保存中...');
        }

        hideLoading() {
            $('.pcs-submit .button-primary').prop('disabled', false).text('保存设置');
        }

        showNotice(message, type = 'info') {
            // 移除现有通知
            $('.pcs-notice').remove();
            
            // 安全修复：使用 .text() 防止XSS
            const $notice = $('<div class="pcs-notice ' + type + '"></div>').text(message);
            $('.pcs-admin-wrap').prepend($notice);
            
            // 5秒后自动移除通知
            setTimeout(() => {
                $('.pcs-notice').fadeOut(300, function() {
                    $(this).remove();
                });
            }, 5000);
        }
    }

    // 初始化后台功能
    $(document).ready(() => {
        new PCSAdmin();
    });

})(jQuery);