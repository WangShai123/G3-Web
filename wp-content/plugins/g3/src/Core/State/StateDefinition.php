<?php
namespace JEALER\G3\Core\State;

use InvalidArgumentException;

final class StateDefinition {

    private static int $memoryIndex = 0;

    private string $type;

    private string $source;

    private array|\Closure $defaults = [];

    private bool $autoload = true;

    private $sanitize = null;

    private function __construct(string $type, string $source)
    {
        if ($source === '') {
            throw new InvalidArgumentException('[G3 State] State source cannot be empty.');
        }

        $this->type   = $type;
        $this->source = $source;
    }

    public static function option(string $optionName, array $defaults = []): self
    {
        return (new self('option', $optionName))->defaults($defaults);
    }

    public static function memory(array $defaults = []): self
    {
        self::$memoryIndex++;
        return (new self('memory', 'memory_' . self::$memoryIndex))->defaults($defaults);
    }

    public function defaults(array|\Closure $defaults): self
    {
        $this->defaults = $defaults;
        return $this;
    }

    public function autoload(bool $autoload): self
    {
        $this->autoload = $autoload;
        return $this;
    }

    public function sanitize(callable $sanitize): self
    {
        $this->sanitize = $sanitize;
        return $this;
    }

    public function type(): string
    {
        return $this->type;
    }

    public function source(): string
    {
        return $this->source;
    }

    public function defaultsData(): array
    {
        $defaults = $this->defaults instanceof \Closure ? ($this->defaults)() : $this->defaults;
        return array_map(
            fn(mixed $v): mixed => $v instanceof \Closure ? $v() : $v,
            is_array($defaults) ? $defaults : []
        );
    }

    public function autoloadEnabled(): bool
    {
        return $this->autoload;
    }

    public function sanitizeValue(array $value): array
    {
        if ($this->sanitize === null) {
            return $value;
        }

        $result = ($this->sanitize)($value);
        return is_array($result) ? $result : $value;
    }
}
