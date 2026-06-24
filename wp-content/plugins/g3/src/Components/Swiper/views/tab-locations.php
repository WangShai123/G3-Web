<?php
use JEALER\G3\Components\Swiper\Includes\SwiperLocationTable;
use JEALER\G3\Utilities\Message;

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
        const { Modal, Toast } = jui;
        const { success, warning, error } = Toast;
        $('.addLocation').on('click', function () {
            const m = new Modal({
                text: {
                    title: '<?php _e('Add New', 'G3'); ?>',
                    confirm: '<?php _e('Submit'); ?>',
                    cancel: '<?php _e('Cancel'); ?>',
                },
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
                onSubmit: async (data) => {
                    $.post(ajaxurl, {
                        action: 'edit_swiper_location',
                        key: data.key,
                        name: data.name
                    }, function (res) {
                        m.setState({ loading: true })
                        setTimeout(function () {
                            if (res.success) {
                                success(res.data.message, 800)
                                setTimeout(function () {
                                    window.location.reload();
                                }, 800)
                                m.hide();
                            } else {
                                error(res.data.message, 2000)
                            }
                            m.setState({ loading: false })
                        }, 300);
                    })
                },
            });
            m.show();
        });

        $('.editLocation').on('click', function () {
            const key = $(this).data('key');
            const name = $(this).data('name');
            const m = new Modal({
                text: {
                    title: '<?php _e('Edit'); ?>',
                    confirm: '<?php _e('Submit'); ?>',
                    cancel: '<?php _e('Cancel'); ?>',
                },
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
                escClose: true,
                onSubmit: function (data) {
                    const newKey = (data.key || '').trim();
                    const newName = (data.name || '').trim();
                    if (String(key) === newKey && String(name) === newName) {
                        warning('<?php _e('No data changed', 'G3'); ?>', 1500);
                        return;
                    }
                    $.post(ajaxurl, {
                        action: 'edit_swiper_location',
                        key: newKey,
                        name: newName
                    }, function (res) {
                        m.state.loading = true
                        setTimeout(() => {
                            if (res.success) {
                                success(res.data.message, 800)
                                setTimeout(function () {
                                    m.hide();
                                    window.location.reload();
                                }, 800)
                            } else {
                                error(res.data.message, 2000)
                            }
                            m.state.loading = false
                        }, 300)
                    })
                }
            });
            m.show();
        });

        $('.deleteLocation').on('click', function () {
            const key = $(this).data('key');
            if (confirm('<?php Message::deleteConfirm(); ?>')) {
                $.post(ajaxurl, {
                    action: 'delete_swiper_location',
                    key: key
                }, function (res) {
                    if (res.success) {
                        success(res.data.message, 800)
                        setTimeout(function () {
                            window.location.reload();
                        }, 800)
                    } else {
                        error(res.data.message, 2000)
                    }
                })
            }
        })
    });
</script>