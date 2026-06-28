<?php
use JEALER\G3\Core\State\StateBag;
use JEALER\G3\Utilities\State;

if (!function_exists('g3_state')) {
    function g3_state(string $path, mixed $default = null): mixed
    {
        return State::get($path, $default);
    }
}

if (!function_exists('g3_state_bag')) {
    function g3_state_bag(string $name): StateBag
    {
        return State::bag($name);
    }
}
