<?php
namespace JEALER\G3\Core\Admin;

use JEALER\G3\Utilities\Element;
use JEALER\G3\Utilities\Message;

final class PanelRenderer {

    /** @var array<string, bool> */
    private array $registered = [];

    public function register(string $component, Panel $panel): void
    {
        foreach ($this->pages($panel) as $pageId => $pageDefinition) {
            $registerKey = $component . ':' . $panel->slug() . ':' . $pageId;
            if (isset($this->registered[$registerKey])) {
                continue;
            }

            $this->registered[$registerKey] = true;

            $page  = $this->settingPage($panel, $pageId);
            $group = $this->group($panel, $pageId);

            add_settings_section($pageDefinition->id(), '', '__return_false', $page);
            $this->registerOption($group, $pageDefinition);

            Element::settingFields($page, $pageDefinition->id(), array_map(
                fn(Field $field): array => $this->fieldArgs($pageDefinition, $field),
                $pageDefinition->fields()
            ));
        }
    }

    public function render(object $component, Panel $panel, string $defaultTab): void
    {
        if (!$panel->hasTabs()) {
            $this->renderPage($component, $panel, $defaultTab);
            return;
        }

        $current = $this->currentTab($panel, $defaultTab);
        $tabs    = $panel->tabTitles();

        echo '<div class="wrap"><h1>' . esc_html($panel->title()) . '</h1>';
        echo '<h2 class="nav-tab-wrapper">';
        foreach ($tabs as $tab => $title) {
            echo sprintf(
                '<a href="%s" class="nav-tab %s">%s</a>',
                esc_url(add_query_arg(['tab' => $tab, 'sub-tab' => false])),
                $tab === $current ? 'nav-tab-active' : '',
                esc_html($title)
            );
        }
        echo '</h2>';

        $componentName = (new \ReflectionClass($component))->getShortName();
        $templatePath  = G3_COMPONENT_DIR . "/{$componentName}/views/tab-{$current}.php";
        if (file_exists($templatePath)) {
            $renderer = $this;
            $panelTab = $current;
            require $templatePath;
        } else {
            echo '<div class="wrap">' . sprintf(__('Template file does not exist: %s', 'G3'), esc_html($templatePath)) . '</div>';
        }
        echo '</div>';
    }

    public function form(Panel $panel, string $tab): void
    {
        $group = $this->group($panel, $tab);
        $page  = $this->settingPage($panel, $tab);

        echo '<form action="" method="POST">';
        settings_fields($group);
        do_settings_sections($page);
        submit_button();
        echo '</form>';
    }

    public function saveSubmitted(Panel $panel): void
    {
        foreach ($this->pages($panel) as $pageId => $section) {
            if (!$section->hasOption() || !array_key_exists($section->optionName(), $_POST)) {
                continue;
            }

            if (!current_user_can($panel->capabilityValue())) {
                continue;
            }

            $group = $this->group($panel, $pageId);
            $nonce = $_POST['_wpnonce'] ?? '';
            if (is_array($nonce) || !wp_verify_nonce((string) $nonce, "{$group}-options")) {
                add_settings_error('notice', 'error', __('Invalid settings request.', 'G3'), 'error');
                settings_errors('notice');
                continue;
            }

            $value = wp_unslash($_POST[$section->optionName()]);
            $value = is_array($value) ? $value : [];
            $value = $this->normalizeCheckboxes($section, $value);

            $saved = update_option($section->optionName(), $value, $section->optionAutoload());
            if (!$saved && !$this->optionExistsInDatabase($section->optionName())) {
                wp_cache_delete($section->optionName(), 'options');
                wp_cache_delete('alloptions', 'options');
                wp_cache_delete('notoptions', 'options');
                $saved = add_option($section->optionName(), $value, '', $section->optionAutoload());
            }

            add_settings_error(
                'notice',
                $saved ? 'updated' : 'notice-info',
                $saved ? Message::updated() : __('No data changed', 'G3'),
                $saved ? 'updated' : 'info'
            );
            settings_errors('notice');
        }
    }

    private function renderPage(object $component, Panel $panel, string $defaultPage): void
    {
        $current = $this->currentPage($panel, $defaultPage);

        echo '<div class="wrap"><h1>' . esc_html($panel->title()) . '</h1>';
        $componentName = (new \ReflectionClass($component))->getShortName();
        $templatePath  = G3_COMPONENT_DIR . "/{$componentName}/views/page-{$current}.php";
        if (file_exists($templatePath)) {
            $renderer  = $this;
            $panelPage = $current;
            $panelTab  = $current;
            require $templatePath;
        } else {
            echo '<div class="wrap">' . sprintf(__('Template file does not exist: %s', 'G3'), esc_html($templatePath)) . '</div>';
        }
        echo '</div>';
    }

    /**
     * @return array<string, Section>
     */
    private function pages(Panel $panel): array
    {
        return $panel->hasTabs() ? $panel->tabs() : $panel->pages();
    }

    private function currentPage(Panel $panel, string $defaultPage): string
    {
        $pages     = $panel->pages();
        $requested = $_REQUEST['page-view'] ?? $_REQUEST['sub-page'] ?? $defaultPage;
        if (is_array($requested)) {
            $requested = $defaultPage;
        }

        $requested = function_exists('wp_unslash') ? wp_unslash($requested) : $requested;
        $requested = $this->normalizeKey((string) $requested);
        $fallback  = array_key_exists($defaultPage, $pages) ? $defaultPage : (array_key_first($pages) ?: $defaultPage);

        return array_key_exists($requested, $pages) ? $requested : $fallback;
    }

    private function currentTab(Panel $panel, string $defaultTab): string
    {
        $tabs      = $panel->tabTitles();
        $requested = $_REQUEST['sub-tab'] ?? $_REQUEST['tab'] ?? $defaultTab;
        if (is_array($requested)) {
            $requested = $defaultTab;
        }

        $requested = function_exists('wp_unslash') ? wp_unslash($requested) : $requested;
        $requested = $this->normalizeKey((string) $requested);
        $fallback  = array_key_exists($defaultTab, $tabs) ? $defaultTab : (array_key_first($tabs) ?: $defaultTab);

        return array_key_exists($requested, $tabs) ? $requested : $fallback;
    }

    private function normalizeKey(string $key): string
    {
        if (function_exists('sanitize_key')) {
            return sanitize_key($key);
        }

        return strtolower(preg_replace('/[^a-z0-9_\-]/i', '', $key) ?? '');
    }

    private function fieldArgs(Section $section, Field $field): array
    {
        $data = $field->toArray();
        $id   = $data['id'];

        $args = [];

        if (!in_array($data['type'], ['switch', 'checkbox', 'radio', 'html', 'callback'], true)) {
            $args['label_for'] = $id;
        }

        if ($data['rowClass'] !== '') {
            $args['class'] = $data['rowClass'];
        }

        return [
            'id'       => $id,
            'title'    => $data['title'],
            'callback' => fn() => print $this->renderField(
                $section->optionName(),
                $this->optionValue($section),
                $data
            ),
            'args'     => $args,
        ];
    }

    private function registerOption(string $group, Section $section): void
    {
        if (!$section->hasOption()) {
            return;
        }

        add_option(
            $section->optionName(),
            $section->optionDefaults(),
            '',
            $section->optionAutoload()
        );

        register_setting($group, $section->optionName(), [
            'type'    => 'array',
            'default' => $section->optionDefaults(),
        ]);
    }

    private function optionValue(Section $section): array
    {
        if (!$section->hasOption()) {
            return [];
        }

        $value = get_option($section->optionName(), $section->optionDefaults());
        return is_array($value) ? $value : $section->optionDefaults();
    }

    private function normalizeCheckboxes(Section $section, array $value): array
    {
        foreach ($section->fields() as $field) {
            $data = $field->toArray();
            if ($data['type'] === 'checkbox' && !isset($value[$data['id']])) {
                $value[$data['id']] = [];
            }
        }

        return $value;
    }

    private function optionExistsInDatabase(string $optionName): bool
    {
        global $wpdb;

        return $wpdb->get_var(
            $wpdb->prepare(
                "SELECT option_name FROM {$wpdb->options} WHERE option_name = %s LIMIT 1",
                $optionName
            )
        ) !== null;
    }

    private function renderField(string $optionName, array $value, array $field): string
    {
        if (is_callable($field['callback'])) {
            return (string) ($field['callback'])($optionName, $value, $field);
        }

        $id          = $field['id'];
        $legend      = $field['legend'] ?: $field['title'];
        $description = $field['description'];

        return match ($field['type']) {
            'switch'       => Element::switch($optionName, $value, $id, $legend, $description, $field['class'], $field['size']),
            'checkbox'     => Element::checkbox($optionName, $value, $id, $legend, $description, $field['class'], $field['options'], $field['horizontal']),
            'radio'        => Element::radio($optionName, $value, $id, $legend, $description, $field['class'], $field['options'], $field['horizontal']),
            'select'       => Element::select($optionName, $value, $id, $legend, $description, $field['class'], $field['options']),
            'file'         => Element::uploadInput($optionName, $value, $id, $legend, $description, $field['class']),
            'image'        => Element::imageInput($optionName, $value, $id, $legend, $description, $field['imageSize'], $field['class']),
            'textarea'     => Element::textarea($optionName, $value, $id, $legend, $description, $field['class'] ?: 'large-text code'),
            'readonly-url' => $this->readonlyUrl($optionName, $value, $id, $field['value']),
            'html'         => $field['html'],
            default        => Element::input($optionName, $value, $id, $legend, $description, $field['inputType'], $field['class']),
        };
    }

    private function readonlyUrl(string $optionName, array $value, string $id, string $fallback): string
    {
        $url = $value[$id] ?? $fallback;
        return '<input name="' . esc_attr($optionName) . '[' . esc_attr($id) . ']" type="url" id="' . esc_attr($id) . '" value="' . esc_url($url) . '" class="regular-text" readonly="readonly">';
    }

    private function group(Panel $panel, string $tab): string
    {
        if (!$panel->hasTabs()) {
            return $panel->slug();
        }

        return $panel->slug() . '-' . $tab;
    }

    private function settingPage(Panel $panel, string $tab): string
    {
        if (!$panel->hasTabs()) {
            return $panel->slug();
        }

        return $tab === 'general' ? $panel->slug() : $panel->slug() . '&tab=' . $tab;
    }
}
