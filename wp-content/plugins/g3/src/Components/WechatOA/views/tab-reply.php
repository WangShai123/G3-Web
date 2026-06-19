<?php

use JEALER\G3\Utilities\Request;
use JEALER\G3\Components\WechatOA\Includes\WechatOAReplyListTable;

$table = new WechatOAReplyListTable();
$table->display();
?>

<script>
    jQuery(document).ready(function ($) {
        const { restUrl, Toast, Modal } = jui;
        const { success, error } = Toast
        $(document).on('click', '#add-reply', function () {
            const editor = new Modal({
                title: "<?php _e('Add New', 'G3'); ?>",
                confirmText: "<?php _e('Add New', 'G3'); ?>",
                cancelText: "<?php _e('Cancel'); ?>",
                fields: [
                    {
                        label: '<?php _e('Keywords'); ?>',
                        type: 'text',
                        name: 'keywords',
                        id: '_keywords',
                        placeholder: '<?php _e('Enter keywords please', 'G3'); ?>',
                        required: true
                    },
                    {
                        label: '<?php _e('Reply'); ?>',
                        type: 'textarea',
                        name: 'reply',
                        id: '_reply',
                        placeholder: '<?php _e('Enter reply please', 'G3'); ?>',
                        required: true
                    },
                    {
                        label: '<?php _e('Status'); ?>',
                        type: 'select',
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
                ],
                onSubmit: function (formData) {
                    editor.state.loading = true;
                    const data = {
                        id: 0,
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
                                location.reload();
                            }, 500);
                        },
                        error: function (xhr, status, error) {
                            const msg = JSON.parse(xhr.responseText);
                            error(msg.message);
                        },
                        complete: function () {
                            setTimeout(function () {
                                editor.state.loading = false;
                            }, 500);
                        }
                    });
                }
            });
            editor.show();
        })

        $(document).on('click', '.edit-reply', function (e) {
            const t = $(e.currentTarget);
            const id = parseInt(t.data('id'));
            const keywords = JSON.parse(t.data('keywords'));
            const reply = JSON.parse(t.data('content'));
            const editModal = new Modal({
                title: "<?php _e('Edit'); ?>",
                confirmText: "<?php _e('Update'); ?>",
                cancelText: "<?php _e('Cancel'); ?>",
                fields: [
                    {
                        label: '<?php _e('Keywords'); ?>',
                        type: 'text',
                        name: 'keywords',
                        id: '_keywords',
                        placeholder: '<?php _e('Enter keywords please', 'G3'); ?>',
                        required: true,
                        value: keywords
                    },
                    {
                        label: '<?php _e('Reply'); ?>',
                        type: 'textarea',
                        name: 'reply',
                        id: '_reply',
                        placeholder: '<?php _e('Enter reply please', 'G3'); ?>',
                        required: true,
                        value: reply
                    },
                    {
                        label: '<?php _e('Status'); ?>',
                        type: 'select',
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
                ],
                onSubmit: function (d) {
                    editModal.state.loading = true
                    const data = {
                        id: id,
                        keywords: d.keywords,
                        content: d.reply,
                        status: d.status,
                        type: 'text'
                    }
                    $.ajax({
                        url: restUrl + '/api/v1/admin/wechat_oa/reply/update',
                        type: 'POST',
                        contentType: 'application/json',
                        data: JSON.stringify(data),
                        success: function (res) {
                            success(res.message);
                            setTimeout(function () {
                                location.reload();
                            }, 500);
                        },
                        error: function (xhr, status, error) {
                            const msg = JSON.parse(xhr.responseText);
                            error(msg.message);
                        },
                        complete: function () {
                            setTimeout(function () {
                                editModal.state.loading = false;
                            }, 500);
                        }
                    });
                }
            })
            editModal.show();
        });

        $(document).on('click', '.delete-reply', function () {
            if (confirm('<?php _e('Are you sure you want to delete it?', 'G3'); ?>')) {
                $.ajax({
                    url: '<?php echo Request::restApi('/api/v1/admin/wechat_oa/reply/delete'); ?>',
                    type: 'POST',
                    contentType: 'application/json',
                    data: JSON.stringify({ id: $(this).data('id') }),
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
        });
    });
</script>