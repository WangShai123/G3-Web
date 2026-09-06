<?php
$renderer->form($panel, $panelTab);

// update WordPress default options.
if (isset($_POST['g3_option_comments'])) {
    $v = $_POST['g3_option_comments'];

    if (isset($v['enable'])) {
        if ($v['enable'] === '1') {
            update_option('default_comment_status', 'open');
        } else {
            update_option('default_comment_status', '');
        }
    }
    if (isset($v['perPage'])) {
        update_option('comments_per_page', $v['perPage']);
    }
    if (isset($v['moderation'])) {
        update_option('comment_moderation', $v['moderation']);
    }
    if (isset($v['hasApproved'])) {
        update_option('comment_previously_approved', $v['hasApproved']);
    }
    if (isset($v['moderationKeys'])) {
        $result = update_option('moderation_keys', $v['moderationKeys']);
    }
    if (isset($v['disallowedKeys'])) {
        $result = update_option('disallowed_keys', $v['disallowedKeys']);
    }
}
