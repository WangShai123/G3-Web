<form action="" method="POST">
    <?php
    settings_fields('general');
    do_settings_sections('g3-settings');
    submit_button();
    ?>
</form>