<?php
use JEALER\G3\Services\WechatMPService;
use JEALER\G3\Utilities\Frontend;
use JEALER\G3\Utilities\Image;
$id          = $_GET['id'] ?? 0;
$menus       = WechatMPService::getMenus();
$formatMenus = WechatMPService::formatMenus($menus);
$data        = [];
if ($id) {
    foreach ($menus as $menu) {
        if ($menu['id'] == $id) {
            $data = $menu;
            break;
        }
    }
}
// $_     = '└─ ';
$name  = $data['name'] ?? '';
$sort  = $data['sort'] ?? '';
$type  = $data['type'] ?? '1';
$value = $data['value'] ?? '';
Frontend::loadStyle('jui');
Frontend::loadScript('jui');
?>
<div class="wrap">
    <h1 class="wp-heading-inline"><?php echo $id ? __('Edit') : __('Add New Menu', 'G3') ?></h1>
    <a href="<?php echo admin_url('admin.php?page=wechat-mp&tab=menus'); ?>" class="page-title-action">
        <?php _e('Back') ?>
    </a>
    <table class="form-table" role="presentation">
        <tbody>
            <!-- name -->
            <tr class="item-input-long">
                <th scope="row"><label for="name"><?php _e('Name'); ?></label></th>
                <td>
                    <fieldset>
                        <legend class="screen-reader-text"><span><?php _e('Name'); ?></span></legend>
                        <label for="name">
                            <input type="text" id="name" name="name" value="<?php echo $name; ?>">
                        </label>
                    </fieldset>
                </td>
            </tr>
            <!-- parent menu -->
            <tr class="item-input-long">
                <th scope="row"><label for="parent"><?php _e('Parent Menu', 'G3'); ?></label></th>
                <td>
                    <fieldset>
                        <legend class="screen-reader-text"><span><?php _e('Parent Menu', 'G3'); ?></span></legend>
                        <label for="parent">
                            <select name="parent" id="parent">
                                <option value="0"></option>
                                <?php
                                $parent = $data['parent'] ?? 0;
                                foreach ($formatMenus as $menu) {
                                    if ($menu['id'] !== $id) {
                                        $selected = ($menu['id'] == $parent) ? 'selected' : '';
                                        echo '<option value="' . esc_attr($menu['id']) . '" ' . $selected . '>' . esc_html($menu['name']) . '</option>';
                                    }
                                }
                                ?>
                            </select>
                        </label>
                    </fieldset>
                </td>
            </tr>
            <!-- sort -->
            <tr class="item-input-long">
                <th scope="row"><label for="sort"><?php _e('Sort', 'G3'); ?></label></th>
                <td>
                    <fieldset>
                        <legend class="screen-reader-text"><span><?php _e('Sort', 'G3'); ?></span></legend>
                        <label for="sort">
                            <input type="number" id="sort" name="sort" value="<?php echo $sort; ?>">
                        </label>
                    </fieldset>
                </td>
            </tr>
            <!-- type -->
            <tr class="item-input-long">
                <th scope="row"><label for="type"><?php _e('Type', 'G3'); ?></label></th>
                <td>
                    <fieldset>
                        <legend class="screen-reader-text"><span><?php _e('Type', 'G3'); ?></span></legend>
                        <label for="type">
                            <select name="type" id="type">
                                <option value="1" <?php echo $type == 1 ? 'selected' : ''; ?>>view</option>
                                <option value="2" <?php echo $type == 2 ? 'selected' : ''; ?>>click</option>
                            </select>
                        </label>
                    </fieldset>
                </td>
            </tr>
            <!-- value -->
            <tr class="item-input-long">
                <th scope="row"><label for="value">Key/URL</label></th>
                <td>
                    <fieldset>
                        <legend class="screen-reader-text"><span><?php _e('Value', 'G3'); ?></span></legend>
                        <label for="value">
                            <input type="text" id="value" name="value" value="<?php echo $value; ?>"
                                class="regular-text">
                        </label>
                    </fieldset>
                </td>
            </tr>
        </tbody>
    </table>
    <p class="submit">
        <button type="button" id="submit" class="button button-primary"><?php _e('Save Changes'); ?></button>
    </p>
</div>

<script>
    const $ = jQuery;
    $(document).ready(function () {
        const submit = $('#submit')
        const oldText = submit.text()
        submit.on('click', function () {
            submit.prop('disabled', true)
            submit.html('<div class="animate-spin" style="width:24px"><?php echo Image::icon('loader'); ?></div>')
            const id = '<?php echo $id; ?>'
            const data = {
                action: 'g3_edit_wechatMP_menu',
                id,
                name: $('#name').val(),
                parent: $('#parent').val(),
                sort: $('#sort').val(),
                type: $('#type').val(),
                value: $('#value').val()
            }
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data,
                success: function (res) {
                    if (res.success) {
                        JUI.Toast.success(res.data.message, 1500)
                        setTimeout(() => {
                            window.location.href = '<?php echo admin_url("admin.php?page=wechat-mp&tab=menus"); ?>'
                        }, 1500)
                    } else {
                        JUI.Toast.error(res.data.message, 2000)
                    }
                },
                error: function (res) {
                    JUI.Toast.error(res.responseJSON.data.message, 2000)
                },
                complete: function () {
                    setTimeout(() => {
                        submit.removeAttr('disabled')
                        submit.text(oldText)
                    }, 2000)
                }
            })
        })
    });
</script>