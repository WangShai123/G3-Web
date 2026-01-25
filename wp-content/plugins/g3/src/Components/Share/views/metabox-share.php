<?php
use JEALER\G3\Components;
use JEALER\G3\Utilities\Context;

$loader       = Context::get('loader');
$option       = Components::getProperty('Share', 'option');
$mediaLibrary = $option['wechatMediaLibrary'] ?? false;
$qqZone       = $option['qqZone'] ?? false;
$weiBo        = $option['weiBo'] ?? false;
$douYin       = $option['douYin'] ?? false;
$tipSpan      = $loader->x() ? '<span style="color:red">' . __('Unauthorized', 'G3') . '</span>' : '';

if ($mediaLibrary) : ?>
        <fieldset>
            <label>
                <input type="checkbox" id="wechatMediaLibrary" name="shareToWechatMediaLibrary"
                    title="<?php _e('Publish to Wechat OA Media Library', 'G3'); ?>">
                <span><?php _e('Wechat OA Media Library', 'G3'); ?></span>
                <?php echo $tipSpan; ?>
            </label>
        </fieldset>
<?php endif;
if ($qqZone) : ?>
        <fieldset>
            <label>
                <input type="checkbox" id="qqZone" name="shareToQQZone" title="<?php _e('Publish to QQ Zone', 'G3'); ?>">
                <span><?php _e('QQ Zone', 'G3'); ?></span>
                <?php echo $tipSpan; ?>
            </label>
        </fieldset>
<?php endif;
if ($douYin) : ?>
        <fieldset>
            <label>
                <input type="checkbox" id="douYin" name="shareToDouYin" title="<?php _e('Publish to DouYin', 'G3'); ?>">
                <span><?php _e('DouYin', 'G3'); ?></span>
                <?php echo $tipSpan; ?>
            </label>
        </fieldset>
<?php endif;
if ($weiBo) : ?>
        <fieldset>
            <label>
                <input type="checkbox" id="weiBo" name="shareToWeiBo" title="<?php _e('Publish to Sina WeiBo', 'G3'); ?>">
                <span><?php _e('Sina WeiBo', 'G3'); ?></span>
                <?php echo $tipSpan; ?>
            </label>
        </fieldset>
<?php endif;

wp_add_inline_script("jui", "
const tooltipElements = document.querySelectorAll('[data-tooltip]');
    tooltipElements.forEach(element => {
        let tooltip = new jui.tooltip(element, {
            message: element.dataset.tooltip,
        });
    });
");
?>
<style>
    #g3_metabox_share .inside>fieldset {
        margin-top: 8px
    }
</style>