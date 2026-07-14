<?php
namespace JEALER\G3\Core;
use JEALER\G3\Core\Queue\Queue;
use JEALER\G3\Utilities\System;
use WP_Error;

class Deactivator {
    public static function deactivate(): void
    {
        flush_rewrite_rules();
        self::cleanOptions();

        // Queue::unregisterCron();
    }

    private static function cleanOptions()
    {
        $mapFile    = G3_PLUGIN_DIR . '/config/options.php';
        $backupFile = WP_CONTENT_DIR . '/G3-Web/cache/options.php';

        $optionsMap = @require $mapFile;

        if (!is_array($optionsMap)) {
            return new WP_Error('g3_options_map_error', 'G3 Error: cleanOptions() can not find valid options map.');
        }

        $optionsMap = self::normalizeOptionsMap($optionsMap);

        if (empty($optionsMap)) {
            return new WP_Error('g3_options_map_error', 'G3 Error: cleanOptions() can not find valid typed options map.');
        }

        $backupData = [];

        // delete options from DB
        foreach ($optionsMap as $optionKey => $type) {

            // get option value from DB
            $currentValue = get_option($optionKey, null);

            // save to backup array
            $backupData[$optionKey] = self::normalizeOptionValue($type, $currentValue);

            // delete option data from DB
            delete_option($optionKey);
            delete_site_option($optionKey);
        }

        // write backup data to backup/options.php
        System::writeArray($backupFile, $backupData);
    }

    private static function normalizeOptionsMap(array $optionsMap): array
    {
        $normalized = [];

        // Backward compatibility: support old flat format.
        if (array_is_list($optionsMap)) {
            foreach ($optionsMap as $optionKey) {
                if (is_string($optionKey) && $optionKey !== '') {
                    $normalized[$optionKey] = 'array';
                }
            }

            return $normalized;
        }

        foreach ($optionsMap as $type => $optionKeys) {
            if (!is_string($type) || !is_array($optionKeys)) {
                continue;
            }

            $type = strtolower($type);

            foreach ($optionKeys as $optionKey) {
                if (is_string($optionKey) && $optionKey !== '') {
                    $normalized[$optionKey] = $type;
                }
            }
        }

        return $normalized;
    }

    private static function normalizeOptionValue(string $type, $value)
    {
        switch ($type) {
            case 'array':
                if (is_array($value)) {
                    return $value;
                }

                if (is_string($value)) {
                    $decoded = maybe_unserialize($value);
                    if (is_array($decoded)) {
                        return $decoded;
                    }
                }

                return [];

            case 'string':
                if (is_string($value)) {
                    return $value;
                }

                return is_null($value) ? '' : (string) $value;

            default:
                return $value;
        }
    }
}
