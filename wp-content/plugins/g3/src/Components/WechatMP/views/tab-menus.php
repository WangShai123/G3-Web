<?php
use JEALER\G3\Utilities\Frontend;
use JEALER\G3\Utilities\Image;
use JEALER\G3\Includes\WechatMenuListTable;
use JEALER\G3\Services\WechatMPService;
Frontend::loadStyle('jui');
Frontend::loadScript('jui');
$table = new WechatMenuListTable();
?>

<div class="j-tip is-default mt-4">
    <div class="tip-title"><?php _e('Tip', 'G3'); ?></div>
    <div class="tip-content">
        <?php
        // 在微信客户端中，公众号菜单每5分钟自动刷新一次。若您想即时手动刷新菜单，请重新关注公众号后访问。
        _e('In the WeChat client, the menu of the official account is refreshed every 5 minutes. If you want to instantly manually refresh the menu, please re-follow the official account and visit.', 'G3');
        ?>
    </div>
</div>
<div class="mt-4 flex gap-2">
    <a href="<?php echo admin_url('admin.php?page=wechat-mp-menu-edit'); ?>" class="button button-primary">
        <?php _e('Add New Menu', 'G3'); ?>
    </a>
    <button type="button" class="button" id="sync-wechat-mp-menu">
        <?php _e('Synchronize Menu to WeChat MP', 'G3'); ?>
    </button>
</div>
<?php $table->display(); ?>

<script>
    const $ = jQuery;
    $(document).ready(function () {
        $('.action-delete').on('click', function (e) {
            e.preventDefault();
            let id = $(this).attr('data-id');
            if (confirm('<?php _e('Are you sure you want to delete this menu?', 'G3'); ?>')) {
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'g3_delete_wechatMP_menu',
                        id
                    },
                    success: function (res) {
                        if (res.success) {
                            JUI.Toast.success(res.data.message, 1500);
                            setTimeout(() => {
                                window.location.reload();
                            }, 1500);
                        } else {
                            JUI.Toast.error(res.data.message, 2000);
                        }
                    },
                    error: function (res) {
                        JUI.Toast.error(res.data.message, 2000);
                    }
                });
            }
        });
        $('#sync-wechat-mp-menu').on('click', function (e) {
            const oldText = $(this).text();
            e.preventDefault();
            if (confirm('<?php _e('Are you sure you want to synchronize the menu to WeChat MP?', 'G3'); ?>')) {
                $(this).attr('disabled', true);
                $(this).html('<div class="animate-spin" style="width:24px"><?php echo Image::icon('loader'); ?></div>');
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'g3_sync_wechatMP_menu',
                        nonce: '<?php echo wp_create_nonce('g3_sync_wechatMP_menu'); ?>'
                    },
                    success: function (res) {
                        if (res.success) {
                            JUI.Toast.success(res.data.message, 2000);
                        } else {
                            JUI.Toast.error(res.data.message, 2000);
                        }
                    },
                    error: function (xhr, status, error) {
                        JUI.Toast.error(error, 2000);
                    },
                    complete: function () {
                        setTimeout(function () {
                            $('#sync-wechat-mp-menu').removeAttr('disabled');
                            $('#sync-wechat-mp-menu').text(oldText)
                        }, 2000)
                    }
                })
            }
        });
    })
</script>