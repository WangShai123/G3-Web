<?php

use JEALER\G3\Components\Product\Includes\SkuListTable;

echo '<div class="wrap"><h1>SKU</h1>';
$table = new SkuListTable();
$table->display();
echo '</div>';
?>

<script>
    jQuery(document).ready(function ($) {
        const { toast } = jui

        $('.delete-sku').click(function (e) {
            e.preventDefault();
            const id = $(this).attr('data-id');
            const data = {
                action: 'g3_delete_sku',
                id: id
            };

            if (!confirm('<?php _e('Are you sure you want to delete it?', 'G3'); ?>')) return

            $.post(ajaxurl, data, function (res) {
                if (res.success) {
                    toast.success(res.data.message);
                    setTimeout(function () {
                        window.location.reload();
                    }, 1000);
                }
            })
        })
    })
</script>