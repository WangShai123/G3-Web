<form action="" method="post">
    <?php
    settings_fields('opWeiBo');
    do_settings_sections('open-platform&tab=weiBo');
    submit_button();
    ?>
</form>