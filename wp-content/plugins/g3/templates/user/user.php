<?php

$user = wp_get_current_user();
?>
<div class="container">
    <div>username: <?php echo $user->display_name; ?></div>
    <div>email:
        <?php echo $user->user_email; ?>
    </div>
    <div>role:
        <?php echo $user->roles[0]; ?>
    </div>
    <div>wechat open id:
        <?php echo $user->g3_wechat_openId; ?>
    </div>
</div>