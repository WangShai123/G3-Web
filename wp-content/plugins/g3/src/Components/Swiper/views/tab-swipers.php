<?php
use JEALER\G3\Includes\SwiperListTable;

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
            title: '<?php _e("Preview"); ?>',
            confirmText: '<?php _e("Close"); ?>',
            fullscreen: true,
            escClose: true,
            showCancel: false,
            header: false
        });
        $('.swiperPreview').on('click', function (e) {
            e.preventDefault();
            const src = $(this).attr('src');
            const content = '<div class="flex justify-center align-center w-full h-full"><img src="' + src + '" style="object-fit:cover"></div>';
            preview.setContent(content);
            preview.show();
        });
    });
</script>
<style>
    .j-modal .modal-body {
        padding: 0;
    }
</style>