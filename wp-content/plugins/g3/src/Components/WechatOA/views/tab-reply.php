<?php
use JEALER\G3\Utilities\Frontend;
use JEALER\G3\Utilities\Request;
use JEALER\G3\Includes\WechatOAReplyListTable;

Frontend::loadStyle('jui');
Frontend::loadScript('jui');
$table = new WechatOAReplyListTable();

// echo '<form id="list-form" method="post">';
$table->display();
// echo '</form>';
?>

<script>
    jQuery(document).ready(function () {
        const $ = jQuery;
        $('html').addClass('j-theme-indigo j-radius-sm j-font-sm j-shadow-none');
        const addReply = $('#add-reply');
        const editReply = $('.edit-reply');
        const deleteReply = $('.delete-reply');

        addReply.on('click', function () {
            const modal = new JUI.Modal({
                title: "<?php _e('Add'); ?>",
                confirmText: "<?php _e('Add'); ?>",
                cancelText: "<?php _e('Cancel'); ?>",
                formData: [
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
                            JUI.Toast.success(res.message);
                            setTimeout(function () {
                                location.reload();
                            }, 500);
                        },
                        error: function (xhr, status, error) {
                            const msg = JSON.parse(xhr.responseText);
                            JUI.Toast.error(msg.message);
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
        });

        editReply.on('click', function () {
            const id = parseInt($(this).data('id'));
            const keywords = JSON.parse($(this).data('keywords'));
            const reply = JSON.parse($(this).data('content'));
            const editModal = new JUI.Modal({
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
                    editModal.showLoading();
                    const data = {
                        id: id,
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
                            JUI.Toast.success(res.message);
                            setTimeout(function () {
                                location.reload();
                            }, 500);
                        },
                        error: function (xhr, status, error) {
                            const msg = JSON.parse(xhr.responseText);
                            JUI.Toast.error(msg.message);
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

        deleteReply.on('click', function () {
            if (confirm('<?php _e('Are you sure you want to delete it?', 'G3'); ?>')) {
                $.ajax({
                    url: '<?php echo Request::restApi('/api/v1/admin/wechat_oa/reply/delete'); ?>',
                    type: 'POST',
                    contentType: 'application/json',
                    data: JSON.stringify({ id: $(this).data('id') }),
                    success: function (res) {
                        JUI.Toast.success(res.message);
                        setTimeout(function () {
                            location.reload();
                        }, 500);
                    },
                    error: function (xhr, status, error) {
                        const msg = JSON.parse(xhr.responseText);
                        JUI.Toast.error(msg.message);
                    }
                });
            }
        });
    });
</script>