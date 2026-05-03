/* 梧州电通广告货币显示JavaScript功能 */

// 安全修复：HTML转义工具函数，防止XSS
function pcsEscapeHtml(str) {
    if (typeof str !== 'string') return str;
    var div = document.createElement('div');
    div.appendChild(document.createTextNode(str));
    return div.innerHTML;
}

(function($) {
    'use strict';

    /**
     * 货币切换器核心类
     */
    class ProCurrencySwitcher {
        constructor(selector, options = {}) {
            this.selector = selector;
            this.container = $(selector);
            this.options = $.extend({
                ajaxUrl: pcs_ajax.ajax_url,
                nonce: pcs_ajax.nonce,
                currentCurrency: pcs_ajax.current_currency,
                baseCurrency: pcs_ajax.base_currency,
                currencySymbols: pcs_ajax.currency_symbols || {},
                currencyNames: pcs_ajax.currency_names || {},
                priceFormat: pcs_ajax.price_format || 'left',
                autoClose: true,
                searchEnabled: true,
                mobileOverlay: true
            }, options);
            
            this.isOpen = false;
            this.init();
        }

        /**
         * 初始化货币切换器
         */
        init() {
            this.createDropdown();
            this.bindEvents();
            this.loadCurrencyData();
        }

        /**
         * 创建下拉菜单结构
         */
        createDropdown() {
            const currentCurrency = this.options.currentCurrency;
            const currentSymbol = this.options.currencySymbols[currentCurrency] || currentCurrency;
            
            // 创建下拉菜单HTML结构
            this.container.html(`
                <div class="pcs-selector-btn">
                    <span class="pcs-currency-symbol">${currentSymbol}</span>
                    <span class="pcs-currency-code">${currentCurrency}</span>
                    <span class="pcs-dropdown-arrow">▼</span>
                </div>
                <div class="pcs-dropdown-menu">
                    ${this.options.searchEnabled ? `
                    <div class="pcs-search-box">
                        <input type="text" class="pcs-search-input" placeholder="搜索货币...">
                    </div>
                    ` : ''}
                    <div class="pcs-currency-list"></div>
                </div>
                ${this.options.mobileOverlay ? '<div class="pcs-dropdown-overlay"></div>' : ''}
            `);

            this.btn = this.container.find('.pcs-selector-btn');
            this.menu = this.container.find('.pcs-dropdown-menu');
            this.overlay = this.container.find('.pcs-dropdown-overlay');
            this.searchInput = this.container.find('.pcs-search-input');
            this.currencyList = this.container.find('.pcs-currency-list');
        }

        /**
         * 绑定事件处理
         */
        bindEvents() {
            // 切换按钮点击事件
            this.btn.on('click', (e) => {
                e.stopPropagation();
                this.toggleDropdown();
            });

            // 文档点击关闭菜单
            $(document).on('click', (e) => {
                if (!$(e.target).closest(this.selector).length) {
                    this.closeDropdown();
                }
            });

            // 搜索功能
            if (this.options.searchEnabled) {
                this.searchInput.on('input', this.debounce(() => {
                    this.filterCurrencies(this.searchInput.val());
                }, 300));
            }

            // 移动端遮罩层点击关闭
            if (this.options.mobileOverlay) {
                this.overlay.on('click', () => {
                    this.closeDropdown();
                });
            }

            // 键盘导航
            this.container.on('keydown', (e) => {
                this.handleKeyboardNavigation(e);
            });
        }

        /**
         * 加载货币数据
         */
        loadCurrencyData() {
            $.ajax({
                url: this.options.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'pcs_get_currencies',
                    nonce: this.options.nonce
                },
                success: (response) => {
                    if (response.success) {
                        this.currencies = response.data.currencies;
                        this.renderCurrencyList();
                    } else {
                        console.error('Failed to load currencies:', response.data);
                    }
                },
                error: (xhr, status, error) => {
                    console.error('AJAX error:', error);
                }
            });
        }

        /**
         * 渲染货币列表
         */
        renderCurrencyList(filterText = '') {
            if (!this.currencies) return;

            let filteredCurrencies = this.currencies;
            
            if (filterText) {
                const searchTerm = filterText.toLowerCase();
                filteredCurrencies = this.currencies.filter(currency => {
                    return currency.code.toLowerCase().includes(searchTerm) ||
                           currency.name.toLowerCase().includes(searchTerm) ||
                           currency.symbol.toLowerCase().includes(searchTerm);
                });
            }

            if (filteredCurrencies.length === 0) {
                this.currencyList.html('<div class="pcs-empty-state">未找到匹配的货币</div>');
                return;
            }

            const currentCurrency = this.options.currentCurrency;
            const itemsHtml = filteredCurrencies.map(currency => {
                const isSelected = currency.code === currentCurrency;
                const selectedClass = isSelected ? 'selected' : '';
                
                return `
                    <div class="pcs-currency-item ${selectedClass}" data-currency="${currency.code}">
                        <span class="pcs-currency-symbol">${currency.symbol}</span>
                        <span class="pcs-currency-name">${currency.name}</span>
                        <span class="pcs-currency-code">${currency.code}</span>
                    </div>
                `;
            }).join('');

            this.currencyList.html(itemsHtml);

            // 绑定货币项点击事件
            this.currencyList.find('.pcs-currency-item').on('click', (e) => {
                const currencyCode = $(e.currentTarget).data('currency');
                this.switchCurrency(currencyCode);
            });
        }

        /**
         * 切换货币
         */
        switchCurrency(currencyCode) {
            if (currencyCode === this.options.currentCurrency) {
                this.closeDropdown();
                return;
            }

            // 显示加载状态
            this.showLoading();

            $.ajax({
                url: this.options.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'pcs_switch_currency',
                    currency: currencyCode,
                    nonce: this.options.nonce
                },
                success: (response) => {
                    this.hideLoading();
                    
                    if (response.success) {
                        // 更新当前货币
                        this.options.currentCurrency = currencyCode;
                        
                        // 更新按钮显示
                        const symbol = this.options.currencySymbols[currencyCode] || currencyCode;
                        this.btn.find('.pcs-currency-symbol').text(symbol);
                        this.btn.find('.pcs-currency-code').text(currencyCode);
                        
                        // 重新渲染列表
                        this.renderCurrencyList();
                        
                        // 关闭下拉菜单
                        this.closeDropdown();
                        
                        // 触发自定义事件
                        $(document).trigger('pcs_currency_changed', {
                            currency: currencyCode,
                            previousCurrency: this.options.currentCurrency
                        });
                        
                        // 刷新页面或更新价格（根据配置）
                        if (response.data.refresh_required) {
                            window.location.reload();
                        } else {
                            this.updatePrices(response.data.prices);
                        }
                        
                    } else {
                        this.showError('货币切换失败: ' + (response.data.message || '未知错误'));
                    }
                },
                error: (xhr, status, error) => {
                    this.hideLoading();
                    this.showError('网络错误: ' + error);
                }
            });
        }

        /**
         * 更新页面价格
         */
        updatePrices(priceData) {
            if (!priceData) return;

            // 更新所有带有价格数据属性的元素
            $('[data-pcs-price]').each(function() {
                const $element = $(this);
                const priceKey = $element.data('pcs-price');
                const newPrice = priceData[priceKey];

                if (newPrice) {
                    // 安全修复：使用 pcsEscapeHtml 转义价格文本，防止XSS
                    $element.html('<span class="pcs-converted-price" style="font-weight:700;">' + pcsEscapeHtml(newPrice.formatted) + '</span>');
                    $element.attr('data-original-price', newPrice.original);
                }
            });

            // 更新购物车和总计
            this.updateCartTotals(priceData);
        }

        /**
         * 更新购物车总计
         */
        updateCartTotals(priceData) {
            // 更新WooCommerce购物车（如果存在）
            if (typeof WC !== 'undefined' && WC.cart_totals) {
                this.updateWooCommerceCart(priceData);
            }
            
            // 更新自定义购物车元素
            $('.pcs-cart-total, .pcs-subtotal, .pcs-shipping, .pcs-tax, .pcs-grand-total').each(function() {
                const $element = $(this);
                const priceType = $element.data('price-type') || 'total';
                const newPrice = priceData[priceType];
                
                if (newPrice) {
                    $element.text(newPrice.formatted);
                }
            });
        }

        /**
         * 更新WooCommerce购物车
         */
        updateWooCommerceCart(priceData) {
            // 这里需要根据WooCommerce的具体DOM结构来更新
            // 这是一个通用实现，可能需要根据具体主题调整
            
            const selectors = {
                subtotal: '.cart-subtotal .amount, .woocommerce-Price-amount',
                shipping: '.shipping .amount',
                tax: '.tax-rate .amount, .cart_tax .amount',
                total: '.order-total .amount'
            };

            Object.keys(selectors).forEach(type => {
                const price = priceData[type];
                if (price) {
                    $(selectors[type]).each(function() {
                        const $amount = $(this);
                        const symbol = $amount.find('.woocommerce-Price-currencySymbol');
                        
                        if (symbol.length) {
                            symbol.text(price.symbol);
                            $amount.contents().filter(function() {
                                return this.nodeType === 3 && this.textContent.trim() !== '';
                            })[0].textContent = price.amount;
                        } else {
                            $amount.text(price.formatted);
                        }
                    });
                }
            });
        }

        /**
         * 切换下拉菜单显示状态
         */
        toggleDropdown() {
            if (this.isOpen) {
                this.closeDropdown();
            } else {
                this.openDropdown();
            }
        }

        /**
         * 打开下拉菜单
         */
        openDropdown() {
            this.isOpen = true;
            this.btn.addClass('active');
            this.menu.addClass('show');
            
            if (this.options.mobileOverlay && this.isMobile()) {
                this.overlay.addClass('show');
            }
            
            // 聚焦搜索框（如果启用）
            if (this.options.searchEnabled) {
                setTimeout(() => {
                    this.searchInput.focus();
                }, 100);
            }
        }

        /**
         * 关闭下拉菜单
         */
        closeDropdown() {
            this.isOpen = false;
            this.btn.removeClass('active');
            this.menu.removeClass('show');
            this.overlay.removeClass('show');
            
            // 清除搜索
            if (this.options.searchEnabled) {
                this.searchInput.val('');
                this.filterCurrencies('');
            }
        }

        /**
         * 过滤货币列表
         */
        filterCurrencies(searchTerm) {
            this.renderCurrencyList(searchTerm);
        }

        /**
         * 处理键盘导航
         */
        handleKeyboardNavigation(e) {
            if (!this.isOpen) return;

            const items = this.currencyList.find('.pcs-currency-item');
            const currentIndex = items.index(items.filter('.selected'));
            let newIndex = currentIndex;

            switch (e.key) {
                case 'ArrowDown':
                    e.preventDefault();
                    newIndex = (currentIndex + 1) % items.length;
                    break;
                case 'ArrowUp':
                    e.preventDefault();
                    newIndex = (currentIndex - 1 + items.length) % items.length;
                    break;
                case 'Enter':
                    e.preventDefault();
                    if (currentIndex >= 0) {
                        const currencyCode = items.eq(currentIndex).data('currency');
                        this.switchCurrency(currencyCode);
                    }
                    return;
                case 'Escape':
                    e.preventDefault();
                    this.closeDropdown();
                    return;
                default:
                    return;
            }

            // 更新选中状态
            items.removeClass('selected');
            items.eq(newIndex).addClass('selected').get(0).scrollIntoView({
                block: 'nearest',
                behavior: 'smooth'
            });
        }

        /**
         * 显示加载状态
         */
        showLoading() {
            this.btn.addClass('loading');
            this.btn.append('<div class="pcs-loading"></div>');
        }

        /**
         * 隐藏加载状态
         */
        hideLoading() {
            this.btn.removeClass('loading');
            this.btn.find('.pcs-loading').remove();
        }

        /**
         * 显示错误消息
         */
        showError(message) {
            // 创建错误提示
            const errorDiv = $(`<div class="pcs-notice error">${message}</div>`);
            this.container.before(errorDiv);
            
            // 3秒后自动移除
            setTimeout(() => {
                errorDiv.fadeOut(() => errorDiv.remove());
            }, 3000);
        }

        /**
         * 检查是否为移动设备
         */
        isMobile() {
            return window.innerWidth <= 768;
        }

        /**
         * 防抖函数
         */
        debounce(func, wait) {
            let timeout;
            return function executedFunction(...args) {
                const later = () => {
                    clearTimeout(timeout);
                    func(...args);
                };
                clearTimeout(timeout);
                timeout = setTimeout(later, wait);
            };
        }

        /**
         * 销毁实例
         */
        destroy() {
            this.btn.off('click');
            $(document).off('click');
            this.searchInput.off('input');
            this.overlay.off('click');
            this.container.off('keydown');
            this.container.empty();
        }
    }

    /**
     * jQuery插件封装
     */
    $.fn.proCurrencySwitcher = function(options) {
        return this.each(function() {
            if (!$.data(this, 'proCurrencySwitcher')) {
                $.data(this, 'proCurrencySwitcher', new ProCurrencySwitcher(this, options));
            }
        });
    };

    /**
     * 页面加载完成后初始化所有货币切换器
     */
    $(document).ready(function() {
        // 初始化所有货币切换器
        $('.pro-currency-switcher').proCurrencySwitcher();
        
        // 监听货币变化事件
        $(document).on('pcs_currency_changed', function(event, data) {
            console.log('Currency changed:', data);
            
            // 这里可以添加其他需要响应的逻辑
            // 例如：更新分析数据、发送统计信息等
        });
        
        // 处理AJAX购物车更新（WooCommerce）
        $(document.body).on('updated_cart_totals', function() {
            // 购物车更新后重新初始化价格显示
            $('.pro-currency-switcher').each(function() {
                const instance = $.data(this, 'proCurrencySwitcher');
                if (instance) {
                    instance.loadCurrencyData();
                }
            });
        });
    });

    /**
     * 全局函数：手动切换货币
     */
    window.pcsSwitchCurrency = function(currencyCode) {
        const switcher = $('.pro-currency-switcher').first().data('proCurrencySwitcher');
        if (switcher) {
            switcher.switchCurrency(currencyCode);
        }
    };

    /**
     * 全局函数：获取当前货币
     */
    window.pcsGetCurrentCurrency = function() {
        const switcher = $('.pro-currency-switcher').first().data('proCurrencySwitcher');
        return switcher ? switcher.options.currentCurrency : null;
    };

    /**
     * 全局函数：格式化价格
     */
    window.pcsFormatPrice = function(amount, currencyCode = null) {
        const switcher = $('.pro-currency-switcher').first().data('proCurrencySwitcher');
        if (!switcher) return amount;
        
        currencyCode = currencyCode || switcher.options.currentCurrency;
        const symbol = switcher.options.currencySymbols[currencyCode] || currencyCode;
        const format = switcher.options.priceFormat;
        
        switch (format) {
            case 'left':
                return symbol + amount;
            case 'right':
                return amount + symbol;
            case 'left_space':
                return symbol + ' ' + amount;
            case 'right_space':
                return amount + ' ' + symbol;
            default:
                return symbol + amount;
        }
    };

})(jQuery);