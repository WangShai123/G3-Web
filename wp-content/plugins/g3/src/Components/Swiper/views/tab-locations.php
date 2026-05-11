<?php

use JEALER\G3\Components\Swiper\Includes\SwiperLocationTable;

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
        $('.addLocation').on('click', function () {
            const modal = new jui.modal({
                title: '<?php _e('Add New', 'G3'); ?>',
                fields: [
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
                    $.post(ajaxurl, {
                        action: 'edit_swiper_location',
                        key: data.key,
                        name: data.name
                    }, function (res) {
                        modal.showLoading();
                        setTimeout(function () {
                            if (res.success) {
                                modal.hideLoading();
                                modal.hide();
                                jui.toast.success(res.data.message, 800)
                                setTimeout(function () {
                                    window.location.reload();
                                }, 800)
                            } else {
                                modal.hideLoading();
                                jui.toast.error(res.data.message, 2000)
                            }
                        }, 300);
                    })
                }
            });
            modal.show();
        });

        $('.editLocation').on('click', function () {
            const key = $(this).data('key');
            const name = $(this).data('name');
            const modal = new jui.modal({
                title: '<?php _e('Edit'); ?>',
                fields: [
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
                        jui.toast.warning('<?php _e('No data changed', 'G3'); ?>', 1500);
                        return;
                    }
                    $.post(ajaxurl, {
                        action: 'edit_swiper_location',
                        key: newKey,
                        name: newName
                    }, function (res) {
                        modal.showLoading();
                        if (res.success) {
                            modal.hideLoading();
                            modal.hide();
                            jui.toast.success(res.data.message, 800)
                            setTimeout(function () {
                                window.location.reload();
                            }, 800)
                        } else {
                            modal.hideLoading();
                            jui.toast.error(res.data.message, 2000)
                        }
                    })
                }
            });
            modal.show();
        });

        $('.deleteLocation').on('click', function () {
            const key = $(this).data('key');
            if (confirm('<?php _e('Are you sure you want to delete it?', 'G3'); ?>')) {
                $.post(ajaxurl, {
                    action: 'delete_swiper_location',
                    key: key
                }, function (res) {
                    if (res.success) {
                        jui.toast.success(res.data.message, 800)
                        setTimeout(function () {
                            window.location.reload();
                        }, 800)
                    } else {
                        jui.toast.error(res.data.message, 2000)
                    }
                })
            }
        })
    });
</script>