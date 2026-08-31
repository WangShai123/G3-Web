<?php
use JEALER\G3\Services\AuthService;
use JEALER\G3\Utilities\Element;
use JEALER\G3\Utilities\Frontend;

$authOption = get_option(AuthService::OPTION_KEY, []);
if (!is_array($authOption) || ($authOption['code'] ?? '') !== '1') {
    echo Element::tip(
        __('Invitation code feature is not available. Please set the invitation code as the registration code first.', 'G3'),
        '',
        'danger',
        'mt-4'
    );
    return;
}

Frontend::umd('g3.admin.tablelist');

$config = [
    'rootId'    => 'g3-invitation-code-table',
    'restUrl'   => esc_url_raw(rest_url('api/admin/table-list/v1')),
    'nonce'     => wp_create_nonce('wp_rest'),
    'endpoints' => [
        'list'       => '/invitation-codes',
        'generate'   => '/invitation-codes/generate',
        'delete'     => '/invitation-codes/delete',
        'bulkDelete' => '/invitation-codes/bulk-delete',
    ],
    'labels'    => [
        'search'            => __('Search'),
        'searchPlaceholder' => __('Search by Invitation Code', 'G3'),
        'apply'             => __('Apply'),
        'bulkActions'       => __('Bulk actions'),
        'delete'            => __('Delete'),
        'copy'              => __('Copy'),
        'copied'            => __('Copied'),
        'failed'            => __('Failed', 'G3'),
        'generate'          => __('Generate Invitation Code', 'G3'),
        'amount'            => __('Amount', 'G3'),
        'cancel'            => __('Cancel'),
        'noItems'           => __('No items found.'),
        'item'              => __('item'),
        'items'             => __('items'),
        'firstPage'         => __('First page'),
        'previousPage'      => __('Previous page'),
        'nextPage'          => __('Next page'),
        'lastPage'          => __('Last page'),
        'currentPage'       => __('Current Page'),
        'of'                => __('of'),
        'loading'           => __('Loading...'),
        'selectAction'      => __('Please select a bulk action.'),
        'selectRows'        => __('Please select at least one item.'),
        'deleteConfirm'     => __('Are you sure you want to delete it?', 'G3'),
    ],
    'sortable'  => ['source', 'status'],
    'columns'   => [
        ['key' => 'code', 'title' => __('Invitation Code', 'G3'), 'primary' => true],
        ['key' => 'creatorName', 'title' => __('Creator', 'G3'), 'sortKey' => 'creator_id'],
        ['key' => 'createdAtText', 'title' => __('Created At', 'G3'), 'sortKey' => 'created_at'],
        ['key' => 'sourceText', 'title' => __('Source'), 'sortKey' => 'source'],
        ['key' => 'endTimeText', 'title' => __('Expiration'), 'sortKey' => 'end_time'],
        ['key' => 'statusText', 'title' => __('Status'), 'sortKey' => 'status'],
        ['key' => 'inviteeName', 'title' => __('Invitee', 'G3'), 'sortKey' => 'invitee_id'],
        ['key' => 'usedAtText', 'title' => __('Used At', 'G3'), 'sortKey' => 'used_at'],
        ['key' => 'actions', 'title' => __('Action')],
    ],
];
?>

<div class="wrap">
    <div id="g3-invitation-code-table"></div>
</div>
<?php echo Frontend::configScript('g3-invitation-code-table-config', $config); ?>

<script>
    jQuery(document).ready(function ($) {
        const configEl = document.getElementById('g3-invitation-code-table-config');
        const config = configEl ? JSON.parse(configEl.textContent || '{}') : {};
        const root = document.getElementById(config.rootId);
        const { createModal, createForm, Toast, copy } = jui;
        const { success, error, confirm } = Toast;
        const createTableList = window.G3AdminTableList && window.G3AdminTableList.createTableList;
        const labels = config.labels || {};

        if (!root || typeof createTableList !== 'function') {
            return;
        }

        const messageFromError = (err) => {
            return err && err.message ? err.message : labels.failed;
        };

        const api = (path, data = {}) => {
            return fetch(config.restUrl + path, {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'Content-Type': 'application/json',
                    'X-WP-Nonce': config.nonce || ''
                },
                body: JSON.stringify(data)
            }).then((response) => {
                return response.json().catch(() => ({})).then((json) => {
                    if (!response.ok || json.success === false) {
                        throw new Error(json.message || (json.data && json.data.message) || labels.failed);
                    }
                    return json.data || json;
                });
            });
        };

        const table = createTableList(root, {
            endpoint: config.restUrl + config.endpoints.list,
            nonce: config.nonce,
            plural: 'codes',
            rowKey: 'id',
            perPage: 20,
            labels,
            sortable: config.sortable,
            columns: config.columns.map((column) => {
                if (column.key === 'code') {
                    return {
                        ...column,
                        render: (item, tableApi) => {
                            const code = tableApi.escapeHtml(item.code);
                            return tableApi.renderHtml(item.status === '0' ? code : `<del class="color-gray">${code}</del>`);
                        }
                    };
                }

                if (column.key === 'actions') {
                    return {
                        ...column,
                        render: (item, tableApi) => tableApi.renderHtml(`
                            <span class="copy-code color-link cursor-pointer" data-code="${tableApi.escapeAttr(item.code)}">${tableApi.escapeHtml(labels.copy)}</span>
                            <span class="delete-code color-error cursor-pointer" data-id="${tableApi.escapeAttr(item.id)}">${tableApi.escapeHtml(labels.delete)}</span>
                        `)
                    };
                }

                return column;
            }),
            toolbar: [
                { key: 'generate', label: labels.generate, primary: true }
            ],
            bulkActions: [
                { key: 'delete', label: labels.delete }
            ],
            onToolbarAction: (action, tableApi) => {
                if (action !== 'generate') {
                    return;
                }

                const form = createForm({
                    fields: [
                        {
                            type: 'number',
                            payload: {
                                label: labels.amount,
                                name: 'amount',
                                value: 1
                            }
                        }
                    ],
                    buttons: 'reverse',
                    buttonsPosition: 'end',
                    onSubmit: (data) => {
                        return api(config.endpoints.generate, data).then((res) => {
                            success(res.message);
                            editor.hide();
                            tableApi.refresh({ page: 1 });
                        }).catch((err) => {
                            error(messageFromError(err));
                        });
                    }
                }).build();

                const editor = createModal({
                    text: {
                        title: labels.generate,
                        confirm: labels.generate,
                        cancel: labels.cancel,
                    },
                    footer: false,
                    content: form.element,
                    onHidden: () => {
                        form.destroy();
                        editor.destroy();
                    }
                }).build();
                editor.show();
            },
            onBulkAction: (action, ids, tableApi) => {
                if (!action) {
                    error(labels.selectAction);
                    return;
                }

                if (action !== 'delete') {
                    return;
                }

                if (!ids.length) {
                    error(labels.selectRows);
                    return;
                }

                confirm(labels.deleteConfirm, {
                    onConfirm: () => {
                        api(config.endpoints.bulkDelete, { ids }).then((res) => {
                            success(res.message);
                            tableApi.refresh();
                        }).catch((err) => {
                            error(messageFromError(err));
                        });
                    }
                });
            }
        });

        $(document).on('click', '#g3-invitation-code-table .copy-code', (e) => {
            const code = $(e.currentTarget).data('code');
            Promise.resolve(copy(code)).then((result) => {
                result ? success(labels.copied) : error(labels.failed);
            });
        });

        $(document).on('click', '#g3-invitation-code-table .delete-code', (e) => {
            const id = Number($(e.currentTarget).data('id'));
            if (!id) {
                error(labels.failed);
                return;
            }

            confirm(labels.deleteConfirm, {
                onConfirm: () => {
                    api(config.endpoints.delete, { id }).then((res) => {
                        success(res.message);
                        table.refresh();
                    }).catch((err) => {
                        error(messageFromError(err));
                    });
                }
            });
        });
    });
</script>