<form action="" method="POST">
    <?php
    settings_fields('securitySection');
    do_settings_sections('security');
    submit_button();
    ?>
</form>