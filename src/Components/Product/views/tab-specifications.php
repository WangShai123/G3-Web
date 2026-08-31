<?php
use JEALER\G3\Components\Product\Includes\SpecsListTable;
use JEALER\G3\Utilities\Message;

$table = new SpecsListTable();
$table->display();
?>

<script>
    jQuery(document).ready(function ($) {
        const { createModal, createForm, Toast } = jui
        const { t } = vanillaSignalI18n
        const { lite, success, error, confirm } = Toast
        const langs = {
            en: {
                keyExists: 'The key is duplicated, please change it.',
            },
            zh: {
                keyExists: 'Key 已重复，请修改。',
            }
        }
        const ts = (k) => t(k, langs)

        const form = createForm({
            buttons: "reverse",
            buttonsPosition: "end",
            fields: [
                {
                    type: 'text',
                    payload: {
                        label: '<?php _e('Name'); ?>',
                        name: 'name',
                        required: true,
                    }
                },
                {
                    type: 'text',
                    payload: {
                        label: 'Key',
                        name: 'key',
                        placeholder: '<?php _e('The unique and machine-readable name.'); ?>',
                        required: true
                    }
                },
                {
                    type: 'select',
                    payload: {
                        label: '<?php _e('Global Spec', 'G3'); ?>',
                        name: 'is_global',
                        options: [
                            { value: '1', text: '<?php _e('Yes'); ?>' },
                            { value: '0', text: '<?php _e('No'); ?>' },
                        ],
                        required: true
                    }
                },
                {
                    type: 'select',
                    payload: {
                        label: '<?php _e('Scope', 'G3'); ?>',
                        name: 'scope',
                        options: [
                            { value: '0', text: '<?php _e('All'); ?>' },
                            { value: '1', text: '<?php _e('Product', 'G3'); ?>' },
                            { value: '2', text: '<?php _e('Categories'); ?>' },
                            { value: '3', text: '<?php _e('Tags'); ?>' },
                            { value: '4', text: '<?php _e('Brand', 'G3'); ?>' },
                        ],
                        required: true
                    }
                },
            ],
            onSubmit: (fields) => {
                for (const key in fields) {
                    if (fields.hasOwnProperty(key) && fields[key] === '') {
                        if (key === 'owner_ids') {
                            continue;
                        }
                        error('<?php _e('<strong>Error:</strong> Please fill the required fields.'); ?>')
                        return false;
                    }
                }
                $.post(ajaxurl, {
                    fields,
                    action: 'g3_update_spec',
                }, (res) => {
                    if (res.success) {
                        success(res.data.message);
                        setTimeout(function () {
                            location.reload();
                        }, 800);
                    }
                }).fail((res) => {
                    error(ts('keyExists'))
                })
            },
        }).build()
        const modal = createModal({
            header: false,
            footer: false,
            bgClose: true,
            escClose: true,
            content: form.element,
            onHidden: () => {
                form.reset()
            }
        }).build()
        $(document).on('click', 'button#add-spec', (e) => {
            e.preventDefault()
            modal.show()
        })
        $(document).on('click', 'span.edit-spec', (e) => {
            const t = $(e.currentTarget)
            form.setFields([
                {
                    type: 'hidden',
                    payload: {
                        name: 'id',
                        value: t.attr('data-id'),
                    }
                },
                {
                    type: 'text',
                    payload: {
                        label: '<?php _e('Name'); ?>',
                        name: 'name',
                        required: true,
                        value: t.data('name'),
                    }
                },
                {
                    type: 'text',
                    payload: {
                        label: 'Key',
                        name: 'key',
                        required: true,
                        value: t.data('key'),
                    }
                },
                {
                    type: 'select',
                    payload: {
                        label: '<?php _e('Global Spec', 'G3'); ?>',
                        name: 'is_global',
                        value: t.data('global'),
                        options: [
                            { value: '1', text: '<?php _e('Yes'); ?>' },
                            { value: '0', text: '<?php _e('No'); ?>' },
                        ],
                        required: true
                    }
                },
                {
                    type: 'select',
                    payload: {
                        label: '<?php _e('Scope', 'G3'); ?>',
                        name: 'scope',
                        value: t.data('scope'),
                        options: [
                            { value: '0', text: '<?php _e('All'); ?>' },
                            { value: '1', text: '<?php _e('Product', 'G3'); ?>' },
                            { value: '2', text: '<?php _e('Categories'); ?>' },
                            { value: '3', text: '<?php _e('Tags'); ?>' },
                            { value: '4', text: '<?php _e('Brand', 'G3'); ?>' },
                        ],
                        required: true
                    }
                },
            ])
            modal.show()
        })
        $(document).on('click', 'span.delete-spec', function (e) {
            const t = $(e.currentTarget)
            if (t.attr('data-count') > 0 || t.attr('disabled')) {
                lite('<?php _e('Cannot delete this spec, it is used in some sku or options.', 'G3'); ?>')
                return
            }
            confirm('<?php echo Message::deleteConfirm(); ?>', {
                onConfirm: () => {
                    $.post(ajaxurl, {
                        action: 'g3_delete_spec',
                        id: t.attr('data-id'),
                    }, (res) => {
                        if (res.success) {
                            success(res.data.message);
                            setTimeout(function () {
                                location.reload();
                            }, 800);
                            return;
                        }
                    }).fail((res) => {
                        error(res.responseJSON.data.message);
                    })
                }
            })
        })
    });
</script>