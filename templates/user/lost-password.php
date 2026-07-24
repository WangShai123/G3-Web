<?php
use JEALER\G3\Core\Container\Container;
use JEALER\G3\Services\UserService;
use JEALER\G3\Utilities\Element;

if (is_user_logged_in()) {
    wp_redirect(home_url(), 302, 'G3');
    return;
}
/** @var UserService $userService */
$userService = Container::run()->get(UserService::class);
$context     = $userService->lostPasswordContext();
get_header();
?>

<div class="vaurora"></div>
<div class="container">
    <main class="g3-lost-password">
        <h1><?php _e('Lost Password'); ?></h1>
        <p>
            <?php _e('Please enter your username or email address. You will receive an email message with instructions on how to reset your password.'); ?>
        </p>
        <?php if ($context['message'] !== '') :
            echo Element::tip($context['message'], false, $context['success'] ? 'success' : 'danger');
        endif;
        if (!$context['success']) : ?>
            <form method="post" action="" class="j-form is-vertical is-item-vertical">
                <?php wp_nonce_field('g3_lost_password'); ?>
                <div class="form-item">
                    <label for="user_login" class="item-label is-required">
                        <?php _e('Username or Email Address'); ?>
                    </label>
                    <div class="form-control">
                        <input id="user_login" name="user_login" type="text" required autocomplete="username" placeholder=""
                            class="j-input is-lg w-full">
                    </div>
                </div>
                <div class="form-buttons">
                    <button type="submit" class="j-button is-default is-lg"><?php _e('Get New Password'); ?></button>
                </div>
            </form>
        <?php endif; ?>

        <p>
            <a href="<?php echo home_url(); ?>" style="color:inherit" class="j-button is-ghost">
                <?php echo __('Back'); ?>
            </a>
        </p>
    </main>
</div>

<style>
    body {
        background: radial-gradient(circle at 12% -8%,
                color-mix(in srgb, var(--tone-soft) 66%, transparent), transparent 42%), radial-gradient(circle at 86% 0%,
                color-mix(in srgb, var(--state-info-soft) 64%, transparent), transparent 40%), radial-gradient(circle at 20% 96%,
                color-mix(in srgb, var(--state-success-soft) 56%, transparent), transparent 36%), linear-gradient(180deg, var(--ui-bg), var(--ui-bg-subtle));
        padding-top: 48px;
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

    main {
        max-width: 520px;
        margin-inline: auto;
        padding: 32px;
        background: var(--ui-bg);
        box-shadow: var(--shadow-md);
        border-radius: 18px;
    }

    .j-tip {
        margin-block: 16px;
    }
</style>
<?php get_footer(); ?>