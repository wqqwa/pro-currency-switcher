/* 梧州电通广告货币显示前端JavaScript */

(function($) {
    'use strict';

    class PCSFrontend {
        constructor() {
            this.init();
        }

        init() {
            this.bindEvents();
            this.updatePrices();
        }

        bindEvents() {
            // 货币切换事件
            $(document).on('change', '#pcs-currency-dropdown', this.handleCurrencyChange.bind(this));
            
            // 页面加载完成后更新价格
            $(document).ready(() => {
                this.updatePrices();
            });
        }

        handleCurrencyChange(event) {
            const currency = $(event.target).val();
            
            this.showLoading();
            
            $.ajax({
                url: pcs_data.ajax_url,
                type: 'POST',
                data: {
                    action: 'pcs_switch_currency',
                    currency: currency,
                    nonce: pcs_data.nonce
                },
                success: (response) => {
                    this.hideLoading();
                    
                    if (response.success) {
                        this.showSuccessMessage('货币切换成功');
                        setTimeout(() => {
                            window.location.reload();
                        }, 1000);
                    } else {
                        this.showErrorMessage(response.data || '切换失败');
                    }
                },
                error: (xhr, status, error) => {
                    this.hideLoading();
                    this.showErrorMessage('网络错误: ' + error);
                }
            });
        }

        updatePrices() {
            // 更新页面上的价格显示
            $('.price, .amount').each((index, element) => {
                const $element = $(element);
                const originalText = $element.text();
                
                // 这里可以添加价格转换逻辑
                // 实际应用中应该从服务器获取转换后的价格
                
                $element.addClass('pcs-price-display');
            });
        }

        showLoading() {
            // 显示加载状态
            $('body').append('<div id="pcs-loading" style="position:fixed;top:50%;left:50%;transform:translate(-50%,-50%);background:rgba(0,0,0,0.7);color:white;padding:10px 20px;border-radius:4px;z-index:10000;">切换中...</div>');
        }

        hideLoading() {
            $('#pcs-loading').remove();
        }

        showSuccessMessage(message) {
            this.showMessage(message, 'success');
        }

        showErrorMessage(message) {
            this.showMessage(message, 'error');
        }

        showMessage(message, type) {
            const bgColor = type === 'success' ? '#4CAF50' : '#f44336';
            
            $('body').append(`
                <div id="pcs-message" style="
                    position:fixed;
                    top:20px;
                    left:50%;
                    transform:translateX(-50%);
                    background:${bgColor};
                    color:white;
                    padding:10px 20px;
                    border-radius:4px;
                    z-index:10000;
                    box-shadow:0 2px 10px rgba(0,0,0,0.2);
                ">${message}</div>
            `);
            
            setTimeout(() => {
                $('#pcs-message').fadeOut(300, function() {
                    $(this).remove();
                });
            }, 3000);
        }
    }

    // 初始化前端功能
    $(document).ready(() => {
        new PCSFrontend();
    });

})(jQuery);