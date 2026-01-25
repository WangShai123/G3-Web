<form action="" method="post">
    <?php
    settings_fields('generalSetting');
    do_settings_sections('wallet-settings&tab=general');
    submit_button();
    ?>
</form>