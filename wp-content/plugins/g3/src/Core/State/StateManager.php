<?php
namespace JEALER\G3\Core\State;

use RuntimeException;

final class StateManager {

    private static ?self $instance = null;

    /** @var array<string, StateDefinition> */
    private array $definitions = [];

    /** @var array<string, StateBag> */
    private array $bags = [];

    /** @var array<string, string> */
    private array $aliases = [];

    public static function run(): self
    {
        if (!isset(self::$instance)) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    public function register(string $component, array $definitions): void
    {
        $componentKey = $this->normalize($component);

        foreach ($definitions as $name => $definition) {
            if (!$definition instanceof StateDefinition || !is_string($name) || $name === '') {
                continue;
            }

            $nameKey = $this->normalize($name);
            $key     = $componentKey . '.' . $nameKey;

            $this->definitions[$key] = $definition;
            $this->aliases[$key]     = $key;
            $this->aliases[$component . '.' . $name] = $key;

            if (!isset($this->aliases[$nameKey])) {
                $this->aliases[$nameKey] = $key;
            }
        }
    }

    public function has(string $name): bool
    {
        return $this->resolve($name) !== null;
    }

    public function bag(string $name): StateBag
    {
        $key = $this->resolve($name);
        if ($key === null) {
            throw new RuntimeException("[G3 State] State [$name] is not registered.");
        }

        if (!isset($this->bags[$key])) {
            [$component, $state] = explode('.', $key, 2);
            $this->bags[$key] = new StateBag($component, $state, $this->definitions[$key]);
        }

        return $this->bags[$key];
    }

    public function get(string $path, mixed $default = null): mixed
    {
        [$state, $field] = $this->splitPath($path);
        $bag = $this->bag($state);

        return $field === null ? $bag->all() : $bag->get($field, $default);
    }

    public function saveSubmitted(string $component, array $groups): void
    {
        foreach ($groups as $stateName => $group) {
            $bag = $this->bag($component . '.' . $stateName);
            if (!$bag->submitted()) {
                continue;
            }

            if (!current_user_can('manage_options')) {
                continue;
            }

            $nonce = $_POST['_wpnonce'] ?? '';
            if (!wp_verify_nonce($nonce, "{$group}-options")) {
                add_settings_error('notice', 'error', __('Invalid settings request.', 'G3'), 'error');
                settings_errors('notice');
                continue;
            }

            $bag->saveSubmitted();
        }
    }

    private function splitPath(string $path): array
    {
        $path = trim($path);
        if ($this->resolve($path) !== null) {
            return [$path, null];
        }

        $parts = explode('.', $path);
        if (count($parts) >= 3) {
            return [$parts[0] . '.' . $parts[1], implode('.', array_slice($parts, 2))];
        }

        if (count($parts) === 2) {
            return [$parts[0], $parts[1]];
        }

        return [$path, null];
    }

    private function resolve(string $name): ?string
    {
        $name = trim($name);
        if ($name === '') {
            return null;
        }

        $normalized = implode('.', array_map(fn(string $part): string => $this->normalize($part), explode('.', $name)));
        return $this->aliases[$name] ?? $this->aliases[$normalized] ?? (isset($this->definitions[$normalized]) ? $normalized : null);
    }

    private function normalize(string $name): string
    {
        return strtolower(trim($name));
    }
}
