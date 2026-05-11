<?php

use JEALER\G3\Components\Swiper\Includes\SwiperListTable;

echo '<form id="swipers-filter" method="post"><input type="hidden" name="page" value="swiper-management" />';
$table = new SwiperListTable();
$table->prepare_items();
$table->views();
$table->display();
$table->process_bulk_actions();
echo '</form>';
?>

<script>
    jQuery(document).ready(function ($) {
        const preview = new jui.modal({
            // title: '<?php //_e("Preview"); ?>',
            // confirmText: '<?php //_e("Close"); ?>',
            // fullscreen: true,
            escClose: true,
            bgClose: true,
            showCancel: false,
            header: false,
            footer: false,
        });
        $('body').on('click', '.swiperPreview', function (e) {
            e.preventDefault();
            const src = $(this).attr('src');
            const content = '<div class="flex justify-center align-center w-full h-full"><img src="' + src + '" style="object-fit:cover"></div>';
            preview.setContent(content);
            preview.show();
        });
    });
</script>
<style>
    .j-modal {
        max-width: 1024px;
        max-height: auto;
    }

    .j-modal .modal-body {
        padding: 0;
    }
</style>