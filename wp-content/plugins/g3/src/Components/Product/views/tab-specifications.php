<?php
use JEALER\G3\Components\Product\Includes\SpecsListTable;
use JEALER\G3\Utilities\Message;

$table = new SpecsListTable();
$table->display();
?>

<script>
    jQuery(document).ready(function ($) {
        const { Modal, Toast, t, getLang } = jui
        const { lite, success, error } = Toast
        const langs = {
            en: {
                keyExists: 'The key is duplicated, please change it.',
            },
            zh: {
                keyExists: 'Key 已重复，请修改。',
            }
        }
        const ts = (k) => t(k, langs)
        const editModal = new Modal({
            header: false,
            text: {
                confirm: '<?php _e('Submit'); ?>',
                cancel: '<?php _e('Cancel'); ?>',
            },
            fields: [
                {
                    label: '<?php _e('Name'); ?>',
                    type: 'text',
                    name: 'name',
                    required: true,
                },
                {
                    label: 'Key',
                    type: 'text',
                    name: 'key',
                    placeholder: '<?php _e('The unique and machine-readable name.'); ?>',
                    required: true
                },
                {
                    label: '<?php _e('Global Spec', 'G3'); ?>',
                    type: 'select',
                    name: 'is_global',
                    options: [
                        { value: '1', text: '<?php _e('Yes'); ?>' },
                        { value: '0', text: '<?php _e('No'); ?>' },
                    ],
                    required: true
                },
                {
                    label: '<?php _e('Scope', 'G3'); ?>',
                    type: 'select',
                    name: 'scope',
                    options: [
                        { value: '0', text: '<?php _e('All'); ?>' },
                        { value: '1', text: '<?php _e('Product', 'G3'); ?>' },
                        { value: '2', text: '<?php _e('Categories'); ?>' },
                        { value: '3', text: '<?php _e('Tags'); ?>' },
                        { value: '4', text: '<?php _e('Brand', 'G3'); ?>' },
                    ],
                    required: true
                },
                // {
                //     label: '<?php //_e('Apply To', 'G3'); ?>',
                //     type: 'text',
                //     name: 'owner_ids',
                //     placeholder: '<?php //_e('Separate IDs with commas', 'G3'); ?>',
                // }
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
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        fields: fields,
                        action: 'g3_update_spec',
                    },
                    beforeSend: function () {
                        editModal.state.loading = true;
                    },
                    success: function (res) {
                        if (res.success) {
                            success(res.data.message);
                            setTimeout(function () {
                                editModal.state.loading = false
                                location.reload();
                            }, 1000);
                            return;
                        }
                        error(res.data.message);
                    },
                    error: function (res) {
                        error(ts('keyExists'))
                        setTimeout(function () {
                            editModal.state.loading = false
                        }, 1000);
                        return;
                    },
                })
            },
            onHidden: () => {
                editModal.reset()
            }
        })
        $(document).on('click', 'button#add-spec', (e) => {
            e.preventDefault()
            editModal.show()
        })
        $(document).on('click', 'span.edit-spec', (e) => {
            const t = $(e.currentTarget)
            editModal.setFields([
                {
                    label: '<?php _e('Name'); ?>',
                    name: 'name',
                    required: true,
                    value: t.data('name'),
                },
                {
                    label: 'Key',
                    name: 'key',
                    required: true,
                    value: t.data('key'),
                },
                {
                    label: '<?php _e('Global Spec', 'G3'); ?>',
                    type: 'select',
                    name: 'is_global',
                    value: t.data('global'),
                    options: [
                        { value: '1', text: '<?php _e('Yes'); ?>' },
                        { value: '0', text: '<?php _e('No'); ?>' },
                    ],
                    required: true
                },
                {
                    label: '<?php _e('Scope', 'G3'); ?>',
                    type: 'select',
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
                },
            ]
                // {
                //     name: t.attr('data-name'),
                //     key: t.attr('data-key'),
                //     is_global: t.attr('data-global'),
                //     scope: t.attr('data-scope'),
                //     owner_ids: t.attr('data-ids'),
                // }
            )
            editModal.addFields({
                id: t.attr('data-id'),
            })
            editModal.show()
        })
        $(document).on('click', 'span.delete-spec', function (e) {
            const t = $(e.currentTarget)
            if (t.attr('data-count') > 0 || t.attr('disabled')) {
                lite('<?php _e('Cannot delete this spec, it is used in some sku or options.', 'G3'); ?>')
                return
            }
            if (!confirm('<?php Message::deleteConfirm(); ?>')) return
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'g3_delete_spec',
                    id: t.attr('data-id'),
                },
                success: function (res) {
                    if (res.success) {
                        success(res.data.message);
                        setTimeout(function () {
                            location.reload();
                        }, 1000);
                        return;
                    }
                    error(res.data.message);
                }
            })
        })
    });
</script>