<?php

use JEALER\G3\Utilities\Element;

settings_errors('flush');
?>
<form action="" method="post">
    <?php
    echo Element::tip(
        __('The actions below will flush the Rewrite / Options / Cache data, please make sure you have a backup before performing the action.', 'G3'),
        '',
        'default',
        'mt-4'
    );
    settings_fields('flush');
    do_settings_sections('developer-mode&tab=refresh');
    ?>
</form>

<script>
    jQuery(document).ready(function ($) {
        const { Toast } = jui
        const { success, error } = Toast
        $('#g3-action__flush-options').on('click', function () {
            $.post(ajaxurl, {
                action: 'g3_admin_flush_options',
            }, (res) => {
                $('#g3-action__flush-options').attr('disabled', true)
                if (res.success) {
                    success(res.data.message, 1000);
                    setTimeout(function () {
                        window.location.reload();
                    }, 1500);
                } else {
                    error(res.data.message, 2000);
                }
                setTimeout(function () {
                    $('#g3-action__flush-options').attr('disabled', false);
                }, 2000);
            })
        })
    })
</script>