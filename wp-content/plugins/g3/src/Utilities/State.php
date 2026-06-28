<?php
namespace JEALER\G3\Utilities;

use JEALER\G3\Core\State\StateBag;
use JEALER\G3\Core\State\StateManager;

final class State {

    public static function bag(string $name): StateBag
    {
        return StateManager::run()->bag($name);
    }

    public static function get(string $path, mixed $default = null): mixed
    {
        try {
            return StateManager::run()->get($path, $default);
        }
        catch (\Throwable) {
            return $default;
        }
    }
}
