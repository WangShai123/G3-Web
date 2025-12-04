<?php
use JEALER\G3\Utilities\Frontend;
if (array_key_exists('g3_option_wallet', $_POST) && $_POST['g3_option_wallet']) {
    update_option('g3_option_wallet', $_POST['g3_option_wallet']);
    add_settings_error('withdrawal', '1', __('Updated!', 'G3'), 'updated');
}
settings_errors('withdrawal');
Frontend::loadStyle('jui');
?>
<div class="j-tip mt-4 is-default">
    <div class="tip-title"><?php _e('Tip', 'G3'); ?></div>
    <div class="tip-content"><?php _e('Coming soon.', 'G3'); ?></div>
</div>
<form action="" method="post">
    <?php
    settings_fields('withdrawalSettings');
    do_settings_sections('wallet-setting&tab=withdrawal');
    submit_button();
    ?>
</form>