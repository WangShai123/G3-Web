<?php
use JEALER\G3\Utilities\Frontend;
use JEALER\G3\Utilities\Image;
use JEALER\G3\Services\PageService;

$name = get_bloginfo('name');
if (is_user_logged_in() || !PageService::isAdminLogin()) {
    wp_safe_redirect(esc_url(home_url()), 302, $name);
    return;
}
Frontend::loadStyle('jui');
Frontend::loadScript('jui');
get_header();
?>
<div class="j-background-grid"></div>
<div class="flex flex-col justify-center items-center h-screen w-full">
    <h1 class="text-center -translate-y-24px"><?php echo $name; ?></h1>
    <section class="flex justify-center -translate-y-24px">
        <form class="j-form is-vertical is-item-vertical" id="login" style="width:480px;min-width:300px">
            <div class="form-item">
                <label for="username" class="item-label is-required"><?php _e('Username'); ?></label>
                <div class="form-control">
                    <input type="text" class="j-input" id="username"
                        placeholder="<?php _e('Enter Username Please', 'G3'); ?>" autocomplete="username" minlength="5"
                        maxLength="64" required />
                </div>
            </div>
            <div class="form-item">
                <label for="password" class="item-label is-required"><?php _e(text: 'Password'); ?></label>
                <div class="form-control">
                    <input type="password" class="j-input" id="password"
                        placeholder="<?php _e('Enter Password Please', 'G3'); ?>" autocomplete="password" minlength="5"
                        maxLength="64" required />
                </div>
            </div>
            <div class="form-item" id="warning"></div>
            <div class="form-buttons justify-center mt-2" id="action">
                <button type="submit" class="j-button is-primary w-full" id="submit"><?php _e('Submit'); ?></button>
                <button type="reset" class="j-button is-default w-full"><?php _e('Cancel'); ?></button>
            </div>
        </form>
    </section>
</div>

<style>
    .-translate-y-24px {
        transform: translateY(-24px);
    }

    .e {
        border-color: var(--red-9) !important;
    }
</style>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        document.documentElement.classList.add('j-theme-indigo', 'j-radius-sm', 'dark');
        bg();

        const name = 'oaLoginCookie';
        const form = document.querySelector('#login');
        const action = form.querySelector('#action');
        const submitBtn = form.querySelector('#submit');
        const username = form.querySelector('#username');
        const password = form.querySelector('#password');
        const warning = form.querySelector('#warning');

        if (!submitBtn.dataset.originalText) {
            submitBtn.dataset.originalText = submitBtn.textContent.trim();
        }

        let isSubmitting = false;
        let lastCookie = null;
        const timer = setInterval(updateSubmitButtonState, 200);
        updateSubmitButtonState();

        form.addEventListener('submit', function (e) {
            e.preventDefault();

            isSubmitting = true;

            const text = submitBtn.dataset.originalText;
            const loader = '<?php echo Image::icon('loader', 'spin'); ?>';

            submitBtn.disabled = true;
            submitBtn.innerHTML = loader;

            const origin = window.location.origin;
            fetch(origin + '/wp-json/api/v1/oa/admin/auth', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    username: username.value,
                    password: password.value
                })
            }).then(res => res.json())
                .then(res => {
                    if (res.code === 200) {
                        JUI.Toast.success(res.message, 1500)
                        setTimeout(function () {
                            window.location.href = origin + '/dashboard';
                        }, 1500);
                        clearInterval(timer);
                    } else {
                        JUI.Toast.error(res.message, 2000)

                        if (res.code === 429) {
                            const expireTime = new Date(Date.now() + 60 * 5 * 1000);
                            document.cookie = `${name}=1; expires=${expireTime.toUTCString()}; path=/; SameSite=Strict`;
                        } else {
                            document.cookie = `${name}=; expires=Thu, 01 Jan 1970 00:00:00 UTC; path=/`;
                        }
                    }
                    setTimeout(function () {
                        submitBtn.innerHTML = text;
                        submitBtn.disabled = false;
                        isSubmitting = false;
                    }, 2000);
                })
                .catch(function (error) {
                    console.error('Login request failed:', error);
                    JUI.Toast.error('Login Request Failed', 2000);
                    document.cookie = "oa_login=; expires=Thu, 01 Jan 1970 00:00:00 GMT; path=/";
                    isSubmitting = false;
                });
        })

        function updateSubmitButtonState() {
            if (isSubmitting) return;

            const cookie = getCookie(name);

            if (cookie === lastCookie) {
                return;
            }
            lastCookie = cookie;

            const msg = '<div style="color:var(--red-9)">* <?php _e("Due to frequent illegal requests, the login function has been locked. Please try again 5 mins later.", "G3"); ?></div>';

            if (cookie === '1') {
                warning.innerHTML = msg;
                submitBtn.disabled = true;
                username.classList.add('e');
                password.classList.add('e');
                username.disabled = true;
                password.disabled = true;
            } else {
                warning.innerHTML = '';
                submitBtn.disabled = false;
                username.classList.remove('e');
                password.classList.remove('e');
                username.disabled = false;
                password.disabled = false;
            }
        }
    })

    const bg = function () {
        const isDark = document.documentElement.classList.contains('dark')
        let themeBox = null
        if (isDark) {
            if (!themeBox) {
                themeBox = document.createElement('div')
                Object.assign(themeBox.style, {
                    position: 'absolute',
                    top: '0',
                    left: '0',
                    width: '100%',
                    height: '60vh',
                    minHeight: '480px',
                    pointerEvents: 'none',
                    opacity: '0.6'
                })
                document.body.prepend(themeBox)
            }
            const themeValues = Array.from(document.documentElement.classList)
                .filter((cls) => cls.startsWith('j-theme-'))
                .map((cls) => cls.replace('j-theme-', ''))

            if (themeValues.length) {
                const themeName = themeValues[0]
                themeBox.style.background = `linear-gradient(to bottom, var(--${themeName}-4), transparent)`
            }
        } else {
            if (themeBox && themeBox.parentNode) {
                themeBox.parentNode.removeChild(themeBox)
                themeBox = null
            }
        }
    }

    function getCookie(name) {
        const value = `; ${document.cookie}`;
        const parts = value.split(`; ${name}=`);
        if (parts.length === 2) return parts.pop().split(';').shift();
        return null;
    }
</script>
<?php
get_footer();
