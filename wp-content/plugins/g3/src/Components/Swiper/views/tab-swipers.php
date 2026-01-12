<?php
use JEALER\G3\Includes\SwiperListTable;
use JEALER\G3\Utilities\Frontend;

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
            $('html').addClass('j-theme-indigo j-radius-sm j-font-sm j-shadow-none');
            e.preventDefault();
            const src = $(this).attr('src');
            const preview = new jui.modal({
                title: '<?php _e("Preview"); ?>',
                content: '<div class="flex justify-center align-center w-full h-screen" style="margin: -1rem -0.75rem"><img src="' + src + '" style="height: 100%;"></div>',
                confirmText: '<?php _e("Confirm", "G3"); ?>',
                fullscreen: true,
                escClose: true,
                showCancel: false,
                onHidden: function () {
                    $('html').removeClass('j-theme-indigo j-radius-sm j-font-sm j-shadow-none');
                }
            });
            preview.show();
        });
    });
</script>