<?php
namespace JEALER\G3\Components;
use JEALER\G3\Core\Admin\Panel;
use JEALER\G3\Services\MailerService;
use JEALER\G3\Services\UserService;
use PHPMailer\PHPMailer\PHPMailer;
use Override;
use WP_User;

class Mail extends Components {

    protected function defaultOption(): array
    {
        return [MailerService::OPTION_KEY => MailerService::mailDefaults()];
    }
    protected function hooks(): void
    {
        $this->filter([
            'wp_mail_from'                          => [[$this, 'wpMailFrom'], 10, 1],

            /**
             * 面向用户的通知
             * @since 1.0.0
             */
            // 忘记密码通知
            'retrieve_password_notification_email'  => [[$this, 'retrievePasswordNotificationEmail'], 10, 4],
            // 新用户注册通知
            'wp_new_user_notification_email'        => [[$this, 'newUserNotificationEmail'], 10, 3],
            // 密码变更通知
            'password_change_email'                 => [[$this, 'passwordChangeEmail'], 10, 3],
            // 邮箱变更通知
            'email_change_email'                    => [[$this, 'emailChangeEmail'], 10, 3],

            /**
             * 面向管理员的通知
             * @since 1.0.0
             */
            // 新用户注册通知（管理员）
            'wp_new_user_notification_email_admin'  => [[$this, 'newUserAdminNotificationEmail'], 10, 3],
            // 用户密码变更通知（管理员）
            'wp_password_change_notification_email' => [[$this, 'adminPasswordChangeEmail'], 10, 3],
            // 站点管理员邮箱变更通知
            'site_admin_email_change_email'         => [[$this, 'siteAdminEmailChangeEmail'], 10, 3],
            // 自动更新调试通知
            'automatic_updates_debug_email'         => [[$this, 'automaticUpdatesDebugEmail'], 10, 3],
        ]);
        $this->action(['phpmailer_init' => [[$this, 'smtpInit'], 10, 1]]);
    }
    protected function adminMenu(): void
    {
        add_submenu_page('g3-settings', __('Email', 'G3'), __('Email', 'G3'), 'manage_options', 'mail', [$this, 'render'], 13);
    }

    protected function adminPanelPage(): string
    {
        return 'mail';
    }

    protected function adminPanels(): array
    {
        return [
            $this->panel('mail', __('Email', 'G3'))
                ->tab('set', __('General Settings', 'G3'))
                ->option(MailerService::OPTION_KEY, MailerService::mailDefaults())
                ->switch('enable', __('System Email', 'G3'), __('The system will use SMTP to send emails.', 'G3'))
                ->input('nickname', __('Nickname', 'G3'))
                ->rowClass('field-nickname')
                ->input('server', 'SMTP ' . __('Server', 'G3'), __('e.g.', 'G3') . ' <code>smtp.x.com</code>')
                ->rowClass('field-server')
                ->number('port', 'SMTP ' . __('Port', 'G3'), __('e.g.', 'G3') . ' <code>465</code>')
                ->rowClass('field-port')
                ->select('encryption', __('Encryption', 'G3'), ['0' => __('No Encryption', 'G3'), '1' => 'SSL', '2' => 'TLS'])
                ->rowClass('field-encryption')
                ->input('address', __('Sender\'s Email Address', 'G3'))
                ->rowClass('field-address')
                ->password('secret', __('Sender\'s Email Secret', 'G3'))
                ->rowClass('field-secret')
                ->switch('template', __('Custom Email Templates', 'G3'), __('The system will replace the default email templates of the site with custom email templates.', 'G3'))
                ->html('templates', __('Templates'), '<code>./templates/mail/*.php</code>')
                ->tab('notifications', __('Notifications'))
                ->switch('welcome', __('Welcome', 'G3'), sprintf(__('The system will send an email to users when %s.', 'G3'), __('new user registered', 'G3')))
                ->switch('comment', __('Comment'), sprintf(__('The system will send an email to users when %s.', 'G3'), __('post commented on', 'G3')))
                ->switch('comment_reply', __('Reply'), sprintf(__('The system will send an email to users when %s.', 'G3'), __('comment replied to', 'G3')))
                ->switch('welcome_admin', 'Admin-' . __('Welcome', 'G3'), sprintf(__('The system will send an email to administrator when %s.', 'G3'), __('new user registered', 'G3')))
                ->switch('password_changed_admin', 'Admin-' . __('Password Changed', 'G3'), sprintf(__('The system will send an email to administrator when %s.', 'G3'), __('someone password changed', 'G3')))
                ->switch('automatic_updates_debug', 'Admin-' . __('Update') . ' Debug', sprintf(__('The system will send an email to administrator when %s.', 'G3'), __('Update') . ' Debug'))
                ->switch('comment_moderation', 'Admin-' . __('Comment Moderation', 'G3'), sprintf(__('The system will send an email to administrator when %s.', 'G3'), __('new comment moderation', 'G3')))
                ->tab('test', __('Email Test', 'G3')),
        ];
    }
    public function render(): void
    {
        $this->createPanel('set');
    }
    public function smtpInit(PHPMailer $phpmailer): void
    {
        $option = $this->option();
        if (($option['enable'] ?? '0') === '1') {
            $phpmailer->isSMTP();
            $phpmailer->SMTPAuth   = true;
            $phpmailer->Host       = $option['server'] ?? '';
            $phpmailer->Port       = $option['port'] ?? '';
            $phpmailer->Username   = $option['address'] ?? '';
            $phpmailer->Password   = $option['secret'] ?? '';
            $phpmailer->SMTPSecure = ($option['encryption'] ?? '0') === '1' ? 'ssl' : (($option['encryption'] ?? '0') === '2' ? 'tls' : '');
            $phpmailer->isHTML(true);
            $phpmailer->CharSet = 'UTF-8';
            $phpmailer->setFrom($option['address'] ?? '', $option['nickname'] ?? '', false);
        }
    }
    public function wpMailFrom($originalEmail)
    {
        $option = $this->option();
        if (($option['enable'] ?? '0') === '1' && is_email($option['address'] ?? '')) {
            return $option['address'];
        }
        return $originalEmail;
    }

    public function retrievePasswordNotificationEmail(array $email, string $key, string $userLogin, WP_User $user): array
    {
        if (!$this->customTemplatesEnabled()) {
            return $email;
        }

        return $this->templateEmail('reset-password', [
            'user'              => $user,
            'userLogin'         => $userLogin,
            'resetUrl'          => UserService::resetPasswordUrl($user, $key),
            'expirationSeconds' => UserService::RESET_PASSWORD_TTL,
            'expirationMinutes' => (int) ceil(UserService::RESET_PASSWORD_TTL / 60),
        ], $email);
    }

    public function newUserNotificationEmail(array $email, WP_User $user, string $blogname): array
    {
        if (!$this->customTemplatesEnabled()) {
            return $email;
        }

        $key      = get_password_reset_key($user);
        $resetUrl = is_wp_error($key) ? UserService::loginUrl() : UserService::resetPasswordUrl($user, $key);

        return $this->templateEmail('new-user', [
            'user'              => $user,
            'userLogin'         => $user->user_login,
            'resetUrl'          => $resetUrl,
            'loginUrl'          => UserService::loginUrl(),
            'blogname'          => $blogname,
            'expirationSeconds' => UserService::RESET_PASSWORD_TTL,
            'expirationMinutes' => (int) ceil(UserService::RESET_PASSWORD_TTL / 60),
        ], $email);
    }

    public function newUserAdminNotificationEmail(array $email, WP_User $user, string $blogname): array
    {
        if (!$this->customTemplatesEnabled()) {
            return $email;
        }

        return $this->templateEmail('new-user-admin', [
            'user'     => $user,
            'blogname' => $blogname,
        ], $email);
    }

    public function passwordChangeEmail(array $email, array $user, array $userdata): array
    {
        if (!$this->customTemplatesEnabled()) {
            return $email;
        }

        return $this->templateEmail('password-change', [
            'userLogin' => (string) ($user['user_login'] ?? ''),
            'userEmail' => (string) ($user['user_email'] ?? ''),
            'user'      => $user,
            'userdata'  => $userdata,
        ], $email);
    }

    public function emailChangeEmail(array $email, array $user, array $userdata): array
    {
        if (!$this->customTemplatesEnabled()) {
            return $email;
        }

        return $this->templateEmail('email-change', [
            'userLogin' => (string) ($user['user_login'] ?? ''),
            'oldEmail'  => (string) ($user['user_email'] ?? ''),
            'newEmail'  => (string) ($userdata['user_email'] ?? ''),
            'user'      => $user,
            'userdata'  => $userdata,
        ], $email);
    }

    public function adminPasswordChangeEmail(array $email, WP_User $user, string $blogname): array
    {
        if (!$this->customTemplatesEnabled()) {
            return $email;
        }

        return $this->templateEmail('admin-password-change', [
            'user'     => $user,
            'blogname' => $blogname,
        ], $email);
    }

    public function siteAdminEmailChangeEmail(array $email, string $oldEmail, string $newEmail): array
    {
        if (!$this->customTemplatesEnabled()) {
            return $email;
        }

        return $this->templateEmail('site-admin-email-change', [
            'oldEmail' => $oldEmail,
            'newEmail' => $newEmail,
        ], $email);
    }

    public function automaticUpdatesDebugEmail(array $email, int $failures, mixed $results): array
    {
        if (!$this->customTemplatesEnabled()) {
            return $email;
        }

        $mail = $this->templateEmail('automatic-updates-debug', [
            'failures'      => $failures,
            'updateResults' => $results,
            'originalBody'  => (string) ($email['body'] ?? ''),
            'originalEmail' => $email,
        ], [
            'to'      => $email['to'] ?? get_option('admin_email'),
            'subject' => $email['subject'] ?? __('Automatic Updates Debug', 'G3'),
            'message' => $email['body'] ?? '',
            'body'    => $email['body'] ?? '',
            'headers' => $email['headers'] ?? '',
        ]);

        $mail['body'] = $mail['message'];
        return $mail;
    }

    private function customTemplatesEnabled(): bool
    {
        $option = $this->option();
        return ($option['enable'] ?? '0') === '1' && ($option['template'] ?? '0') === '1';
    }

    private function templateEmail(string $template, array $variables, array $fallback): array
    {
        $mail = MailerService::renderTemplate($template, $variables, [
            'to'      => $fallback['to'] ?? '',
            'subject' => $fallback['subject'] ?? __('Notifications'),
            'message' => $fallback['message'] ?? $fallback['body'] ?? '',
            'body'    => $fallback['body'] ?? $fallback['message'] ?? '',
            'headers' => $fallback['headers'] ?? '',
        ]);

        if ($mail === null) {
            return $fallback;
        }

        return array_merge($fallback, array_filter([
            'to'      => $mail['to'] !== '' ? $mail['to'] : ($fallback['to'] ?? ''),
            'subject' => $mail['subject'],
            'message' => $mail['message'],
            'body'    => $mail['body'],
            'headers' => $mail['headers'],
        ], fn($value) => $value !== null));
    }

}
