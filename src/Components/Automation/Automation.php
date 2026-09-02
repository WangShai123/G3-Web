<?php
namespace JEALER\G3\Components;
use JEALER\G3\Components\Components;
use JEALER\G3\Services\SystemService;

class Automation extends Components {
    protected function defaultOption(): array
    {
        return [
            SystemService::AUTOMATION_OPTION_KEY => SystemService::automationDefaultOption(),
        ];
    }

    protected function adminMenu()
    {
        add_submenu_page(
            'g3-settings',
            __('Automation', 'G3'),
            __('Automation', 'G3'),
            'manage_options',
            'automation-settings',
            [$this, 'render'],
            16
        );
    }

    public function render(): void
    {
        $this->createPanel();
    }

    protected function adminPanelPage(): string
    {
        return 'automation-settings';
    }

    protected function adminPanels(): array
    {
        return [
            $this->panel('automation-settings', __('Automation', 'G3'))
                ->tab('collection', __('Data Collection', 'G3'))
            // ->option(SystemService::AUTOMATION_OPTION_KEY, SystemService::automationDefaultOption())
        ];
    }
}
