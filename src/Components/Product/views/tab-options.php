<?php
use JEALER\G3\Core\Container\Container;
use JEALER\G3\Components\Product\Includes\SpecOptionsListTable;
use JEALER\G3\Services\ProductService;
use JEALER\G3\Utilities\Message;

$table = new SpecOptionsListTable();
$table->display();
$specs = Container::run()->get(ProductService::class)->getSpecs();
$specs = array_map(function ($item) {
    return ['value' => $item['id'], 'text' => $item['name']];
}, $specs);
$specs = json_encode($specs);
?>
<script>
    jQuery(document).ready(function ($) {
        const { createModal, createForm, Toast } = jui
        const { t } = vanillaSignalI18n
        const { lite, success, error, confirm } = Toast
        const langs = {
            en: {
                keyExists: 'The key & sku_id relation is duplicated, please change it.',
                required: 'The field is required.',
                only: 'The key must be unique and only contain letters, numbers, and underscores',
            },
            zh: {
                keyExists: 'Key和sku_id关系数据 已重复，请修改。',
                required: '当前字段不可为空',
                only: '唯一且仅支持字母、数字和下划线',
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
                        required: true
                    }
                },
                {
                    type: 'text',
                    payload: {
                        label: 'Key',
                        name: 'key',
                        placeholder: ts('only'),
                        required: true
                    }
                },
                {
                    type: 'select',
                    payload: {
                        label: '<?php _e('Specifications', 'G3'); ?>',
                        name: 'spec_id',
                        options: <?php echo $specs; ?>,
                        required: true
                    }
                },
                {
                    type: 'select',
                    payload: {
                        label: '<?php _e('Status', 'G3'); ?>',
                        name: 'status',
                        options: [
                            { value: '1', text: '<?php _e('Enabled'); ?>' },
                            { value: '0', text: '<?php _e('Disabled'); ?>' }
                        ],
                        required: true
                    }
                }
            ],
            validator: {
                rules: {
                    name: {
                        required: true,
                    },
                    key: {
                        required: true,
                        pattern: /^[a-zA-Z0-9_]+$/,
                    }
                },
                messages: {
                    name: {
                        required: ts('required'),
                    },
                    key: {
                        required: ts('required'),
                        pattern: ts('only'),
                    }
                }
            },
            onSubmit: (fields) => {
                for (const key in fields) {
                    if (fields.hasOwnProperty(key) && fields[key] === '') {
                        if (key === 'sku_id') {
                            continue;
                        }
                        error('<?php _e('<strong>Error:</strong> Please fill the required fields.'); ?>')
                        return false;
                    }
                }
                console.table(fields)
                $.post(ajaxurl, {
                    fields,
                    action: 'g3_update_spec_option',
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
            footer: false,
            text: {
                title: '<?php _e('Options', 'G3'); ?>',
            },
            content: form.element,
            onHidden: () => {
                form.reset()
            }
        }).build()
        $(document).on('click', 'button#add-spec-option', (e) => {
            e.preventDefault()
            modal.show()
        })
        $(document).on('click', 'span.edit-spec-option', (e) => {
            const t = $(e.currentTarget)
            form.setFields([
                {
                    type: 'hidden',
                    payload: {
                        name: 'id',
                        value: t.data('id'),
                    }
                },
                {
                    type: 'hidden',
                    payload: {
                        name: 'spec_id',
                        value: t.data('spec'),
                    }
                },
                {
                    type: 'text',
                    payload: {
                        label: '<?php _e('Name'); ?>',
                        name: 'name',
                        value: t.data('name'),
                        required: true,
                    }
                },
                {
                    type: 'text',
                    payload: {
                        label: 'Key',
                        name: 'key',
                        value: t.data('key'),
                        required: true,
                    }
                },
                {
                    type: 'select',
                    payload: {
                        label: '<?php _e('Status', 'G3'); ?>',
                        name: 'status',
                        options: [
                            { value: '1', text: '<?php _e('Enabled'); ?>' },
                            { value: '0', text: '<?php _e('Disabled'); ?>' }
                        ],
                        value: t.data('status'),
                        required: true
                    }
                }
            ])
            modal.show()
        })
        $(document).on('click', 'span.delete-spec-option', (e) => {
            const t = $(e.currentTarget)
            if (t.attr('data-count') > 0 || t.attr('disabled')) {
                lite('<?php _e('Cannot delete this spec option, it is used in sku.', 'G3'); ?>')
                return
            }
            confirm('<?php echo Message::deleteConfirm(); ?>', {
                onConfirm: () => {
                    $.post(ajaxurl, {
                        action: 'g3_delete_spec_option',
                        id: t.attr('data-id'),
                    }, (res) => {
                        if (res.success) {
                            success(res.data.message);
                            setTimeout(function () {
                                location.reload();
                            }, 1000);
                        }
                    }).fail((res) => {
                        error(res.responseJSON.data.message);
                    })
                }
            })
        })
    });
</script>