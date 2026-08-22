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
        const { createModal } = jui;
        const { jsx } = vanillaSignal;
        const preview = createModal({
            escClose: true,
            bgClose: true,
            header: false,
            footer: false,
            style: 'max-width: 1024px',
        }).build();
        $(document).on('click', '.swiperPreview', function (e) {
            e.preventDefault();
            const src = $(this).attr('src');
            const content = jsx`<img src="${src}" style="object-fit:cover">`;
            preview.reset();
            preview.setState({ content });
            preview.show();
        });
    });
</script>