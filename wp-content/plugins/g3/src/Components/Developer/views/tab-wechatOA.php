<form action="" method="post">
    <?php
    settings_fields('opWechatOA');
    do_settings_sections('open-platform&tab=mp');
    submit_button();
    ?>
</form>