<?php
use JEALER\G3\Utilities\Message;
use JEALER\G3\Utilities\Request;
use JEALER\G3\Components\WechatOA\Includes\WechatOAReplyListTable;

$table = new WechatOAReplyListTable();
$table->display();
?>

<script>
    jQuery(document).ready(function ($) {
        const { restUrl, Toast, createModal, createForm } = jui;
        const { success, error, confirm } = Toast
        const f = createForm({
            fields: [
                {
                    type: 'text',
                    payload: {
                        label: '<?php _e('Keywords'); ?>',
                        name: 'keywords',
                        id: '_keywords',
                        placeholder: '<?php _e('Enter keywords please', 'G3'); ?>',
                        required: true
                    }
                },
                {
                    type: 'textarea',
                    payload: {
                        label: '<?php _e('Reply'); ?>',
                        name: 'reply',
                        id: '_reply',
                        placeholder: '<?php _e('Enter reply please', 'G3'); ?>',
                        required: true
                    }
                },
                {
                    type: 'select',
                    payload: {
                        label: '<?php _e('Status'); ?>',
                        name: 'status',
                        id: '_status',
                        value: '0',
                        options: [
                            {
                                value: '1',
                                text: '<?php _e('Enable'); ?>'
                            },
                            {
                                value: '0',
                                text: '<?php _e('Disable'); ?>'
                            }
                        ],
                        required: true
                    }
                }
            ],
            buttons: "reverse",
            buttonsPosition: "end",
            onSubmit: function (formData) {
                const data = {
                    id: parseInt(formData.id) || 0,
                    keywords: formData.keywords,
                    content: formData.reply,
                    status: formData.status,
                    type: 'text'
                };
                $.ajax({
                    url: restUrl + '/api/v1/admin/wechat_oa/reply/update',
                    type: 'POST',
                    contentType: 'application/json',
                    data: JSON.stringify(data),
                    success: function (res) {
                        success(res.message);
                        setTimeout(function () {
                            editor.hide();
                            location.reload();
                        }, 800);
                    },
                    error: (res) => {
                        error(res.responseJSON.message);
                    }
                });
            }
        }).build();
        const editor = createModal({
            text: {
                title: "<?php _e('Edit'); ?>",
            },
            content: f.element,
            footer: false,
            onHidden: () => { f.reset() }
        }).build();
        $(document).on('click', '#add-reply', function () {
            editor.show();
        })

        $(document).on('click', '.edit-reply', function (e) {
            const t = $(e.currentTarget);
            const id = parseInt(t.data('id'));
            const keywords = JSON.parse(t.data('keywords'));
            const reply = JSON.parse(t.data('content'));
            f.setFields([
                {
                    type: 'hidden',
                    payload: {
                        name: 'id',
                        value: id
                    }
                },
                {
                    type: 'text',
                    payload: {
                        label: '<?php _e('Keywords'); ?>',
                        name: 'keywords',
                        id: '_keywords',
                        placeholder: '<?php _e('Enter keywords please', 'G3'); ?>',
                        required: true,
                        value: keywords
                    }
                },
                {
                    type: 'textarea',
                    payload: {
                        label: '<?php _e('Reply'); ?>',
                        name: 'reply',
                        id: '_reply',
                        placeholder: '<?php _e('Enter reply please', 'G3'); ?>',
                        required: true,
                        value: reply
                    }
                },
                {
                    type: 'select',
                    payload: {
                        label: '<?php _e('Status'); ?>',
                        name: 'status',
                        id: '_status',
                        value: t.data('status'),
                        options: [
                            {
                                value: '1',
                                text: '<?php _e('Enable'); ?>'
                            },
                            {
                                value: '0',
                                text: '<?php _e('Disable'); ?>'
                            }
                        ],
                        required: true,
                    }
                }
            ]);

            editor.show();
        });

        $(document).on('click', '.delete-reply', function () {
            const id = $(this).data('id');
            confirm('<?php echo Message::deleteConfirm(); ?>', {
                onConfirm: () => {
                    $.ajax({
                        // url: '<?php //echo Request::restApi('/api/v1/admin/wechat_oa/reply/delete'); ?>',
                        url: restUrl + '/api/v1/admin/wechat_oa/reply/delete',
                        type: 'POST',
                        contentType: 'application/json',
                        data: JSON.stringify({ id }),
                        success: function (res) {
                            success(res.message);
                            setTimeout(function () {
                                location.reload();
                            }, 500);
                        },
                        error: function (xhr, status, error) {
                            const msg = JSON.parse(xhr.responseText);
                            error(msg.message);
                        }
                    });
                }
            })
        });
    });
</script>