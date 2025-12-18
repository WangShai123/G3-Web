<?php
use JEALER\G3\Utilities\Frontend;
use JEALER\G3\Utilities\Request;
use JEALER\G3\Includes\WechatOAReplyListTable;

Frontend::loadStyle('jui');
Frontend::loadScript('jui');
$table = new WechatOAReplyListTable();

echo '<form id="list-form" method="post">';
$table->display();
echo '</form>';
?>

<style>
    #edit-reply,
    #delete-reply {
        cursor: pointer;
    }

    #edit-reply {
        color: var(--linkColor);
    }

    #delete-reply {
        color: var(--error40);
    }
</style>

<script>
    jQuery(document).ready(function () {
        const $ = jQuery;
        $('html').addClass('j-theme-indigo', 'j-radius-sm');
        const addReply = $('#add-reply');
        const editReply = $('#edit-reply');
        const deleteReply = $('#delete-reply');
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
                        id: 'keywords',
                        placeholder: '<?php _e('Enter keywords', 'G3'); ?>',
                        required: true
                    },
                    {
                        label: '<?php _e('Reply'); ?>',
                        type: 'textarea',
                        name: 'reply',
                        id: 'reply',
                        placeholder: '<?php _e('Enter reply', 'G3'); ?>',
                        required: true
                    },
                    {
                        label: '<?php _e('Status'); ?>',
                        type: 'select',
                        name: 'status',
                        id: 'status',
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
                        keywords: formData.keywords,
                        content: formData.reply,
                        status: formData.status,
                        type: 'text'
                    };
                    $.ajax({
                        url: '<?php echo Request::restApi('/api/v1/admin/wechat_oa/reply/add'); ?>',
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
    });
</script>