<?php
use JEALER\G3\Utilities\Request;
use JEALER\G3\Includes\WechatOAReplyListTable;

$table = new WechatOAReplyListTable();
$table->display();
?>

<script>
    jQuery(document).ready(function ($) {

        $(document).on('click', '#add-reply', function () {
            const modal = new jui.modal({
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
                    modal.showLoading();
                    const data = {
                        id: 0,
                        keywords: formData.keywords,
                        content: formData.reply,
                        status: formData.status,
                        type: 'text'
                    };
                    $.ajax({
                        url: '<?php echo Request::restApi('/api/v1/admin/wechat_oa/reply/update'); ?>',
                        type: 'POST',
                        contentType: 'application/json',
                        data: JSON.stringify(data),
                        success: function (res) {
                            jui.toast.success(res.message);
                            setTimeout(function () {
                                location.reload();
                            }, 500);
                        },
                        error: function (xhr, status, error) {
                            const msg = JSON.parse(xhr.responseText);
                            jui.toast.error(msg.message);
                        },
                        complete: function () {
                            setTimeout(function () {
                                modal.hideLoading();
                            }, 500);
                        }
                    });

                }
            });
            modal.show();
        })

        $(document).on('click', '.edit-reply', function () {
            const id = parseInt($(this).data('id'));
            const keywords = JSON.parse($(this).data('keywords'));
            const reply = JSON.parse($(this).data('content'));
            const editModal = new jui.modal({
                title: "<?php _e('Edit'); ?>",
                confirmText: "<?php _e('Update'); ?>",
                cancelText: "<?php _e('Cancel'); ?>",
                formData: [
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
                onSubmit: function (formData) {
                    editModal.showLoading()
                    const data = {
                        id: id,
                        keywords: formData.keywords,
                        content: formData.reply,
                        status: formData.status,
                        type: 'text'
                    }
                    $.ajax({
                        url: '<?php echo Request::restApi('/api/v1/admin/wechat_oa/reply/update'); ?>',
                        type: 'POST',
                        contentType: 'application/json',
                        data: JSON.stringify(data),
                        success: function (res) {
                            jui.toast.success(res.message);
                            setTimeout(function () {
                                location.reload();
                            }, 500);
                        },
                        error: function (xhr, status, error) {
                            const msg = JSON.parse(xhr.responseText);
                            jui.toast.error(msg.message);
                        },
                        complete: function () {
                            setTimeout(function () {
                                editModal.hideLoading();
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
                        jui.toast.success(res.message);
                        setTimeout(function () {
                            location.reload();
                        }, 500);
                    },
                    error: function (xhr, status, error) {
                        const msg = JSON.parse(xhr.responseText);
                        jui.toast.error(msg.message);
                    }
                });
            }
        });
    });
</script>