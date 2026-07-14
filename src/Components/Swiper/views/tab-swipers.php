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
        const { Modal } = jui;
        const preview = new Modal({
            escClose: true,
            bgClose: true,
            header: false,
            footer: false,
            style: 'max-width: 1024px',
        });
        $(document).on('click', '.swiperPreview', function (e) {
            e.preventDefault();
            const src = $(this).attr('src');
            const content = '<img src="' + src + '" style="object-fit:cover">';
            preview.reset();
            preview.setContent(content);
            preview.show();
        });
    });
</script>
