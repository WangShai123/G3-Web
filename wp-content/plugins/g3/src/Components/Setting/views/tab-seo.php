<form action="" method="POST">
    <?php
    settings_fields('seo');
    do_settings_sections('g3-settings&tab=seo');
    submit_button();
    ?>
</form>