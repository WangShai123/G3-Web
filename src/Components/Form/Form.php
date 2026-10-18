<?php
namespace JEALER\G3\Components;
use JEALER\G3\Core\Components\Components;
use JEALER\G3\Core\Admin\Panel;
use JEALER\G3\Services\FormService;
use JEALER\G3\Utilities\Response;
use Override;

class Form extends Components {

    public array        $option      = [];
    public string       $postType    = 'form';
    private FormService $formService;

    protected function start(): void
    {
        $this->formService = $this->service(FormService::class);
    }

    protected function defaultOption(): array
    {
        return [
            FormService::FORM_OPTION_KEY => FormService::optionDefaults()
        ];
    }

    protected function adminMenu(): void
    {
        $count     = $this->formService->getPendingCount();
        $menuTitle = __('Form', 'G3');
        if ($count > 0) {
            $menuTitle .= sprintf(' <span class="g3-badge">%d</span>', $count);
        }
        add_menu_page(
            $menuTitle,
            $menuTitle,
            'manage_options',
            'g3-form',
            function () {
                require_once __DIR__ . '/views/page-list.php';
            },
            'dashicons-email',
            25
        );


        if (current_user_can('manage_options')) {
            add_submenu_page(
                'g3-form',
                __('Settings'),
                __('Settings'),
                'manage_options',
                'form-settings',
                [$this, 'render']
            );
        }
    }

    protected function adminPanels(): array
    {
        $url = site_url('helper/form');
        return [
            $this->panel('form-settings', sprintf(__('%s %s', 'G3'), __('Form', 'G3'), __('Settings')))
                ->page('general', __('General'))
                ->option(FormService::FORM_OPTION_KEY, FormService::optionDefaults())
                ->switch('enable', __('Enable'), sprintf(__('Please <a href="%s">flush rewrite rules</a> after setting.', 'G3'), '?page=developer-mode&tab=flush'))
                ->number('perPage', __('Items Per Page', 'G3'), __('The number of items displayed per page in the table list of admin panel.', 'G3'))
                ->switch('email', __('Email Notification', 'G3'), __('Automatically send email to the system email when the form is submitted.', 'G3'))
                ->rowClass('advanced')
                ->html('address', 'URL', '<a href="' . $url . '" target="_blank">' . $url . '</a>')
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
            if (current_user_can('manage_options') && is_admin()) {
                Response::ajaxIllegal();
                return;
            }
            $id = $_POST['id'] ?? false;
            if (!$id) {
                Response::ajaxIllegal();
            }
            $result = $this->formService->delete($id);
            if (!$result || is_wp_error($result)) {
                Response::ajaxFailed();
            } else {
                Response::ajaxDeleted();
            }
        });
        add_action('wp_ajax_g3_change_field_status', function () {
            if (current_user_can('manage_options') && is_admin()) {
                Response::ajaxIllegal();
                return;
            }
            $id     = $_POST['id'] ?? false;
            $old    = $_POST['old'] ?? false;
            $status = $_POST['status'] ?? false;
            if (!$id || $status === false || $old === false) {
                Response::ajaxIllegal();
            }
            $result = $this->formService->updateStatus((int) $status, (int) $id);
            if (!$result) {
                Response::ajaxFailed();
            } else {
                $old    = (int) $old;
                $status = (int) $status;

                if ($old !== 0 && $status === 0) {
                    $this->formService->increasePendingCount();
                }
                if ($old === 0 && $status !== 0) {
                    $this->formService->decreasePendingCount();
                }

                Response::ajaxUpdated();
            }
        });
    }
}
