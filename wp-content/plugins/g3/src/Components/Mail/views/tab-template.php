<form action="" method="post">
    <?php
    settings_fields('template');
    do_settings_sections('mail&tab=template');
    submit_button();
    ?>
</form>

<script>
    const target = jQuery('.field-enable');
    const switcher = jQuery('#switch-enable');
    switcher.checked ? target.siblings('tr').show() : target.siblings('tr').hide();
    switcher.change(function () {
        this.checked ? target.siblings('tr').fadeIn() : target.siblings('tr').fadeOut();
    });

</script>