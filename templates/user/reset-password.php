<?php
use JEALER\G3\Core\Container\Container;
use JEALER\G3\Services\MailerService;
use JEALER\G3\Services\UserService;
use JEALER\G3\Utilities\Element;

if (
    is_user_logged_in()
    || (!$context['valid'] && !$context['success'])
) {
    wp_redirect(home_url(), 302, 'G3');
    return;
}
/** @var UserService $userService */
$userService = Container::run()->get(UserService::class);
$context     = $userService->resetPasswordContext();
get_header();
?>

<div class="container">
    <main class="g3-reset-password" style="max-width:520px;margin:64px auto;padding:0 16px;">
        <h1><?php esc_html_e('Password Reset'); ?></h1>

        <?php
        echo '<p>' . __('Enter your new password below or generate one.') . '</p>';
        if ($context['message'] !== '') {
            echo Element::tip(__($context['message']), false, $context['success'] ? 'success' : 'danger');
        }
        if ($context['success']) : ?>
            <p>
                <a href="<?php echo esc_url($context['loginUrl']); ?>">
                    <?php esc_html_e('Login'); ?>
                </a>
            </p>
        <?php elseif ($context['valid']) : ?>
            <form method="post" action="" class="j-form is-vertical is-item-vertical">
                <?php wp_nonce_field('g3_reset_password'); ?>
                <input type="hidden" name="login" value="<?php echo esc_attr($context['login']); ?>">
                <input type="hidden" name="key" value="<?php echo esc_attr($context['key']); ?>">
                <div class="form-item">
                    <label for="password" class="item-label is-required">
                        <?php esc_html_e('New password'); ?>
                    </label>
                    <div class="form-control">
                        <input id="password" name="password" type="password" required minlength="8"
                            autocomplete="new-password" class="j-input is-lg w-full">
                    </div>
                </div>
                <div class="form-item">
                    <label for="password_confirm" class="item-label is-required">
                        <?php esc_html_e('Confirm new password'); ?>
                    </label>
                    <div class="form-control">
                        <input id="password_confirm" name="password_confirm" type="password" required minlength="8"
                            autocomplete="new-password" class="j-input is-lg w-full">
                    </div>
                </div>
                <div class="form-buttons">
                    <button type="submit" class="j-button is-default is-lg">
                        <?php esc_html_e('Reset Password'); ?>
                    </button>
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

<?php get_footer(); ?>