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
                ->switch('enable', __('Enable'), sprintf(__('Enable the <a href="%s">Form</a> feature, user can contact us on the form page.', 'G3') . __('Please <a href="%s">flush rewrite rules</a> after setting.', 'G3'), '?page=form-list', '?page=developer-mode&tab=flush'))
                ->number('perPage', __('Items Per Page', 'G3'), __('The number of items displayed per page in the table list of admin panel.', 'G3'))
                ->switch('email', __('Email Notification', 'G3'), __('Automatically send email to the system email when the form is submitted.', 'G3'))
                ->rowClass('advanced')
                ->html('address', __('Page'), '<a href="' . $url . '" target="_blank">' . $url . '</a>')
                ->html('custom', __('Custom Fields'), sprintf(__('Default fields: name, email, content.<p>Custom fields: You can customize fields by setting <code>ext</code> property, for example: %s while submitting the request. See more details in the API documentation.</p>', 'G3'), '<code>ext: { phone: "1234567890" }</code>'))
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
            $result = $this->service->updateStatus((int) $status, (int) $id);
            if (!$result) {
                Response::ajaxFailed();
            } else {
                Response::ajaxUpdated();
            }
        });
    }
}
