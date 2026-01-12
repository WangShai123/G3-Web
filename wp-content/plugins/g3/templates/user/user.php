<?php
use JEALER\G3\Utilities\Frontend;
use JEALER\G3\Services\AuthService;

if (!is_user_logged_in()) {
    return;
}

Frontend::loadStyle('jui');

$user = wp_get_current_user();
get_header();
$openId = AuthService::OPENID_META_KEY;
?>
<div class="container">
    <div>username: <?php echo $user->display_name; ?></div>
    <div class="my-4">wechat open id:
        <?php echo $user->g3_wechat_openId ? esc_html($user->g3_wechat_openId) : '<span style="color:#999;" id="toBind">(未绑定)</span>'; ?>
    </div>
    <div class="my-4">
        <?php if (empty($user->g3_wechat_openId)) {
            echo '<button id="bind" type="button" class="j-button is-default">Bind</button>';
        } ?>
    </div>
</div>
<?php get_footer(); ?>
<script type="module">
    import JUI from '<?php echo G3_JS_URL . '/es/jui.js'; ?>';
    document.addEventListener('DOMContentLoaded', function () {
        const btn = document.querySelector('#bind');
        if (!btn) return;

        btn.addEventListener('click', async function () {
            let content = '';
            const modal = new jui.modal({
                header: false,
                footer: false,
                bgClose: true,
                escClose: true,
                content: content,
                style: 'height: 300px'
            });
            modal.show();
            modal.showLoading();

            try {
                const res = await fetch('/wp-json/api/v1/auth/wechat/bind/qrcode', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    credentials: 'include'
                });
                const data = await res.json();
                if (data.code !== 200) throw new Error(data.message || 'QRCode Generate Failed');
                const content = `
                    <img src="${data.data.url}" alt = "微信绑定二维码"
                    style = "width:200px; height:auto; border:1px solid #eee; border-radius:8px;" />
                    `;

                modal.setContent(content);
                modal.hideLoading();
            } catch (error) {
                modal.hideLoading();
                modal.setContent(error.message);
                setTimeout(() => modal.hide(), 3000);
            }
        });
    })
</script>