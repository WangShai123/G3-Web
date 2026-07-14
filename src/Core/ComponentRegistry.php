<?php
namespace JEALER\G3\Core;

use JEALER\G3\Components\Components;
use ReflectionClass;

/**
 * Component runtime registry.
 *
 * ComponentLoader owns component creation; this registry owns component lookup
 * state. Components::$components is kept as a compatibility mirror only.
 */
class ComponentRegistry {

    private static ?self $instance = null;

    /** @var array<string, Components> */
    private array $components = [];

    /** @var array<string, string> */
    private array $names = [];

    /** @var array<int, array{code: string, message: string}> */
    private array $errors = [];

    private function __construct()
    {
    }

    public static function run(): self
    {
        if (!isset(self::$instance)) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    public function register(string $name, Components $component): void
    {
        $key = $this->normalizeName($name);

        $this->components[$key] = $component;
        $this->names[$key]      = $this->displayName($name, $component);

        $this->syncLegacyCache($name, $component);
    }

    public function has(string $name): bool
    {
        return isset($this->components[$this->normalizeName($name)]);
    }

    public function get(string $name): ?Components
    {
        return $this->components[$this->normalizeName($name)] ?? null;
    }

    /**
     * @return array<string, Components>
     */
    public function all(): array
    {
        $components = [];
        foreach ($this->components as $key => $component) {
            $components[$this->names[$key] ?? $key] = $component;
        }

        return $components;
    }

    /**
     * @return array<string, Components>
     */
    public function allNormalized(): array
    {
        return $this->components;
    }

    public function normalizeName(string $name): string
    {
        $name = trim($name);
        if (str_contains($name, '\\')) {
            $name = substr($name, strrpos($name, '\\') + 1);
        }

        return strtolower($name);
    }

    public function addError(string $code, string $message): void
    {
        $this->errors[] = [
            'code'    => $code,
            'message' => $message,
        ];
    }

    /**
     * @return array<int, array{code: string, message: string}>
     */
    public function errors(): array
    {
        return $this->errors;
    }

    private function displayName(string $name, Components $component): string
    {
        $name = trim($name);
        if ($name !== '' && !str_contains($name, '\\')) {
            return $name;
        }

        return (new ReflectionClass($component))->getShortName();
    }

    private function syncLegacyCache(string $name, Components $component): void
    {
        $shortName = (new ReflectionClass($component))->getShortName();
        $key       = $this->normalizeName($name);

        Components::$components[$key]       = $component;
        Components::$components[$name]      = $component;
        Components::$components[$shortName] = $component;
    }
}
