<?php
namespace JEALER\G3\Core\Router;
use RuntimeException;

class RouteConflictException extends RuntimeException {
    public function __construct(private array $conflicts)
    {
        parent::__construct($this->buildMessage($conflicts));
    }

    public function getConflicts(): array
    {
        return $this->conflicts;
    }

    private function buildMessage(array $conflicts): string
    {
        $messages = [];
        foreach ($conflicts as $conflict) {
            $messages[] = sprintf(
                '%s %s %s: %s::%s conflicts with %s::%s',
                $conflict['namespace'],
                $conflict['method'] ?? '*',
                $conflict['route'],
                $conflict['current']['class'],
                $conflict['current']['method'],
                $conflict['previous']['class'],
                $conflict['previous']['method']
            );
        }
        return '[G3 Router] REST route conflicts detected: ' . implode('; ', $messages);
    }
}
