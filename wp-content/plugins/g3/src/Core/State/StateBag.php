<?php
namespace JEALER\G3\Core\State;

use JEALER\G3\Utilities\Message;

final class StateBag {

    private ?array $value = null;

    public function __construct(
        private readonly string $component,
        private readonly string $name,
        private readonly StateDefinition $definition
    ) {
    }

    public function component(): string
    {
        return $this->component;
    }

    public function name(): string
    {
        return $this->name;
    }

    public function source(): string
    {
        return $this->definition->source();
    }

    public function all(): array
    {
        if ($this->value === null) {
            $this->value = $this->read();
        }

        return $this->value;
    }

    public function get(string $key, mixed $default = null): mixed
    {
        $data = $this->all();
        return $data[$key] ?? $default;
    }

    public function replace(array $value): array
    {
        $this->value = $this->normalize($value);
        return $this->value;
    }

    public function submitted(): bool
    {
        return $this->definition->type() === 'option' && array_key_exists($this->source(), $_POST);
    }

    public function saveSubmitted(bool $notice = true): bool
    {
        if (!$this->submitted()) {
            return false;
        }

        $value       = $this->normalize($this->submittedValue());
        $this->value = $value;
        $saved       = update_option($this->source(), $value, $this->definition->autoloadEnabled());

        if ($notice && function_exists('add_settings_error') && function_exists('settings_errors')) {
            if ($saved) {
                add_settings_error('notice', 'updated', Message::updated(), 'updated');
            } else {
                add_settings_error('notice', 'notice-info', __('No data changed', 'G3'), 'info');
            }
            settings_errors('notice');
        }

        return (bool) $saved;
    }

    public function registerSetting(string $group): void
    {
        if ($this->definition->type() !== 'option') {
            return;
        }

        register_setting($group, $this->source(), [
            'type'              => 'array',
            'sanitize_callback' => fn(mixed $value): array => $this->normalize(is_array($value) ? $value : []),
            'default'           => $this->definition->defaultsData(),
        ]);
    }

    private function read(): array
    {
        if ($this->submitted()) {
            return $this->normalize($this->submittedValue());
        }

        if ($this->definition->type() !== 'option') {
            return $this->definition->defaultsData();
        }

        $value = get_option($this->source(), null);
        if (is_array($value)) {
            return $this->normalize($value);
        }

        add_option($this->source(), $this->definition->defaultsData(), '', $this->definition->autoloadEnabled());
        return $this->definition->defaultsData();
    }

    private function submittedValue(): array
    {
        $value = wp_unslash($_POST[$this->source()]);
        return is_array($value) ? $value : [];
    }

    private function normalize(array $value): array
    {
        $value = array_replace($this->definition->defaultsData(), $value);
        return $this->definition->sanitizeValue($value);
    }
}
