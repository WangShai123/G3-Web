<form action="" method="post">
    <?php
    settings_fields('opWechatOA');
    do_settings_sections('open-platform&tab=wechatOA');
    submit_button();
    ?>
</form>