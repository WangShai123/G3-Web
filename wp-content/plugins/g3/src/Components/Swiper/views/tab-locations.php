<?php
use JEALER\G3\Includes\SwiperLocationTable;
use JEALER\G3\Utilities\Frontend;
Frontend::loadStyle('jui');
Frontend::loadScript('jui');
$location = new SwiperLocationTable();
$location->prepare_items();
$location->views();

echo '<form id="list-form" method="post">';
$location->display();
$location->process_bulk_actions();
echo '</form>';
?>

<script>
    jQuery(document).ready(function ($) {
        $('html').addClass('j-theme-indigo j-radius-sm');
        $('#addNew').on('click', function (e) {
            e.preventDefault();
            const modal = new JUI.Modal({
                title: '<?php _e('Add New', 'G3'); ?>',
                formData: [
                    {
                        label: '<?php _e('Slug'); ?>',
                        name: 'key',
                        type: 'text',
                        required: true
                    },
                    {
                        label: '<?php _e('Name'); ?>',
                        name: 'name',
                        type: 'text',
                        required: true
                    }
                ],
                confirmText: '<?php _e('Submit'); ?>',
                cancelText: '<?php _e('Cancel'); ?>',
                escClose: true,
                onSubmit: function (data) {
                    $.ajax({
                        url: ajaxurl,
                        type: 'POST',
                        data: {
                            action: 'edit_location',
                            key: data.key,
                            name: data.name
                        },
                        beforeSend: function () {
                            modal.showLoading();
                        },
                        success: function (res) {
                            setTimeout(function () {
                                modal.hideLoading();
                                if (res.success) {
                                    modal.hide();
                                    JUI.Toast.success(res.data.message, 1000)
                                    setTimeout(function () {
                                        window.location.reload();
                                    }, 1000)
                                } else {
                                    JUI.Toast.error(res.data.message, 2000)
                                }
                            }, 500)
                        },
                        error: function (xhr, status, error) {
                            modal.hideLoading();
                        }
                    })
                }
            });
            modal.show();
        });

        $('.editLocation').on('click', function (e) {
            const key = $(this).data('key');
            const name = $(this).data('name');
            e.preventDefault();
            const modal = new JUI.Modal({
                title: '<?php _e('Edit'); ?>',
                formData: [
                    {
                        label: '<?php _e('Slug'); ?>',
                        name: 'key',
                        type: 'text',
                        value: key
                    },
                    {
                        label: '<?php _e('Name'); ?>',
                        name: 'name',
                        type: 'text',
                        value: name
                    }
                ],
                confirmText: '<?php _e('Submit'); ?>',
                cancelText: '<?php _e('Cancel'); ?>',
                escClose: true,
                onSubmit: function (data) {
                    const newKey = (data.key || '').trim();
                    const newName = (data.name || '').trim();
                    if (String(key) === newKey && String(name) === newName) {
                        JUI.Toast.warning('<?php _e('No changes were made'); ?>', 1500);
                        return;
                    }
                    $.ajax({
                        url: ajaxurl,
                        type: 'POST',
                        data: {
                            action: 'edit_location',
                            key: newKey,
                            name: newName
                        },
                        beforeSend: function () {
                            modal.showLoading();
                        },
                        success: function (res) {
                            setTimeout(function () {
                                modal.hideLoading();
                                if (res.success) {
                                    modal.hide();
                                    JUI.Toast.success(res.data.message, 1000)
                                    setTimeout(function () {
                                        window.location.reload();
                                    }, 1000)
                                } else {
                                    JUI.Toast.error(res.data.message, 2000)
                                }
                            }, 500)
                        },
                        error: function (xhr, status, error) {
                            modal.hideLoading();
                        }
                    })
                }
            });
            modal.show();
        });
    });
</script>