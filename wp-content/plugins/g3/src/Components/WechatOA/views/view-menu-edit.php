<?php
use JEALER\G3\Services\WechatOAService;
use JEALER\G3\Utilities\Image;

$id          = $_GET['id'] ?? 0;
$menus       = WechatOAService::getMenus();
$formatMenus = WechatOAService::formatMenus($menus, '└─');
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
$appId = $data['app_id'] ?? '';

$options = [
    '1'  => 'view',
    '2'  => 'click',
    '3'  => 'scancode_push',
    '4'  => 'scancode_waitmsg',
    '5'  => 'pic_sysphoto',
    '6'  => 'pic_photo_or_album',
    '7'  => 'pic_weixin',
    '8'  => 'location_select',
    '9'  => 'view_miniprogram',
    '10' => 'media_id',
    '11' => 'view_limited',
    '12' => 'article_id',
    '13' => 'article_view_limited',
];
?>
<div class="wrap">
    <h1 class="wp-heading-inline"><?php echo $id ? __('Edit') : __('Add New Menu', 'G3') ?></h1>
    <a href="<?php echo admin_url('admin.php?page=wechat-oa&tab=menus'); ?>" class="page-title-action">
        <?php _e('Back') ?>
    </a>
    <table class="form-table" role="presentation">
        <tbody>
            <!-- name -->
            <tr class="item-input-long">
                <th scope="row"><label for="name" class="is-required"><?php _e('Name'); ?></label></th>
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
                <th scope="row"><label for="sort" class="is-required"><?php _e('Sort', 'G3'); ?></label></th>
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
                <th scope="row"><label for="type" class="is-required"><?php _e('Type', 'G3'); ?></label></th>
                <td>
                    <fieldset>
                        <legend class="screen-reader-text"><span><?php _e('Type', 'G3'); ?></span></legend>
                        <label for="type">
                            <select name="type" id="type">
                                <?php
                                foreach ($options as $key => $label) {
                                    $selected = ($key == $type) ? 'selected' : '';
                                    echo '<option value="' . esc_attr($key) . '" ' . $selected . '>' . WechatOAService::renderMenuType($key) . '</option>';
                                }
                                ?>

                            </select>
                        </label>
                    </fieldset>
                </td>
            </tr>
            <!-- value -->
            <tr class="item-input-long">
                <th scope="row"><label for="value" class="is-required">Key/URL/ID</label></th>
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
            <!-- app_id -->
            <tr class="item-input-long">
                <th scope="row"><label for="app_id">App ID</label></th>
                <td>
                    <fieldset>
                        <legend class="screen-reader-text"><span>App ID</span></legend>
                        <label for="app_id">
                            <input type="text" id="app_id" name="app_id" value="<?php echo $appId; ?>"
                                class="regular-text">
                            <p class="description">
                                <?php _e('When you want to jump to a mini program, you need to fill in the App ID of the mini program.', 'G3'); ?>
                            </p>
                        </label>
                    </fieldset>
                </td>
            </tr>
        </tbody>
    </table>
    <p class="submit">
        <button type="button" id="submit" class="button button-primary"><?php _e('Save Changes'); ?></button>
    </p>

    <div class="j-tip is-default">
        <div class="tip-title"><?php _e('Tip', 'G3'); ?></div>
        <div class="tip-content">
            <div class="tip-wrap">
                <p><?php _e('Wechat Official Account supports 13 types of menu:', 'G3'); ?></p>
                <ol>
                    <li>
                        <p><?php _e('View URL', 'G3'); ?></p>
                        <ul>
                            <li><?php _e('Slug'); ?>: <code>view</code></li>
                            <li>
                                <?php
                                echo __('Description') . ': ' .
                                    __('Users click and jump directly to the specified web link.', 'G3');
                                ?>
                            </li>
                        </ul>
                    </li>
                    <li>
                        <p><?php _e('Click Event', 'G3'); ?></p>
                        <ul>
                            <li><?php _e('Slug'); ?>: <code>click</code></li>
                            <li>
                                <?php
                                echo __('Description') . ': ' .
                                    __('Users click and an event will be pushed to the developer server.', 'G3');
                                ?>
                            </li>
                        </ul>
                    </li>
                    <li>
                        <p><?php _e('Scan Code', 'G3'); ?></p>
                        <ul>
                            <li><?php _e('Slug'); ?>: <code>scancode_push</code></li>
                            <li>
                                <?php
                                echo __('Description') . ': ' .
                                    __('Users click and scan code, the scan result will be pushed to the developer server.', 'G3');
                                ?>
                            </li>
                        </ul>
                    </li>
                    <li>
                        <p><?php _e('Scan & Alert', 'G3'); ?></p>
                        <ul>
                            <li><?php _e('Slug'); ?>: <code>scancode_waitmsg</code></li>
                            <li>
                                <?php
                                echo __('Description') . ': ' .
                                    __('Users click and scan code, the scan result will be pushed to the developer server, then popup a notify box with message "Message receiving".', 'G3');
                                ?>
                            </li>
                        </ul>
                    </li>
                    <li>
                        <p><?php _e('System Camera', 'G3'); ?></p>
                        <ul>
                            <li><?php _e('Slug'); ?>: <code>pic_sysphoto</code></li>
                            <li>
                                <?php
                                echo __('Description') . ': ' .
                                    __('Users click and take a photo with the system camera, the photo will be sent to the developer server.', 'G3');
                                ?>
                            </li>
                        </ul>
                    </li>
                    <li>
                        <p><?php _e('Photo or Album', 'G3'); ?></p>
                        <ul>
                            <li><?php _e('Slug'); ?>: <code>pic_photo_or_album</code></li>
                            <li>
                                <?php
                                echo __('Description') . ': ' .
                                    __('Users click and take a photo, or select a photo from the album, the photo will be sent to the developer server.', 'G3');
                                ?>
                            </li>
                        </ul>
                    </li>
                    <li>
                        <p><?php _e('WeChat Album', 'G3'); ?></p>
                        <ul>
                            <li><?php _e('Slug'); ?>: <code>pic_weixin</code></li>
                            <li>
                                <?php
                                echo __('Description') . ': ' .
                                    __('Users click and select a photo from the Wechat album, the photo will be sent to the developer server.', 'G3');
                                ?>
                            </li>
                        </ul>
                    </li>
                    <li>
                        <p><?php _e('Location Selector', 'G3'); ?></p>
                        <ul>
                            <li><?php _e('Slug'); ?>: <code>location_select</code></li>
                            <li>
                                <?php
                                echo __('Description') . ': ' .
                                    __('Users click and select a location from the Wechat map, the location information will be sent to the developer server.', 'G3');
                                ?>
                            </li>
                        </ul>
                    </li>
                    <li>
                        <p><?php _e('Mini Program', 'G3'); ?></p>
                        <ul>
                            <li><?php _e('Slug'); ?>: <code>view_miniprogram</code></li>
                            <li>
                                <?php
                                echo __('Description') . ': ' .
                                    __('Users click and jump to the specified mini program.', 'G3');
                                ?>
                            </li>
                        </ul>
                    </li>
                    <li>
                        <p><?php _e('Media Library', 'G3'); ?></p>
                        <ul>
                            <li><?php _e('Slug'); ?>: <code>media_id</code></li>
                            <li>
                                <?php
                                echo __('Description') . ': ' .
                                    __('Users click and return the media content of the specified id.', 'G3');
                                ?>
                            </li>
                        </ul>
                    </li>
                    <li>
                        <p><?php _e('Article URL', 'G3'); ?></p>
                        <ul>
                            <li><?php _e('Slug'); ?>: <code>media_id</code></li>
                            <li>
                                <?php
                                echo __('Description') . ': ' .
                                    __('Users click and view the article of the specified URL.', 'G3');
                                ?>
                            </li>
                        </ul>
                    </li>
                    <li>
                        <p><?php _e('Article ID', 'G3'); ?></p>
                        <ul>
                            <li><?php _e('Slug'); ?>: <code>view_url</code></li>
                            <li>
                                <?php
                                echo __('Description') . ': ' .
                                    __('Users click and view the article of the specified ID.', 'G3');
                                ?>
                            </li>
                        </ul>
                    </li>
                    <li>
                        <p><?php _e('Limited Article URL', 'G3'); ?></p>
                        <ul>
                            <li><?php _e('Slug'); ?>: <code>view_id</code></li>
                            <li>
                                <?php
                                echo __('Description') . ': ' .
                                    __('Users click and view the article of the specified URL with limited access.', 'G3');
                                ?>
                            </li>
                        </ul>
                    </li>
                </ol>
            </div>
        </div>

    </div>
</div>

<style>
    .tip-wrap>p {
        margin-top: 0;
        margin-bottom: 8px;
    }

    .tip-wrap>ol>li {
        margin-bottom: 8px;
    }

    .tip-wrap>ol>li>p {
        margin-top: 0;
        margin-bottom: 8px;
    }

    .tip-wrap>ol>li>ul {
        list-style: disc;
        font-size: 12px;
        margin-left: 12px;
    }
</style>

<script>
    const $ = jQuery
    $(document).ready(function () {
        const { Toast } = jui
        const { success, error } = Toast
        const submit = $('#submit')
        const oldText = submit.text()
        submit.on('click', function () {
            this.disabled = true
            this.innerHTML = '<div class="animate-spin" style="width:24px"><?php echo Image::icon('loader'); ?></div>'
            const id = '<?php echo $id; ?>'
            const data = {
                action: 'g3_edit_wechatOA_menu',
                id,
                name: $('#name').val(),
                parent: $('#parent').val(),
                sort: $('#sort').val(),
                type: $('#type').val(),
                value: $('#value').val(),
                appId: $('#app_id').val()
            }
            $.post(ajaxurl, data, function (res) {
                if (res.success) {
                    success(res.data.message, 1000)
                    setTimeout(() => {
                        window.location.href = '<?php echo admin_url("admin.php?page=wechat-oa&tab=menus"); ?>'
                    }, 1000)
                } else {
                    error(res.data.message, 2000)
                }
                setTimeout(() => {
                    submit.removeAttr('disabled')
                    submit.text(oldText)
                }, 1000)
            })
        })
    })
</script>