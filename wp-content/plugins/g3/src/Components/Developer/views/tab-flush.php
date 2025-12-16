<?php
use JEALER\G3\Utilities\Frontend;
use JEALER\G3\Utilities\Container;

Frontend::loadScript('jui');
settings_errors('flush');
?>
<form action="" method="post">
    <?php
    echo Container::tip(
        __('The actions below will flush the Rewrite / Options / Cache data, please make sure you have a backup before performing the action.', 'G3'),
        'default',
        'mt-4'
    );
    settings_fields('flush');
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