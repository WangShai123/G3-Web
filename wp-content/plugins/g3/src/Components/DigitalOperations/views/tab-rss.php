<?php
use JEALER\G3\Utilities\Frontend;
Frontend::loadStyle('jui');
if (array_key_exists('g3_option_rss', $_POST) && $_POST['g3_option_rss']) {
    update_option('g3_option_rss', $_POST['g3_option_rss']);
    add_settings_error('rss', '1', __('Updated!', 'G3'), 'updated');
}
settings_errors('rss');
?>
<div class="j-tip is-default mt-4">
    <div class="tip-title"><?php _e('Help'); ?></div>
    <div class="tip-content">
        <?php echo '<p class="description">' . __('You can provide the RSS feed address to partners or users for subscribing to receive content from your platform.', 'G3') . '</p>'; ?>
    </div>
</div>
<form action="" method="POST">
    <?php
    settings_fields('rss');
    do_settings_sections('digital-operations&tab=rss');
    submit_button();
    ?>
</form>