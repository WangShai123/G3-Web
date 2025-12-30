<form action="" method="post">
    <?php
    settings_fields('general');
    do_settings_sections('share-setting&tab=general');
    submit_button();
    ?>
</form>