<?php
use JEALER\G3\Utilities\Frontend;

Frontend::loadStyle('jui');
Frontend::loadScript('jui');
?>
<form action="" method="post">
    <div class="j-tip is-error mt-4">
        <div class="tip-title"><?php echo __('Tip', 'G3'); ?></div>
        <div class="tip-content">
            <?php
            echo '<div class="underline">' . __('The actions below will flush the Rewrite / Options / Cache data, please make sure you have a backup before performing the action.', 'G3') . '</div>';
            ?>
        </div>
    </div>
    <?php
    settings_fields('test_refresh');
    do_settings_sections('developer-mode&tab=refresh');
    ?>
</form>

<script>
    jQuery('#g3-action__flush-options').on('click', function () {
        jQuery.ajax({
            url: ajaxurl,
            type: 'post',
            data: {
                action: 'g3_admin_flush_options'
            },
            beforeSend: function () {
                jQuery('#g3-action__flush-options').attr('disabled', true);
            },
            success: function (res) {
                if (res.code === 200) {
                    JUI.Toast.success(res.message, 2000);
                    setTimeout(function () {
                        window.location.reload();
                    }, 2000);
                } else {
                    JUI.Toast.error(res.message, 2000);
                }
            },
            error: function (err) {
                JUI.Toast.error(err.responseJSON.data.message, 2000);
            },
            complete: function () {
                setTimeout(function () {
                    jQuery('#g3-action__flush-options').attr('disabled', false);
                }, 2000);
            }
        });
    });
</script>