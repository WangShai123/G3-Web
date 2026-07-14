<?php $renderer->form($panel, $panelTab); ?>

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
