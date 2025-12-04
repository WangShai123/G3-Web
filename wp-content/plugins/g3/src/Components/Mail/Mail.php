<?php
namespace JEALER\G3\Components;
use JEALER\G3\Components;
use JEALER\G3\Utilities\Container;
use JEALER\G3\Utilities\Option;
use PHPMailer\PHPMailer\PHPMailer;
class Mail extends Components {
    public string $optionKey = 'g3_option_mail';
    public array $option = [];
    public string $templateKey = 'g3_option_mail_template';
    public array $template = [];
    public string $setGroup = 'set';
    protected function options(): void
    {
        $option         = Option::get($this->optionKey, [
            'enable'     => '0',
            'nickname'   => '',
            'server'     => '',
            'port'       => '',
            'encryption' => '0',
            'address'    => '',
            'secret'     => '',
        ]);
        $this->option   = Option::cache($this->optionKey, $option);
        $template       = Option::get($this->templateKey, [
            'enable'         => '0',
            'register'       => '',
            'resetPassword'  => '',
            'paymentSuccess' => '',
        ]);
        $this->template = Option::cache($this->templateKey, $template);
    }

    #[\Override]
    protected function init(): void
    {
        add_action('phpmailer_init', [$this, 'smtpInit']);
    }
    #[\Override]
    protected function admin(): void
    {
        $this->settings();
        $this->systemEmailHandle();
        add_action('wp_mail_failed', function ($wp_error) {
            error_log('Mail failed: ' . print_r($wp_error, true));
        });

        add_action('wp_mail_succeeded', function ($mail_data) {
            error_log('Mail succeeded: ' . print_r($mail_data, true));
        });
    }
    #[\Override]
    protected function adminMenu(): void
    {
        $this->submenu();
    }

    private function submenu()
    {
        add_submenu_page(
            'options-general.php',
            __('Email', 'G3'),
            __('Email', 'G3'),
            'manage_options',
            'mail',
            [$this, 'render'],
            13
        );
    }
    public function render(): void
    {
        echo '<div class="wrap">';
        echo '<h1>' . __('Email', 'G3') . '</h1>';
        $tabs = [
            'set'      => __('General Settings', 'G3'),
            'template' => __('Email Templates', 'G3'),
            'test'     => __('Email Test', 'G3')
        ];
        Container::tab('Mail', 'set', $tabs);
        echo '</div>';
    }
    private function settings(): void
    {
        add_settings_section(
            $this->setGroup,
            null,
            '__return_false',
            'mail'
        );
        register_setting(
            $this->setGroup,
            $this->optionKey,
        );
        Container::settingFields('mail', $this->setGroup, [
            [
                'id'       => 'enable',
                'title'    => __('Enable', 'G3'),
                'callback' => function () {
                    echo Container::enable(
                        $this->optionKey,
                        $this->option,
                        'enable',
                        __('System Email', 'G3'),
                        __('The system will use SMTP to send emails.', 'G3'),
                    );
                },
                'args'     => [
                    'label_for' => 'enable',
                    'class'     => 'field-enable'
                ]
            ],
            [
                'id'       => 'nickname',
                'title'    => __('Nickname', 'G3'),
                'callback' => function () {
                    echo Container::input(
                        $this->optionKey,
                        $this->option,
                        'nickname',
                        __('Nickname', 'G3'),
                    );
                },
                'args'     => [
                    'label_for' => 'nickname',
                    'class'     => 'field-nickname'
                ]
            ],
            [
                'id'       => 'server',
                'title'    => __('Server', 'G3'),
                'callback' => function () {
                    echo Container::input(
                        $this->optionKey,
                        $this->option,
                        'server',
                        __('Server', 'G3'),
                        __('Please fill in the SMTP address of the mail server, for example <code>smtp.x.com</code>.', 'G3'),
                    );
                },
                'args'     => [
                    'label_for' => 'server',
                    'class'     => 'field-server'
                ]
            ],
            [
                'id'       => 'port',
                'title'    => __('Port', 'G3'),
                'callback' => function () {
                    echo Container::input(
                        $this->optionKey,
                        $this->option,
                        'port',
                        __('Port', 'G3'),
                        __('Please fill in the SMTP port of the mail server, for example <code>465</code>.', 'G3'),
                    );
                },
                'args'     => [
                    'label_for' => 'port',
                    'class'     => 'field-port'
                ]
            ],
            [
                'id'       => 'encryption',
                'title'    => __('SMTP Encryption', 'G3'),
                'callback' => function () {
                    echo Container::select(
                        $this->optionKey,
                        $this->option,
                        'encryption',
                        __('SMTP Encryption', 'G3'),
                        __('Please select the appropriate encryption method based on the configuration of the mail server.', 'G3'),
                        '',
                        [
                            '0' => __('No Encryption', 'G3'),
                            '1' => 'SSL',
                            '2' => 'TLS',
                        ]
                    );
                },
                'args'     => [
                    'label_for' => 'encryption',
                    'class'     => 'field-encryption'
                ]
            ],
            [
                'id'       => 'address',
                'title'    => __('Sender\'s Email Address', 'G3'),
                'callback' => function () {
                    echo Container::input(
                        $this->optionKey,
                        $this->option,
                        'address',
                        __('Sender\'s Email Address', 'G3'),
                        __('Please enter the email address from which the sending operation will be executed.', 'G3'),
                        'email'
                    );
                },
                'args'     => [
                    'label_for' => 'address',
                    'class'     => 'field-address'
                ]
            ],
            [
                'id'       => 'secret',
                'title'    => __('Sender\'s Email Secret', 'G3'),
                'callback' => function () {
                    echo Container::input(
                        $this->optionKey,
                        $this->option,
                        'secret',
                        __('Sender\'s Email Secret', 'G3'),
                        __('Please fill in the email password or authorization code according to the service rules of the email provider.', 'G3'),
                        'password'
                    );
                },
                'args'     => [
                    'label_for' => 'secret',
                    'class'     => 'field-secret'
                ]
            ]
        ]);

        // template page
        add_settings_section(
            'template',
            null,
            '__return_false',
            'mail&tab=template'
        );
        register_setting(
            'template',
            $this->templateKey
        );
        Container::settingFields(
            'mail&tab=template',
            'template',
            [
                [
                    'id'       => 'enable',
                    'title'    => __('Custom Email Templates', 'G3'),
                    'callback' => function () {
                        echo Container::enable(
                            $this->templateKey,
                            $this->template,
                            'enable',
                            __('Custom Email Templates', 'G3'),
                            __('The system will replace the default email templates of the site with custom email templates.', 'G3'),
                        );
                    },
                    'args'     => [
                        'label_for' => 'enable',
                        'class'     => 'field-enable'
                    ]
                ],
                [
                    'id'       => 'register',
                    'title'    => __('User Registration Notification Template', 'G3'),
                    'callback' => function () {
                        echo Container::textarea(
                            $this->templateKey,
                            $this->template,
                            'register',
                            __('User Registration Notification Template', 'G3'),
                            __('After successful user registration, send an email notification to the user.', 'G3'),
                        );
                    },
                    'args'     => [
                        'label_for'         => 'register',
                        'class'             => 'field-register',
                        'sanitize_callback' => 'wp_kses_post'
                    ]
                ],
                [
                    'id'       => 'resetPassword',
                    'title'    => __('Password Recovery Notification Template', 'G3'),
                    'callback' => function () {
                        echo Container::textarea(
                            $this->templateKey,
                            $this->template,
                            'resetPassword',
                            __('Password Recovery Notification Template', 'G3'),
                            __('After the user retrieves the password, send an email notification to the user.', 'G3'),
                        );
                    },
                    'args'     => [
                        'label_for'         => 'resetPassword',
                        'class'             => 'field-resetPassword',
                        'sanitize_callback' => 'wp_kses_post'
                    ]
                ],
                [
                    'id'       => 'paymentSuccess',
                    'title'    => __('Order Payment Receipt Template', 'G3'),
                    'callback' => function () {
                        echo Container::textarea(
                            $this->templateKey,
                            $this->template,
                            'paymentSuccess',
                            __('Order Payment Receipt Template', 'G3'),
                            __('Send an email notification to the user after successful payment.', 'G3'),
                        );
                    },
                    'args'     => [
                        'label_for'         => 'paymentSuccess',
                        'class'             => 'field-paymentSuccess',
                        'sanitize_callback' => 'wp_kses_post'
                    ]
                ]
            ]
        );

        // test page
        add_settings_section(
            'test',
            null,
            '__return_false',
            'mail&tab=test'
        );
        register_setting(
            'test',
            'test'
        );
        add_settings_field(
            'mailTo',
            __('Test Email Address', 'G3'),
            function () {
                echo Container::input(
                    'test',
                    '',
                    'mailTo',
                    __('Test Email Address', 'G3'),
                    __('Please enter the email address to receive the test email.', 'G3'),
                    'email'
                );
            },
            'mail&tab=test',
            'test',
            [
                'label_for' => 'mailTo',
                'class'     => 'field-mailTo'
            ]
        );
    }

    /**
     * init SMTP config
     * @param PHPMailer $phpmailer
     * @return void
     * @since 1.0.0
     * @author Wang Shai
     * @see https://developer.wordpress.org/reference/hooks/phpmailer_init/
     */
    public function smtpInit(PHPMailer $phpmailer)
    {
        if (!isset($this->option['enable']) || $this->option['enable'] !== '1') {
            return;
        }

        $phpmailer->isSMTP();
        $phpmailer->isHTML(true);
        $phpmailer->CharSet    = 'UTF-8';
        $phpmailer->SMTPAuth   = true;
        $phpmailer->Host       = $this->option['server'];
        $phpmailer->Port       = $this->option['port'];
        $phpmailer->Username   = $this->option['address'];
        $phpmailer->Password   = $this->option['secret'];
        $phpmailer->SMTPSecure = $this->option['encryption'] === '1' ? 'ssl' : ($this->option['encryption'] === '2' ? 'tls' : '');
        $phpmailer->setFrom($this->option['address'], $this->option['nickname'], false);
    }

    /**
     * Disable System Email Notification
     * @return void
     * @since 1.0.0
     * @author Wang Shai
     */
    public function systemEmailHandle()
    {
        if (!isset($this->option['enable']) || $this->option['enable'] !== '1' || !isset($this->template['enable']) || $this->template['enable'] !== '1') {
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
}