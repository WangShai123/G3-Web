<?php
use JEALER\G3\Services\UserService;

if (is_user_logged_in()) {
    wp_safe_redirect(home_url(), 302, 'G3');
    return;
}

$registerConfig = [
    'endpoint' => esc_url_raw(rest_url('api/v1/auth/register/')),
    'loginUrl' => UserService::loginUrl(),
    'homeUrl'  => home_url('/'),
];
$coverUrl       = defined('G3_IMG_URL') ? G3_IMG_URL . '/cover-placeholder.png' : '';

get_header();
?>

<style>
    body {
        background: radial-gradient(circle at 12% -8%,
                color-mix(in srgb, var(--tone-soft) 66%, transparent), transparent 42%), radial-gradient(circle at 86% 0%,
                color-mix(in srgb, var(--state-info-soft) 64%, transparent), transparent 40%), radial-gradient(circle at 20% 96%,
                color-mix(in srgb, var(--state-success-soft) 56%, transparent), transparent 36%), linear-gradient(180deg, var(--ui-bg), var(--ui-bg-subtle));
    }

    .aurora {
        position: fixed;
        inset: 0;
        z-index: 0;
        overflow: hidden;
        pointer-events: none;
    }

    .aurora:before,
    .aurora:after {
        position: absolute;
        border-radius: 999px;
        filter: blur(54px);
        opacity: .42;
        animation: doc-home-float 9s ease-in-out infinite;
        content: "";
    }

    .aurora:after {
        right: 8%;
        bottom: 7%;
        width: 20rem;
        height: 20rem;
        background:
            color-mix(in srgb, var(--state-warning) 18%, transparent);
        animation-delay: -3.6s;
    }

    .aurora::before {
        top: 6%;
        left: 4%;
        width: 18rem;
        height: 18rem;
        background:
            color-mix(in srgb, var(--tone-solid) 22%, transparent);
    }

    .g3-auth-page {
        display: grid;
        place-items: center;
        padding: 56px 18px;
    }

    .g3-auth-shell {
        width: min(100%, 980px);
        display: grid;
        grid-template-columns: minmax(0, .95fr) minmax(360px, 1fr);
        overflow: hidden;
        border-radius: 18px;
        background: var(--ui-bg);
        color: var(--ui-fg);
        box-shadow: var(--shadow-md);
    }

    .g3-auth-visual {
        position: relative;
        min-height: 560px;
        padding: 40px;
        display: flex;
        flex-direction: column;
        justify-content: space-between;
        background: linear-gradient(var(--ui-bg), var(--tone-solid))
    }

    .g3-auth-brand {
        font-weight: 700;
    }

    .g3-auth-copy h1 {
        max-width: 9em;
        margin: 0 0 14px;
        font-size: clamp(34px, 4vw, 56px);
        line-height: 1;
        letter-spacing: 0;
    }

    .g3-auth-copy p {
        max-width: 32em;
        margin: 0;
        color: rgba(255, 255, 255, .84);
        font-size: 16px;
        line-height: 1.75;
    }

    .g3-auth-panel {
        padding: 46px;
        display: flex;
        flex-direction: column;
        justify-content: center;
    }

    .g3-auth-panel h2 {
        margin: 0 0 8px;
        font-size: 30px;
        line-height: 1.15;
        letter-spacing: 0;
    }

    .g3-auth-subtitle {
        margin: 0 0 24px;
        color: #64748b;
        font-size: 15px;
        line-height: 1.6;
    }

    .g3-auth-note {
        margin: -2px 0 0;
        color: #64748b;
        font-size: 12px;
        line-height: 1.5;
    }

    .g3-auth-message {
        display: none;
        padding: 12px 14px;
        border-radius: 10px;
        font-size: 14px;
        line-height: 1.5;
    }

    .g3-auth-message.is-error {
        display: block;
        background: #fef2f2;
        color: #991b1b;
        border: 1px solid #fecaca;
    }

    .g3-auth-message.is-success {
        display: block;
        background: #ecfdf5;
        color: #065f46;
        border: 1px solid #a7f3d0;
    }



    .g3-auth-footer {
        margin: 22px 0 0;
        color: #64748b;
        font-size: 14px;
        text-align: center;
    }

    .g3-auth-footer a {
        color: #047857;
        font-weight: 800;
        text-decoration: none;
    }

    .g3-auth-footer a:hover {
        text-decoration: underline;
    }

    @media (max-width: 820px) {
        .g3-auth-page {
            padding: 24px 12px;
        }

        .g3-auth-shell {
            grid-template-columns: 1fr;
            border-radius: 14px;
        }

        .g3-auth-visual {
            min-height: 230px;
            padding: 28px;
        }

        .g3-auth-copy h1 {
            max-width: none;
            font-size: 34px;
        }

        .g3-auth-panel {
            padding: 30px 22px;
        }
    }
</style>
<div class="aurora" aria-hidden="true"></div>
<main class="g3-auth-page">
    <section class="g3-auth-shell" aria-label="<?php esc_attr_e('Create account', 'G3'); ?>">
        <div class="g3-auth-visual">
            <div class="g3-auth-brand">
                <span><?php echo get_bloginfo('name'); ?></span>
            </div>
            <div class="g3-auth-copy">
                <h1><?php esc_html_e('Register For This Site'); ?></h1>
                <p><?php esc_html_e('Join the site with a secure account and continue directly after registration.', 'G3'); ?>
                </p>
            </div>
        </div>

        <div class="g3-auth-panel">
            <h2><?php esc_html_e('Register'); ?></h2>
            <p class="g3-auth-subtitle"><?php esc_html_e('Take a minute to get an account.', 'G3'); ?>
            </p>

            <form id="g3-register-form" class="j-form is-vertical is-item-vertical" novalidate>
                <div id="g3-register-message" class="g3-auth-message" role="status" aria-live="polite"></div>

                <div class="form-item">
                    <label for="g3-register-username" class="item-label is-required"><?php _e('Username'); ?></label>
                    <div class="form-control">
                        <input class="j-input is-lg" id="g3-register-username" name="username" type="text"
                            autocomplete="username" minlength="3" maxlength="60" required placeholder="">
                    </div>
                </div>

                <div class="form-item">
                    <label for="g3-register-email" class="item-label is-required"><?php _e('Email'); ?></label>
                    <div class="form-control">
                        <input class="j-input is-lg" id="g3-register-email" name="email" type="email"
                            autocomplete="email" maxlength="100" required placeholder="">
                    </div>
                </div>

                <div class="form-item">
                    <label for="g3-register-password" class="item-label is-required"><?php _e('Password'); ?></label>
                    <div class="form-control">
                        <input class="j-input is-lg has-toggle" id="g3-register-password" name="password"
                            type="password" autocomplete="new-password" minlength="12" maxlength="128" required
                            placeholder="">
                        <div class="help-block">
                            <?php _e('Hint: The password should be at least twelve characters long. To make it stronger, use upper and lower case letters, numbers, and symbols like ! " ? $ % ^ &amp; ).'); ?>
                        </div>
                    </div>
                </div>

                <div class="form-item">
                    <label for="g3-register-confirm-password"
                        class="item-label is-required"><?php _e('Confirm password', 'G3'); ?></label>
                    <div class="form-control">
                        <input class="j-input is-lg has-toggle" id="g3-register-confirm-password"
                            name="confirm_password" type="password" autocomplete="new-password" minlength="8"
                            placeholder="" maxlength="128" required>
                    </div>
                </div>

                <div class="form-buttons">
                    <button id="g3-register-submit" class="j-button is-primary is-lg"
                        type="submit"><?php _e('Register'); ?></button>
                    <button type="reset" class="j-button is-ghost is-lg"><?php _e('Reset'); ?></button>
                </div>
            </form>

            <p class="g3-auth-footer">
                <?php esc_html_e('Already have an account?', 'G3'); ?>
                <a href="<?php echo esc_url($registerConfig['loginUrl']); ?>"><?php _e('Login', 'G3'); ?></a>
            </p>
        </div>
    </section>
</main>

<script>
    (function () {
        const config = <?php echo wp_json_encode($registerConfig, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE); ?>;
        const form = document.getElementById('g3-register-form');
        const submit = document.getElementById('g3-register-submit');
        const message = document.getElementById('g3-register-message');

        document.querySelectorAll('[data-toggle-password]').forEach((button) => {
            button.addEventListener('click', () => {
                const input = document.getElementById(button.dataset.togglePassword);
                if (!input) return;

                const visible = input.type === 'text';
                input.type = visible ? 'password' : 'text';
                button.textContent = visible ? '<?php echo __('Show'); ?>' : '<?php echo __('Hide'); ?>';
            });
        });

        form.addEventListener('submit', async (event) => {
            event.preventDefault();
            setMessage('', '');

            const data = Object.fromEntries(new FormData(form).entries());
            data.username = String(data.username || '').trim();
            data.email = String(data.email || '').trim();

            if (!form.checkValidity()) {
                form.reportValidity();
                return;
            }

            if (data.password !== data.confirm_password) {
                setMessage('<?php echo esc_js(__('The two passwords do not match.', 'G3')); ?>', 'error');
                return;
            }

            setLoading(true);

            try {
                const response = await fetch(config.endpoint, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json'
                    },
                    credentials: 'same-origin',
                    body: JSON.stringify(data)
                });

                const result = await response.json().catch(() => ({}));
                if (!response.ok || result.success !== true) {
                    throw new Error(result.message || '<?php echo esc_js(__('Registration failed. Please try again.', 'G3')); ?>');
                }

                setMessage(result.message || '<?php echo esc_js(__('Registration successful.', 'G3')); ?>', 'success');
                window.setTimeout(() => {
                    window.location.href = result.data?.redirect || config.homeUrl;
                }, 650);
            } catch (error) {
                setMessage(error.message || '<?php echo esc_js(__('Registration failed. Please try again.', 'G3')); ?>', 'error');
                setLoading(false);
            }
        });

        function setLoading(loading) {
            submit.disabled = loading;
            submit.textContent = loading
                ? '<span class="icon-loader"></span>'
                : '<?php echo esc_js(__('Create account', 'G3')); ?>';
        }

        function setMessage(text, type) {
            message.textContent = text;
            message.className = 'g3-auth-message' + (type ? ' is-' + type : '');
        }
    })();
</script>

<?php get_footer(); ?>