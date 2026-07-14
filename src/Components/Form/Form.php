<?php
namespace JEALER\G3\Components;
use JEALER\G3\Components\Components;
use JEALER\G3\Core\Admin\Panel;
use JEALER\G3\Services\FormService;
use JEALER\G3\Utilities\Response;
use Override;

class Form extends Components {
    public array        $option   = [];
    public string       $postType = 'form';
    private FormService $service;
    protected function start(): void
    {
        $this->service = $this->container->get(FormService::class);
    }
    private function default(): array
    {
        return [
            'enable'  => '0',
            'perPage' => '20',
            'email'   => '1',
        ];
    }
    protected function defaultOption(): array
    {
        return [
            FormService::FORM_OPTION_KEY => $this->default()
        ];
    }
    protected function adminMenu(): void
    {
        add_submenu_page(
            'g3-settings',
            __('Form', 'G3'),
            __('Form', 'G3'),
            'manage_options',
            'form-settings',
            [$this, 'render']
        );

        if (($this->option()['enable'] ?? '0') === '1') {
            add_menu_page(
                __('Form', 'G3'),
                __('Form', 'G3'),
                'manage_options',
                'form-list',
                function () {
                    require_once __DIR__ . '/views/page-list.php';
                },
                'dashicons-email',
                25
            );
        }
    }
    protected function adminPanels(): array
    {
        $url = site_url('helper/form');
        return [
            $this->panel('form-settings', __('Form', 'G3'))
                ->page('general', __('General'))
                ->option(FormService::FORM_OPTION_KEY, $this->default())
                ->switch('enable', __('Enable'), __('Enable the contact form on the front-end of your website.', 'G3') . __('Please <a href="?page=developer-mode&tab=flush">flush rewrite rules</a> after setting.', 'G3'))
                ->number('perPage', __('Items Per Page', 'G3'), __('The number of items displayed per page in the form list of admin panel.', 'G3'))
                ->switch('email', __('Email Notification', 'G3'), __('Automatically send email to your system email when the form is submitted.', 'G3'))
                ->rowClass('advanced')
                ->html('address', __('Page'), '<a href="' . $url . '" target="_blank">' . $url . '</a>')
                ->html('custom', __('Custom Fields'), __('Default fields: title, email, content.<p>Custom fields: You can customize fields by setting <code>ext</code> property, for example: <code>ext: { phone: "1234567890" }</code> while submitting the request. See more details in the API documentation.</p>', 'G3'))
                ->html('template', __('Template'), __('You can customize the contact form template by overriding the template file <code>/templates/form/index.php</code>.', 'G3'))
        ];
    }
    protected function adminPanelPage(): string
    {
        return 'form-settings';
    }
    public function render(): void
    {
        $this->createPanel();
    }
    public static function onForm(): bool
    {
        $option = get_option(FormService::FORM_OPTION_KEY, []);
        return is_array($option) && ($option['enable'] ?? '0') === '1';
    }

    public function ajax()
    {
        add_action('wp_ajax_g3_delete_field', function () {
            $id = $_POST['id'] ?? false;
            if (!$id) {
                Response::ajaxIllegal();
            }
            $result = $this->service->delete($id);
            if (!$result || is_wp_error($result)) {
                Response::ajaxFailed();
            } else {
                Response::ajaxDeleted();
            }
        });
        add_action('wp_ajax_g3_change_field_status', function () {
            $id     = $_POST['id'] ?? false;
            $status = $_POST['status'] ?? false;
            if (!$id || $status === false) {
                Response::ajaxIllegal();
            }
            $newStatus = $status === '0' ? 1 : 0;
            $result    = $this->service->updateStatus($newStatus, (int) $id);
            if (!$result) {
                Response::ajaxFailed();
            } else {
                Response::ajaxUpdated();
            }
        });
    }
}
