<?php
use JEALER\G3\Utilities\Frontend;
use JEALER\G3\Services\PageService;
if (is_user_logged_in() || !PageService::isAdminLogin()) {
    wp_safe_redirect(esc_url(home_url()), 302, get_bloginfo('name'));
    return;
}
Frontend::loadStyle('jui');
get_header();
?>

<div class="flex flex-col justify-center items-center h-screen w-full container">
    <h1 class="text-center"><?php _e('Admin Login'); ?></h1>
    <section>
        <form class="j-form is-vertical is-item-vertical" id="adminLogin" data-form="adminLogin">
            <div class="form-item">
                <label for="username" class="item-label is-required"><?php _e('Username'); ?></label>
                <div class="form-control">
                    <input type="text" class="j-input" id="username" placeholder="Enter Username"
                        autocomplete="username" minlength="5" required />
                </div>
            </div>
            <div class="form-item">
                <label for="password" class="item-label is-required"><?php _e(text: 'Password'); ?></label>
                <div class="form-control">
                    <input type="password" class="j-input" id="password" placeholder="Enter password"
                        autocomplete="password" minlength="5" required />
                </div>
            </div>
            <div class="form-buttons">
                <button type="submit" class="j-button is-primary"><?php _e('Submit'); ?></button>
                <button type="reset" class="j-button is-ghost"><?php _e('Reset', 'G3'); ?></button>
            </div>
        </form>
    </section>
</div>

<script type="module">
    import JUI from '<?php echo G3_JS_URL . '/es/jui.js'; ?>'
    document.addEventListener('DOMContentLoaded', function () {
        document.documentElement.classList.add('j-theme-indigo', 'j-radius-sm');

        const form = document.getElementById('adminLogin');
        form.addEventListener('submit', function (e) {
            e.preventDefault();

            form.querySelector('button[type="submit"]').disabled = true;
            setTimeout(function () {
                form.querySelector('button[type="submit"]').disabled = false;
            }, 2000);

            const data = {
                username: form.querySelector('#username').value,
                password: form.querySelector('#password').value
            }
            const origin = window.location.origin;
            fetch(origin + '/wp-json/api/v1/login/admin', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify(data)
            }).then(res => res.json())
                .then(res => {
                    if (res.code === 200) {
                        JUI.Toast.success(res.message, 2000)
                        setTimeout(function () {
                            window.location.href = origin + '/wp-admin';
                        }, 1000);
                    } else {
                        JUI.Toast.error(res.message, 2000)
                    }
                }).catch(function (error) {
                    console.log('error');
                });
        })
    })
</script>
<style>
    .container {
        margin-top: -20px;
    }

    section {
        display: flex;
        justify-content: center;
    }

    form {
        width: 480px;
        min-width: 310px;
    }
</style>
<?php
get_footer();
