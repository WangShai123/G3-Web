<form action="" method="post">
    <?php
    settings_fields('reading');
    do_settings_sections('post-reading');
    submit_button();
    ?>
</form>