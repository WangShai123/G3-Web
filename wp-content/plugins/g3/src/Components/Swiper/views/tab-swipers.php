<?php
use JEALER\G3\Includes\SwiperListTable;
use JEALER\G3\Utilities\Frontend;
use JEALER\G3\Components;
Frontend::loadStyle('jui');
Frontend::loadScript('jui');

$table = new SwiperListTable();
$table->prepare_items();
$table->views();

echo '<form id="list-form" method="post">';
$table->display();
$table->process_bulk_actions();
echo '</form>';
?>

<script>
    jQuery(document).ready(function ($) {
        $('.swiperPreview').on('click', function (e) {
            $('html').addClass('j-theme-indigo j-radius-sm');
            e.preventDefault();
            const src = $(this).attr('src');
            const preview = new JUI.Modal({
                title: '<?php _e("Preview"); ?>',
                content: '<div class="flex justify-center align-center w-full h-screen" style="margin: -1rem -0.75rem"><img src="' + src + '" style="height: 100%;"></div>',
                fullscreen: true,
                escClose: true,
                showCancel: false
            });
            preview.show();
        });
    });
</script>