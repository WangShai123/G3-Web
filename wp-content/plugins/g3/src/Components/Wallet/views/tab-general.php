<form action="" method="post">
    <?php
    settings_fields('generalSetting');
    do_settings_sections('wallet-setting&tab=general');
    submit_button();
    ?>
</form>