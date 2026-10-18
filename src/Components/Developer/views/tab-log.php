<?php
use JEALER\G3\Utilities\Message;
?>

<div class="mt-4 flex justify-between">
    <div class="flex gap-2 flex-wrap">
        <select name="type" id="type">
            <option value="2">WP-Logs</option>
            <option value="1">G3-Logs</option>
        </select>
        <select name="files" id="files" style="display:none;">
            <option value=""><?php echo __('Latest'); ?></option>
        </select>
        <button type="button" class="button" id="refresh"><?php echo __('Refresh', 'G3'); ?></button>
        <button type="button" class="button button-error" id="delete-log"><?php echo __('Delete'); ?></button>
    </div>
    <div>
        <button type="button" class="button button-error" id="clear-logs"><?php echo __('Clear Results'); ?></button>
    </div>
</div>
<div class="mt-4 w-full j-editor is-sm">
    <div class="code-block" style="background:#fff;border:1px solid var(--ui-border);padding-top:8px">
        <div class="code-header">
            <span class="code-dots"></span>
        </div>
        <div id="logs"
            style="min-height:400px;max-height:600px;padding:10px 14px;color:#666;font-size:13px;line-height:1.65;white-space:pre-wrap;word-break:break-all;overflow-y:scroll;scrollbar-width:thin;scrollbar-color:var(--ui-border) transparent;">
        </div>
    </div>
</div>
<style>
    #logs ol {
        padding-left: 0;
        margin-left: 0;
        list-style-position: inside;
    }

    #logs ol li {
        margin-bottom: 2px;
    }
</style>
<script>
    jQuery(document).ready(function ($) {

        const { Toast } = jui;
        const { success, error, confirm, lite } = Toast;

        const skeleton = `<p class="j-skeleton skeleton-text"></p>`;
        const emptyText = '无更多日志';
        const deleteConfirm = '<?php echo Message::deleteConfirm(); ?>';
        const clearConfirm = '<?php echo Message::confirmPrompt(); ?>';
        const $type = $('#type');
        const $files = $('#files');
        const $logs = $('#logs');
        let hasFiles = false;
        let currentFile = '';

        const responseMessage = (res) => res?.responseJSON?.data?.message || res?.data?.message || '<?php echo Message::failed(); ?>';
        const isDebugLog = () => $type.val() === '2';
        const selectedFile = () => isDebugLog() ? currentFile : $files.val();
        const setLoading = (loading) => {
            $('#refresh').prop('disabled', loading);
            $('#delete-log, #clear-logs').prop('disabled', loading || !hasFiles);
            $type.prop('disabled', loading);
            $files.prop('disabled', loading || !hasFiles);
        };
        const renderSkeleton = () => {
            $logs.html(`<ol><li>${skeleton}</li></ol>`);
        };
        const renderLogs = (content) => {
            const value = typeof content === 'string'
                ? content.replace(/\r\n/g, '\n').replace(/\r/g, '\n').trim()
                : '';
            const lines = value ? value.split('\n') : [emptyText];
            const $list = $('<ol></ol>');

            lines.forEach((line) => {
                $('<li></li>').text(line || ' ').appendTo($list);
            });

            $logs.empty().append($list);
            $logs.scrollTop($logs[0].scrollHeight);
        };
        const renderFiles = (files, current) => {
            const list = Array.isArray(files) ? files : [];
            currentFile = current || '';
            hasFiles = list.length > 0;

            if (isDebugLog()) {
                $files.hide().empty();
                setLoading(false);
                return;
            }

            $files.show();
            $files.empty();
            if (!hasFiles) {
                $files.append($('<option></option>').val('').text(emptyText));
            } else {
                list.forEach((file) => {
                    if (!file?.name) return;
                    $files.append($('<option></option>').val(file.name).text(file.name));
                });
            }

            $files.val(current || '');
            setLoading(false);
        };
        const renderPayload = (payload) => {
            renderFiles(payload?.files, payload?.current);
            renderLogs(payload?.content);
        };
        const loadLogs = (file = '') => {
            setLoading(true);
            renderSkeleton();
            $.post(ajaxurl, {
                action: 'g3_developer_logs',
                type: $type.val(),
                file,
            }, (res) => {
                if (res.success) {
                    renderPayload(res.data);
                }
            }).fail((res) => {
                error(responseMessage(res));
                renderLogs('');
            }).always(() => {
                setLoading(false);
            });
        };
        const readLog = (file) => {
            if (!file) {
                renderLogs('');
                return;
            }

            setLoading(true);
            renderSkeleton();
            $.post(ajaxurl, {
                action: 'g3_developer_log',
                type: $type.val(),
                file,
            }, (res) => {
                if (res.success) {
                    renderLogs(res.data.content);
                }
            }).fail((res) => {
                error(responseMessage(res));
                renderLogs('');
            }).always(() => {
                setLoading(false);
            });
        };

        $files.on('change', function () {
            currentFile = $(this).val();
            readLog($(this).val());
        });
        $('#refresh').on('click', () => {
            loadLogs(selectedFile());
        });
        $('#delete-log').on('click', () => {
            const file = selectedFile();
            if (!file) {
                lite(emptyText);
                return;
            }

            confirm(deleteConfirm, {
                onConfirm: () => {
                    setLoading(true);
                    renderSkeleton();
                    $.post(ajaxurl, {
                        action: 'g3_delete_developer_log',
                        type: $type.val(),
                        file,
                    }, (res) => {
                        if (res.success) {
                            success(res.data.message);
                            renderPayload(res.data);
                        }
                    }).fail((res) => {
                        error(responseMessage(res));
                    }).always(() => {
                        setLoading(false);
                    });
                }
            });
        });
        $('#clear-logs').on('click', () => {
            if (!selectedFile()) {
                lite(emptyText);
                return;
            }

            confirm(clearConfirm, {
                onConfirm: () => {
                    setLoading(true);
                    renderSkeleton();
                    $.post(ajaxurl, {
                        action: 'g3_clear_developer_logs',
                        type: $type.val(),
                    }, (res) => {
                        if (res.success) {
                            success(res.data.message);
                            renderPayload(res.data);
                        }
                    }).fail((res) => {
                        error(responseMessage(res));
                    }).always(() => {
                        setLoading(false);
                    });
                }
            });
        });

        $type.on('change', () => {
            currentFile = '';
            loadLogs();
        });
        loadLogs();
    });
</script>