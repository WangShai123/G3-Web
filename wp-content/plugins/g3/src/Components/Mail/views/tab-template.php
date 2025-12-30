<form action="" method="post">
    <?php
    settings_fields('template');
    do_settings_sections('mail&tab=template');
    submit_button();
    ?>
</form>

<script>
    const $ = jQuery;
    const target = $('.field-enable');
    const select = target.find('select');

    select.val() == 1 ? target.siblings('tr').show() : target.siblings('tr').hide();

    select.change(function () {
        $(this).val() == 1 ? target.siblings('tr').fadeIn() : target.siblings('tr').fadeOut();
    });

</script>