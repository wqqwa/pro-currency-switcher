/**
 * 在线客服组件 - 前端交互逻辑
 *
 * @package ProCurrencySwitcher
 * @since 7.0.0
 */

// ============================================================
// 后台管理功能（不依赖 pcs_contact 变量）
// ============================================================
(function ($) {
    'use strict';

    $(function () {
        // ============================================================
        // 后台：媒体上传按钮
        // ============================================================
        $(document).on('click', '.pcs-upload-media, .pcs-media-upload-btn', function (e) {
            e.preventDefault();
            var targetId = $(this).data('target');
            var frame = wp.media({
                title: '选择图片',
                button: { text: '使用此图片' },
                multiple: false,
            });

            frame.on('select', function () {
                var attachment = frame.state().get('selection').first().toJSON();
                $('#' + targetId).val(attachment.url);
                var $preview = $('#' + targetId).siblings('.pcs-media-preview');
                if ($preview.length) {
                    $preview.attr('src', attachment.url).show();
                } else {
                    $('<img src="' + attachment.url + '" class="pcs-media-preview" style="max-width:100px;max-height:100px;margin-top:5px;">').insertAfter($('#' + targetId));
                }
            });

            frame.open();
        });

        // ============================================================
        // 后台：添加/删除 QQ 项
        // ============================================================
        $(document).on('click', '#pcs-add-qq', function () {
            var $container = $('#pcs-qq-items');
            var index = $container.find('.pcs-repeatable-item').length;
            var html = '<div class="pcs-repeatable-item">'
                + '<input type="text" name="qq_items[' + index + '][label]" value="" placeholder="标签（如：售前QQ）" class="regular-text">'
                + '<input type="text" name="qq_items[' + index + '][number]" value="" placeholder="QQ号码" class="regular-text" required>'
                + '<button type="button" class="button pcs-remove-item" title="删除">-</button>'
                + '</div>';
            $container.append(html);
        });

        $(document).on('click', '.pcs-remove-item', function () {
            $(this).closest('.pcs-repeatable-item').remove();
        });

        // ============================================================
        // 后台：主题颜色实时预览
        // ============================================================
        $(document).on('input', '#pcs-theme-color', function () {
            var color = $(this).val();
            $('#pcs-theme-color-hex').text(color);
            $('.pcs-icon-preview').html(
                '<svg width="40" height="40" viewBox="0 0 24 24" fill="' + color + '">'
                + '<path d="M20 2H4c-1.1 0-2 .9-2 2v18l4-4h14c1.1 0 2-.9 2-2V4c0-1.1-.9-2-2-2zm0 14H6l-2 2V4h16v12z"/>'
                + '</svg>'
            );
        });

        // ============================================================
        // 后台：留言管理
        // ============================================================
        $(document).on('click', '.pcs-view-message', function () {
            var messageId = $(this).data('id');
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'pcs_view_contact_message',
                    nonce: pcs_admin_ajax ? pcs_admin_ajax.nonce : '',
                    message_id: messageId,
                },
                success: function (res) {
                    if (res.success) {
                        var d = res.data;
                        var $detail = $('#pcs-message-detail').empty();
                        // 安全修复：使用 .text() 代替 .html() 防止XSS
                        $detail.append($('<p>').text('姓名：' + d.name));
                        $detail.append($('<p>').text('邮箱：' + d.email));
                        $detail.append($('<p>').text('电话：' + (d.phone || '-')));
                        $detail.append($('<p>').text('时间：' + d.created_at));
                        $detail.append($('<div class="pcs-msg-content">').text(d.message));
                        $('#pcs-message-modal').show();
                    }
                },
            });
        });

        // 关闭弹窗
        $(document).on('click', '.pcs-modal-close, .pcs-modal-backdrop', function () {
            $('#pcs-message-modal').hide();
        });

        // 删除留言
        $(document).on('click', '.pcs-delete-message', function () {
            var messageId = $(this).data('id');
            var nonce = $(this).data('nonce');

            if (!confirm('确定要删除这条留言吗？')) {
                return;
            }

            var $btn = $(this);
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'pcs_delete_contact_message',
                    nonce: nonce,
                    message_id: messageId,
                },
                success: function (res) {
                    if (res.success) {
                        $btn.closest('tr').fadeOut(300, function () {
                            $(this).remove();
                        });
                    } else {
                        alert(res.data.message || '删除失败');
                    }
                },
            });
        });
    });
})(jQuery);

// ============================================================
// 前端浮标功能（依赖 pcs_contact 变量）
// ============================================================
(function ($) {
    'use strict';

    if (typeof pcs_contact === 'undefined') {
        return;
    }

    $(function () {
        var $widget = $('#pcs-contact-widget');
        var $fab = $('#pcs-cw-fab');
        var $panel = $('#pcs-cw-panel');
        var $panelClose = $('#pcs-cw-panel-close');
        var $formToggle = $('#pcs-cw-form-toggle');
        var $form = $('#pcs-cw-contact-form');
        var $formSection = $('#pcs-cw-form-section');
        var isOpen = false;

        // 切换面板显示
        function togglePanel() {
            isOpen = !isOpen;
            if (isOpen) {
                $panel.slideDown(250);
                $fab.find('svg').hide();
                $('#pcs-cw-fab-close').show();
                // 显示留言表单切换按钮
                if ($formToggle.length) {
                    $formSection.show();
                }
            } else {
                $panel.slideUp(200);
                $fab.find('svg').show();
                $('#pcs-cw-fab-close').hide();
                closeQrcodePopups();
            }
        }

        // 关闭面板
        function closePanel() {
            if (isOpen) {
                isOpen = false;
                $panel.slideUp(200);
                $fab.find('svg').show();
                $('#pcs-cw-fab-close').hide();
                closeQrcodePopups();
            }
        }

        // 浮标点击
        $fab.on('click', function (e) {
            e.stopPropagation();
            togglePanel();
        });

        // 面板关闭按钮
        $panelClose.on('click', function (e) {
            e.stopPropagation();
            closePanel();
        });

        // 点击页面其他区域关闭面板
        $(document).on('click', function (e) {
            if (!$(e.target).closest($widget).length) {
                closePanel();
            }
        });

        // 微信二维码弹窗（全屏遮罩模式）
        $(document).on('click', '.pcs-cw-channel-qrcode', function (e) {
            e.preventDefault();
            e.stopPropagation();
            var qrcodeUrl = $(this).attr('data-qrcode');
            if (!qrcodeUrl) return;

            // 创建遮罩和弹窗
            var $overlay = $('<div class="pcs-cw-qrcode-overlay"></div>');
            var $popup = $('<div class="pcs-cw-qrcode-popup"><img src="' + qrcodeUrl + '" alt="二维码" style="max-width:260px;width:100%;height:auto;"><p style="text-align:center;margin:10px 0 0;font-size:14px;color:#333;">长按或扫码识别</p></div>');
            $('body').append($overlay).append($popup);

            // 点击遮罩关闭
            $overlay.on('click', function () {
                $overlay.remove();
                $popup.remove();
            });
        });

        // 留言表单切换
        $formToggle.on('click', function () {
            $form.toggleClass('active');
            if ($form.hasClass('active')) {
                $formToggle.text(pcs_contact.i18n.submitting === undefined ? '收起表单' : '');
            }
        });

        // 留言表单提交
        $form.on('submit', function (e) {
            e.preventDefault();

            var $btn = $form.find('.pcs-cw-form-submit');
            var originalText = $btn.text();

            // 移除旧的通知
            $form.find('.pcs-cw-notice').remove();

            // 验证
            var name = $form.find('input[name="name"]').val().trim();
            var email = $form.find('input[name="email"]').val().trim();
            var message = $form.find('textarea[name="message"]').val().trim();

            if (!name || !email || !message) {
                $form.prepend('<div class="pcs-cw-notice pcs-cw-notice-error">' + pcs_contact.i18n.required + '</div>');
                return;
            }

            // 禁用按钮
            $btn.prop('disabled', true).text(pcs_contact.i18n.submitting);

            $.ajax({
                url: pcs_contact.ajax_url,
                type: 'POST',
                data: {
                    action: 'pcs_submit_contact_form',
                    nonce: pcs_contact.nonce,
                    name: name,
                    email: email,
                    phone: $form.find('input[name="phone"]').val().trim(),
                    message: message,
                },
                success: function (res) {
                    if (res.success) {
                        $form.prepend('<div class="pcs-cw-notice pcs-cw-notice-success">' + res.data.message + '</div>');
                        $form[0].reset();
                    } else {
                        $form.prepend('<div class="pcs-cw-notice pcs-cw-notice-error">' + (res.data.message || pcs_contact.i18n.error) + '</div>');
                    }
                },
                error: function () {
                    $form.prepend('<div class="pcs-cw-notice pcs-cw-notice-error">' + pcs_contact.i18n.error + '</div>');
                },
                complete: function () {
                    $btn.prop('disabled', false).text(originalText);
                    // 3秒后自动移除通知
                    setTimeout(function () {
                        $form.find('.pcs-cw-notice').fadeOut(300, function () {
                            $(this).remove();
                        });
                    }, 3000);
                },
            });
        });
    });
})(jQuery);
