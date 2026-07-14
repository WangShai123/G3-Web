<?php
namespace JEALER\G3\Components;
use JEALER\G3\Core\Admin\Panel;
use JEALER\G3\Services\CustomerService;
use JEALER\G3\Utilities\Frontend;

class Customer extends Components {
    protected function hooks()
    {
        $this->action([
            'wp_footer' => [[$this, 'renderFrontRoot']]
        ]);
    }
    protected function scripts(): void
    {
        if (!$this->enabled()) {
            return;
        }
        Frontend::css('g3.customer.service');
        Frontend::esm('g3.customer.service');
    }
    protected function adminScripts(): void
    {
        if ($this->currentAdminPage() !== 'customer-service') {
            return;
        }

        Frontend::css('g3.customer.admin');
        Frontend::umd('g3.customer.admin');
    }

    protected function adminMenu(): void
    {
        add_submenu_page(
            'g3-settings',
            __('Customer Service', 'G3'),
            __('Customer Service', 'G3'),
            'manage_options',
            'customer-service-setting',
            [$this, 'render'],
            12
        );

        if (($this->option()['enable'] ?? '0') === '1') {
            add_menu_page(
                __('Customer Service', 'G3'),
                __('Customer Service', 'G3'),
                'manage_options',
                'customer-service',
                [$this, 'customerConsolePage'],
                'dashicons-format-chat',
                30
            );
        }
    }
    public function customerConsolePage(): void
    {
        echo '<div class="wrap"><h1 class="wp-heading-inline">' . __('Customer Service', 'G3') . '</h1><div id="g3-customer-admin-root" class="mt-4"></div></div>' . $this->configScript('g3-customer-admin-config', $this->adminConfig());
    }
    protected function adminPanels(): array
    {
        return [
            $this->panel('customer-service-setting', __('Customer Service', 'G3'))
                ->tab('settings', __('General'))
                ->option(CustomerService::OPTION_KEY, CustomerService::defaultOption())
                ->switch('enable', __('Enable'), __('When enabled, a floating customer service icon is shown on the frontend and the service console is available in admin.', 'G3'))
                ->input('title', __('Title'), __('Chat window title.', 'G3'))
                ->textarea('announcement', __('Announcement', 'G3'), __('The announcement prompt that is fixedly displayed in front of the chat window. HTML is allowed.', 'G3'))
                ->input('announcementLink', __('Announcement', 'G3') . ' ' . __('Link'), __('Optional URL opened when the announcement is clicked.', 'G3'))
                ->textarea('welcomeTip', __('Tip', 'G3'), __('Frontend tip shown before conversation messages. HTML is allowed.', 'G3'))
                ->rowClass('advanced')
                ->textarea('welcomeMessage', __('Welcome', 'G3') . ' ' . __('Message', 'G3'), __('Welcome message shown when a visitor opens the chat. HTML is allowed.', 'G3'))
                ->rowClass('advanced')
                ->textarea('offlineMessage', __('Offline', 'G3') . ' ' . __('Message', 'G3'), __('Automated message created outside working hours. HTML is allowed.', 'G3'))
                ->rowClass('advanced')
                ->textarea('fallbackMessage', 'Fallback ' . __('Message', 'G3'), __('Fallback message returned when customer service automation or business handling fails. HTML is allowed.', 'G3'))
                ->rowClass('advanced')
                ->checkbox('workDays', __('Working Days', 'G3'), [
                    '1' => __('Monday'),
                    '2' => __('Tuesday'),
                    '3' => __('Wednesday'),
                    '4' => __('Thursday'),
                    '5' => __('Friday'),
                    '6' => __('Saturday'),
                    '7' => __('Sunday'),
                ], __('Days when agents are expected to be online.', 'G3'))
                ->rowClass('advanced')
                ->time('workStart', __('Work Start', 'G3'), 'HH:MM.')
                ->rowClass('advanced')
                ->time('workEnd', __('Work End', 'G3'), 'HH:MM.')
                ->rowClass('advanced')
                ->input('guestName', __('Guest Name', 'G3'), __('Default display name for anonymous visitors.', 'G3'))
                ->number('retentionDays', __('Retention Days', 'G3'), __('<code>CustomerMessageJob</code> removes data older than this many days. Default: 180.', 'G3'))
                ->rowClass('advanced')
                ->number('heartbeatSeconds', __('Heartbeat', 'G3'), __('Heartbeat interval in seconds. It is only used to keep the SSE connection alive. Default: 45, range: 30-60.', 'G3'))
                ->number('timeoutMinutes', __('Timeout', 'G3'), __('Minutes without messages before the system marks a conversation as timeout. Default: 120.', 'G3'))
                ->rowClass('advanced')
        ];
    }
    protected function adminPanelPage(): string
    {
        return 'customer-service-setting';
    }
    public function render(): void
    {
        $this->createPanel('settings');
    }
    public function renderFrontRoot(): void
    {
        if (!$this->enabled()) {
            return;
        }

        echo '<div id="g3-customer-service-root"></div>' . $this->configScript('g3-customer-service-config', $this->frontConfig());
    }
    public function renderAdminConfigScript(): void
    {
        echo $this->configScript('g3-customer-admin-config', $this->adminConfig());
    }
    private function enabled(): bool
    {
        return ($this->option()['enable'] ?? '0') === '1';
    }
    private function frontConfig(): array
    {
        $option = $this->option();
        $z      = $this->z();

        return [
            'restUrl'          => esc_url_raw(rest_url('api/customer/v1')),
            'notifyRestUrl'    => esc_url_raw(rest_url('api/notify/v1')),
            'nonce'            => wp_create_nonce('wp_rest'),
            'audioUrl'         => esc_url_raw(G3_AUDIO_URL . '/new.mp3'),
            'title'            => (string) ($option['title'] ?? CustomerService::defaultOption()['title']),
            'welcomeTip'       => $z ? (string) ($option['welcomeTip'] ?? CustomerService::defaultOption()['welcomeTip']) : '',
            'welcomeMessage'   => $z ? (string) ($option['welcomeMessage'] ?? CustomerService::defaultOption()['welcomeMessage']) : '',
            'announcement'     => (string) ($option['announcement'] ?? ''),
            'announcementLink' => (string) ($option['announcementLink'] ?? ''),
            'z'                => $z,
            'labels'           => [
                'open'        => __('Customer Service', 'G3'),
                'close'       => __('Close'),
                'send'        => __('Send', 'G3'),
                'placeholder' => __('Type a message...', 'G3'),
            ],
        ];
    }
    private function z(): bool
    {
        return $this->loader->admin();
    }
    private function option(): array
    {
        $defaults = CustomerService::defaultOption();
        $option   = get_option(CustomerService::OPTION_KEY, $defaults);
        return is_array($option) ? $option : $defaults;
    }
    private function adminConfig(): array
    {
        $service = $this->getService(CustomerService::class);

        return [
            'restUrl'       => esc_url_raw(rest_url('api/customer/v1')),
            'notifyRestUrl' => esc_url_raw(rest_url('api/notify/v1')),
            'nonce'         => wp_create_nonce('wp_rest'),
            'audioUrl'      => esc_url_raw(G3_AUDIO_URL . '/new.mp3'),
            'enabled'       => $this->enabled(),
            'eventId'       => $service instanceof CustomerService ? $service->latestEventId() : 0,
            'labels'        => [
                'empty'       => __('No conversations', 'G3'),
                'placeholder' => __('Leave a Reply'),
                'send'        => __('Reply'),
                'close'       => __('Close'),
                'handled'     => 'Handled',
                'onHold'      => __('On Hold', 'G3'),
                'all'         => __('All'),
                'pending'     => __('Pending', 'G3'),
                'botHandled'  => 'Bot Handled',
                'closed'      => __('Closed', 'G3'),
                'timeout'     => __('Timeout', 'G3'),
                'profile'     => __('User Profile', 'G3'),
                'search'      => __('Search'),
                'edit'        => __('Edit'),
                'editTitle'   => __('Edit Conversation Title', 'G3'),
            ],
        ];
    }
    private function configScript(string $id, array $config): string
    {
        $json = str_replace('</script', '<\/script', wp_json_encode($config, JSON_UNESCAPED_UNICODE) ?: '{}');
        return '<script type="application/json" id="' . esc_attr($id) . '">' . $json . '</script>';
    }
}
