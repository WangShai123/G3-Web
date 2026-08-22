<?php

use JEALER\G3\Components\Product\Includes\SkuListTable;
use JEALER\G3\Utilities\Message;

echo '<div class="wrap"><h1>SKU</h1>';
$table = new SkuListTable();
$table->display();
echo '</div>';
?>

<script>
    jQuery(document).ready(function ($) {
        const { Toast } = jui
        const { success, confirm, error } = Toast

        $('.delete-sku').click(function (e) {
            e.preventDefault();
            const id = $(this).attr('data-id');
            const data = {
                action: 'g3_delete_sku',
                id: id
            };
            confirm('<?php Message::deleteConfirm(); ?>', {
                onConfirm: () => {
                    $.post(ajaxurl, data, (res) => {
                        if (res.success) {
                            success(res.data.message);
                            setTimeout(function () {
                                location.reload();
                            }, 800);
                        }
                    }).fail((res) => {
                        error(res.responseJSON.data.message)
                    })
                }
            })
        })
    })
</script>