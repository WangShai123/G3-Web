<form action="" method="post">
    <?php
    settings_fields('template');
    do_settings_sections('mail&tab=template');
    submit_button();
    ?>
</form>

<script>
    jQuery(document).ready(($) => {
        const target = $('.field-template');
        const switcher = $('#switch-enable');

        const checked = switcher.prop('checked');
        target.toggle(checked);

        switcher.change(function () {
            this.checked ? target.fadeIn() : target.fadeOut();
        });
    })
</script>