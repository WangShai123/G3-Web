<form action="" method="post">
    <?php
    settings_fields('set');
    do_settings_sections('mail');
    submit_button();
    ?>
</form>