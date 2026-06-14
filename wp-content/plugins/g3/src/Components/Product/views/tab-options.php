<?php

use JEALER\G3\Core\Container\Container;
use JEALER\G3\Components\Product\Includes\SpecOptionsListTable;
use JEALER\G3\Services\ProductService;

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
        const { modal, toast, t, getLang } = jui
        const langs = {
            en: {
                keyExists: 'The key & sku_id relation is duplicated, please change it.',
            },
            zh: {
                keyExists: 'Key和sku_id关系数据 已重复，请修改。',
            }
        }
        const ts = (k) => t(k, langs)
        const editModal = new modal({
            header: false,
            confirmText: '<?php _e('Add'); ?>',
            cancelText: '<?php _e('Cancel'); ?>',
            fields: [
                {
                    label: '<?php _e('Name'); ?>',
                    type: 'text',
                    name: 'name',
                    required: true
                },
                {
                    label: 'Key',
                    type: 'text',
                    name: 'key',
                    placeholder: '<?php _e('The unique and machine-readable name.'); ?>',
                    required: true
                },
                {
                    label: '<?php _e('Specifications', 'G3'); ?>',
                    type: 'select',
                    name: 'spec_id',
                    options: <?php echo $specs; ?>,
                    required: true
                },
                {
                    label: '<?php _e('Status', 'G3'); ?>',
                    type: 'select',
                    name: 'status',
                    options: [
                        { value: '1', text: '<?php _e('Enabled'); ?>' },
                        { value: '0', text: '<?php _e('Disabled'); ?>' }
                    ],
                    required: true
                }
            ],
            onSubmit: (fields) => {
                for (const key in fields) {
                    if (fields.hasOwnProperty(key) && fields[key] === '') {
                        if (key === 'sku_id') {
                            continue;
                        }
                        toast.error('<?php _e('<strong>Error:</strong> Please fill the required fields.'); ?>')
                        return false;
                    }
                }
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        fields: fields,
                        action: 'g3_update_spec_option',
                    },
                    beforeSend: function () {
                        editModal.showLoading();
                    },
                    success: function (res) {
                        if (res.success) {
                            toast.success(res.data.message);
                            setTimeout(function () {
                                location.reload();
                            }, 800);
                        } else {
                            toast.error(res.data.message);
                            editModal.hideLoading();
                        }
                    },
                    error: function (res) {
                        toast.error(ts('keyExists'))
                        setTimeout(function () {
                            editModal.hideLoading()
                        }, 800);
                        return;
                    },
                })
            },
            onHidden: () => {
                editModal.reset()
            }
        })
        $(document).on('click', 'button#add-spec-option', (e) => {
            e.preventDefault()
            editModal.show()
        })
        $(document).on('click', 'span.edit-spec-option', (e) => {
            const t = $(e.currentTarget)
            editModal.setFields([
                {
                    label: '<?php _e('Name'); ?>',
                    type: 'text',
                    name: 'name',
                    value: t.data('name'),
                    required: true,
                },
                {
                    label: 'Key',
                    type: 'text',
                    name: 'key',
                    value: t.data('key'),
                    required: true,
                },
                {
                    label: '<?php _e('Status', 'G3'); ?>',
                    type: 'select',
                    name: 'status',
                    options: [
                        { value: '1', text: '<?php _e('Enabled'); ?>' },
                        { value: '0', text: '<?php _e('Disabled'); ?>' }
                    ],
                    value: t.data('status'),
                    required: true
                }
            ])
            editModal.addFields({
                id: t.data('id'),
                spec_id: t.data('spec'),
            })
            editModal.show()
        })
        $(document).on('click', 'span.delete-spec-option', (e) => {
            const t = $(e.currentTarget)
            if (t.attr('data-count') > 0 || t.attr('disabled')) {
                toast.lite('<?php _e('Cannot delete this spec option, it is used in sku.', 'G3'); ?>')
                return
            }
            if (!confirm('<?php _e('Are you sure you want to delete it?', 'G3'); ?>')) return
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'g3_delete_spec_option',
                    id: t.attr('data-id'),
                },
                success: function (res) {
                    if (res.success) {
                        toast.success(res.data.message);
                        setTimeout(function () {
                            location.reload();
                        }, 1000);
                    } else {
                        toast.error(res.data.message);
                    }
                    editModal.hideLoading();
                },
                error: function (res) {
                    editModal.hideLoading();
                    toast.error(res.responseText);
                    setTimeout(function () {
                        location.reload();
                    }, 1000);
                }
            })
        })
    });
</script>