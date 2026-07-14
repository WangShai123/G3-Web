<?php
namespace JEALER\G3\Components;

use JEALER\G3\Core\Admin\Panel;
use JEALER\G3\Services\MailerService;
use Override;
use PHPMailer\PHPMailer\PHPMailer;

class Mail extends Components {
    private function default(): array
    {
        return [
            'enable'     => '0',
            'nickname'   => '',
            'server'     => '',
            'port'       => '',
            'encryption' => '0',
            'address'    => '',
            'secret'     => '',
        ];
    }

    private function templateDefaults(): array
    {
        return [
            'enable'         => '0',
            'register'       => '',
            'resetPassword'  => '',
            'paymentSuccess' => '',
        ];
    }

    protected function defaultOption(): array
    {
        return [MailerService::OPTION_KEY => $this->default()];
    }

    private function template(): array
    {
        $defaults = $this->templateDefaults();
        $option   = get_option(MailerService::TEMPLATE_OPTION_KEY, $defaults);
        return is_array($option) ? $option : $defaults;
    }

    protected function hooks(): void
    {
        $this->filter(['wp_mail_from' => [[$this, 'wpMailFrom'], 1]]);
        $this->action(['phpmailer_init' => [[$this, 'smtpInit'], 1]]);
    }

    protected function admin(): void
    {
        $this->systemEmailHandle();
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
                ->option(MailerService::OPTION_KEY, $this->default())
                ->switch('enable', __('System Email', 'G3'), __('The system will use SMTP to send emails.', 'G3'))
                ->input('nickname', __('Nickname', 'G3'))
                ->rowClass('field-nickname')
                ->input('server', __('Server', 'G3'), __('Please fill in the SMTP address of the mail server, for example <code>smtp.x.com</code>.', 'G3'))
                ->rowClass('field-server')
                ->number('port', __('Port', 'G3'), __('Please fill in the SMTP port of the mail server, for example <code>465</code>.', 'G3'))
                ->rowClass('field-port')
                ->select('encryption', __('SMTP Encryption', 'G3'), ['0' => __('No Encryption', 'G3'), '1' => 'SSL', '2' => 'TLS'], __('Please select the appropriate encryption method based on the configuration of the mail server.', 'G3'))
                ->rowClass('field-encryption')
                ->input('address', __('Sender\'s Email Address', 'G3'), __('Please enter the email address from which the sending operation will be executed.', 'G3'))
                ->rowClass('field-address')
                ->password('secret', __('Sender\'s Email Secret', 'G3'), __('Please fill in the email password or authorization code according to the service rules of the email provider.', 'G3'))
                ->rowClass('field-secret')
                ->tab('template', __('Email Templates', 'G3'))
                ->option(MailerService::TEMPLATE_OPTION_KEY, $this->templateDefaults())
                ->switch('enable', __('Custom Email Templates', 'G3'), __('The system will replace the default email templates of the site with custom email templates.', 'G3'))
                ->textarea('register', __('User Registration Notification Template', 'G3'), __('After successful user registration, send an email notification to the user.', 'G3'))
                ->rowClass('field-register field-template display-none')
                ->textarea('resetPassword', __('Password Recovery Notification Template', 'G3'), __('After the user retrieves the password, send an email notification to the user.', 'G3'))
                ->rowClass('field-resetPassword field-template display-none')
                ->textarea('paymentSuccess', __('Orders Payment Receipt Template', 'G3'), __('Send an email notification to the user after successful payment.', 'G3'))
                ->rowClass('field-paymentSuccess field-template display-none')
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
        if (($option['enable'] ?? '0') !== '1') {
            return;
        }

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

    public function systemEmailHandle(): void
    {
        $option   = $this->option();
        $template = $this->template();
        if (($option['enable'] ?? '0') !== '1' || ($template['enable'] ?? '0') !== '1') {
            return;
        }

        add_filter('password_change_email', '__return_false');
        add_filter('send_password_change_email', '__return_false');
        add_filter('wp_password_change_notification_email', '__return_false');
        add_filter('email_change_email', '__return_false');
        add_filter('send_email_change_email', '__return_false');
        add_filter('automatic_updates_debug_email', '__return_false');
        add_filter('automatic_updates_send_debug_email', '__return_false');
        add_filter('site_admin_email_change_email', '__return_false');
        add_filter('send_site_admin_email_change_email', '__return_false');
    }

    public function wpMailFrom($originalEmail)
    {
        $option = $this->option();
        if (($option['enable'] ?? '0') !== '1') {
            return $originalEmail;
        }

        return is_email($option['address'] ?? '') ? $option['address'] : $originalEmail;
    }
}
