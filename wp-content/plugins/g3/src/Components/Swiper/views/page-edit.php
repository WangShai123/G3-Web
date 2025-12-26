<?php
use JEALER\G3\Utilities\Frontend;
use JEALER\G3\Services\SwiperService;
wp_enqueue_media();
wp_enqueue_script('media-grid');
wp_enqueue_script('media');
Frontend::loadScript('media.image');
Frontend::loadStyle('jui');
Frontend::loadScript('jui');

$t         = $_GET['t'] ?? '';
$pageTitle = $t === 'new' ? __('Add Swiper', 'G3') : __('Edit Swiper', 'G3');
$id        = '';
if ($t === 'edit' && isset($_GET['id']) && $_GET['id'] !== '') {
    $id       = $_GET['id'];
    $item     = SwiperService::queryById($id);
    $title    = $item['title'] ?? '';
    $media    = $item['media'] ?? '';
    $link     = $item['link'] ?? '';
    $target   = $item['target'] ?? '0';
    $location = $item['location'] ?? '';
    $sort     = $item['sort'] ?? '';
}
?>
<div class="wrap">
    <h1 class="wp-heading-inline"><?php echo $pageTitle; ?></h1>
    <a href="<?php echo admin_url('themes.php?page=swipers'); ?>" class="page-title-action"><?php _e('Back'); ?></a>
    <hr class="wp-header-end">
    <table class="form-table" role="presentation">
        <tbody>
            <tr class="item-input-long">
                <th scope="row"><label for="title"><?php _e('Title'); ?></label></th>
                <td>
                    <fieldset>
                        <legend class="screen-reader-text"><span><?php _e('Title'); ?></span></legend>
                        <label for="title">
                            <input type="text" id="title" name="title" value="<?php echo $title; ?>"
                                class="regular-text">
                        </label>
                    </fieldset>
                </td>
            </tr>
            <tr class="item-input-image">
                <th scope="row"><label for="media"><?php _e('Image'); ?></label></th>
                <td>
                    <fieldset>
                        <legend class="screen-reader-text"><span><?php _e('Image'); ?></span></legend>
                        <label for="media">
                            <input type="text" id="media" name="media" value="<?php echo $media; ?>"
                                class="field-upload-url">
                            <input type="button" class="button button-secondary field-upload-image-button" value="上传">
                            <?php if ($media) : ?>
                                <p class="description preview-wrap"
                                    style="position:relative;width:auto;height:120px;overflow:hidden;"><img
                                        class="preview-image" src="<?php echo $media; ?>" alt="preview image"
                                        style="width:auto;height:120px;object-fit:cover;"><span class="clear-image"
                                        style="position:absolute;top:0;left:0;width:16px;height:16px;line-height:16px;text-align:center;background-color:#f00;color:#fff;cursor:pointer;">×</span>
                                </p>
                            <?php endif; ?>
                        </label>
                    </fieldset>
                </td>
            </tr>
            <tr class="item-input-long">
                <th scope="row"><label for="link"><?php _e('Link'); ?></label></th>
                <td>
                    <fieldset>
                        <legend class="screen-reader-text"><span><?php _e('Link'); ?></span></legend>
                        <label for="link">
                            <input type="text" id="link" name="link" value="<?php echo $link; ?>" class="regular-text">
                        </label>
                    </fieldset>
                </td>
            </tr>
            <tr class="item-input-select">
                <th scope="row"><label for="target"><?php _e('Open Method', 'G3'); ?></label></th>
                <td>
                    <fieldset>
                        <legend class="screen-reader-text"><span><?php _e('Open Method', 'G3'); ?></span></legend>
                        <label for="target">
                            <select id="target" name="targete" class="" title="<?php _e('Open Method', 'G3'); ?>">
                                <option value="0" selected="selected"><?php _e('Current Tab', 'G3'); ?></option>
                                <option value="1"><?php _e('New Tab', 'G3'); ?></option>
                            </select>
                        </label>
                    </fieldset>
                </td>
            </tr>
            <tr class="item-input-select">
                <th scope="row"><label for="location"><?php _e('Location', 'G3'); ?></label></th>
                <td>
                    <fieldset>
                        <legend class="screen-reader-text"><span><?php _e('Location', 'G3'); ?></span></legend>
                        <label for="location">
                            <?php
                            echo '<select id="location" name="location" class="" title="' . __('Location', 'G3') . ' ">';
                            $option = maybe_unserialize(get_option(SwiperService::LOCATION_OPTION_KEY));
                            if ($option) {
                                $location     = explode(',', $item['location']);
                                $selected_any = false;
                                // check if any option is selected
                                foreach ($option as $key => $value) {
                                    if (in_array($key, $location)) {
                                        $selected_any = true;
                                        break;
                                    }
                                }
                                // iterate through options and set selected status
                                foreach ($option as $key => $value) {
                                    $selected = '';
                                    // select if there is a match
                                    if (in_array($key, $location)) {
                                        $selected = 'selected="selected"';
                                    }
                                    // select first option if there is no match
                                    elseif (!$selected_any && $key === array_key_first($option)) {
                                        $selected = 'selected="selected"';
                                    }
                                    echo '<option value="' . esc_attr($key) . '" ' . $selected . '>' . esc_html($value) . '</option>';
                                }
                                echo '</select>';
                            } else {
                                $smg = sprintf(
                                    __('No Position Data Found. Please configure <a href="%s">the Swiper Position</a> first.', 'G3'),
                                    admin_url('themes.php?page=swipers&tab=locations')
                                );
                                echo "</select><p class='description'>{$smg}</p>";
                            }
                            ?>
                        </label>
                    </fieldset>
                </td>
            </tr>
            <tr class="item-input-counter">
                <th scope="row"><label for="sort"><?php _e('Sort', 'G3'); ?></label></th>
                <td>
                    <fieldset>
                        <legend class="screen-reader-text"><span><?php _e('Sort', 'G3'); ?></span></legend>
                        <label for="sort">
                            <input type="number" id="sort" name="sort" value="<?php echo $sort; ?>" min="0" max="100"
                                step="1">
                        </label>
                    </fieldset>
                </td>
            </tr>
            <tr class="item-input-select">
                <th scope="row"><label for="status"><?php _e('Status', 'G3'); ?></label></th>
                <td>
                    <fieldset>
                        <legend class="screen-reader-text"><span><?php _e('Status', 'G3'); ?></span></legend>
                        <label for="status">
                            <select id="status" name="status" class="" title="<?php _e('Status', 'G3'); ?>">
                                <option value="1" selected="selected"><?php _e('Online', 'G3'); ?></option>
                                <option value="0"><?php _e('Offline', 'G3'); ?></option>
                            </select>
                        </label>
                    </fieldset>
                </td>
            </tr>
        </tbody>
    </table>
    <p class="submit">
        <button type="button" id="submit" class="button button-primary">
            <?php _e('Save Changes'); ?>
        </button>
    </p>
</div>

<script>
    jQuery(document).ready(function ($) {
        $('#submit').on('click', function (e) {
            e.preventDefault();
            const title = $('#title').val();
            if (!title) {
                $('#title').focus();
                JUI.Toast.error('<?php _e('The field cannot be empty', 'G3'); ?>', 1500);
                return false;
            }
            const media = $('#media').val();
            if (!media) {
                $('#media').focus();
                JUI.Toast.error('<?php _e('The field cannot be empty', 'G3'); ?>', 1500);
                return false;
            }
            const link = $('#link').val();
            if (!link) {
                $('#link').focus();
                JUI.Toast.error('<?php _e('The field cannot be empty', 'G3'); ?>', 1500);
                return false;
            }
            const target = $('#target').val();
            const location = $('#location').val();
            const sort = $('#sort').val();
            if (!sort) {
                $('#sort').focus();
                JUI.Toast.error('<?php _e('The field cannot be empty', 'G3'); ?>', 1500);
                return false;
            }
            const status = $('#status').val();
            $.post(ajaxurl, {
                action: 'edit_swiper',
                id: '<?php echo $id; ?>',
                title: title,
                media: media,
                link: link,
                target: target,
                location: location,
                sort: sort,
                status: status
            }, function (res) {
                if (res.success) {
                    JUI.Toast.success(res.data.message, 1000);
                    setTimeout(function () {
                        window.location.href = '<?php echo admin_url('themes.php?page=swipers'); ?>';
                    }, 1000);
                } else {
                    JUI.Toast.error(res.data.message, 1500);
                }
            })
        });
    });
</script>