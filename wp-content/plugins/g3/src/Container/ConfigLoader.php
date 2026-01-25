<?php
namespace JEALER\G3\Container;

use InvalidArgumentException;
use JEALER\G3\Utilities\System;

class ConfigLoader {
    /**
     * Force load PHP array config file
     * 
     * @param string $configFile Config file path
     * @return array
     * @throws InvalidArgumentException if config file does not exist
     * @since 1.0.0
     * @author Wang Shai
     */
    public static function load(string $configFile): array
    {
        if (!file_exists($configFile)) {
            throw new InvalidArgumentException("Config file not found: $configFile");
        } else {
            $config = require_once $configFile;
        }

        return is_array($config) ? $config : [];
    }
}