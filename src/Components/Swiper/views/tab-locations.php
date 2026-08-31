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
        const { createModal, createForm, Toast } = jui;
        const { success, warning, error, confirm } = Toast;
        const f = createForm({
            fields: [
                {
                    type: 'text',
                    payload: {
                        label: '<?php _e('Name'); ?>',
                        name: 'name',
                        required: true
                    }
                },
                {
                    type: 'text',
                    payload: {
                        label: '<?php _e('Slug'); ?>',
                        name: 'key',
                        required: true
                    }
                },
            ],
            buttons: "reverse",
            buttonsPosition: "end",
            onSubmit: async (data) => {
                const newKey = (data.key || '').trim();
                const newName = (data.name || '').trim();
                if (String(key) === newKey && String(name) === newName) {
                    warning('<?php _e('No data changed', 'G3'); ?>', 1500);
                    return;
                }
                $.post(ajaxurl, {
                    action: 'edit_swiper_location',
                    key: data.key,
                    name: data.name
                }, function (res) {
                    if (res.success) {
                        success(res.data.message, 800)
                        setTimeout(function () {
                            window.location.reload();
                        }, 800)
                        m.hide();
                    }
                }).fail((res) => {
                    error(res.responseJSON.data.message)
                })
            },
        }).build();
        const m = createModal({
            text: {
                title: '<?php _e('Add New', 'G3'); ?>',
            },
            content: f.element,
            footer: false,
            bgClose: true,
            onHidden: () => { f.reset() }
        }).build();
        $('.addLocation').on('click', function () {
            m.show();
        });

        $('.editLocation').on('click', function () {
            const key = $(this).data('key');
            const name = $(this).data('name');
            f.setFields([
                {
                    type: 'text',
                    payload: {
                        label: '<?php _e('Name'); ?>',
                        name: 'name',
                        value: name
                    }
                },
                {
                    type: 'text',
                    payload: {
                        label: '<?php _e('Slug'); ?>',
                        name: 'key',
                        value: key
                    }
                },
            ]);
            m.show();
        });

        $('.deleteLocation').on('click', function () {
            const key = $(this).data('key');
            confirm('<?php echo Message::deleteConfirm(); ?>', {
                onConfirm: () => {
                    $.post(ajaxurl, {
                        action: 'delete_swiper_location',
                        key: key
                    }, function (res) {
                        if (res.success) {
                            success(res.data.message, 800)
                            setTimeout(function () {
                                window.location.reload();
                            }, 800)
                        }
                    }).fail((res) => {
                        error(res.responseJSON.data.message)
                    })
                }
            })
        })
    });
</script>