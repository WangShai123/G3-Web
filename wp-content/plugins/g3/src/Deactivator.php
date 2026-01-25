<?php
namespace JEALER\G3;

use JEALER\G3\Queue;
use WP_Error;

class Deactivator {
    public static function deactivate(): void
    {
        flush_rewrite_rules();
        self::cleanOptions();

        Queue::unregisterCron();
    }

    private static function cleanOptions()
    {
        $mapFile    = G3_PlUGIN_DIR . '/config/options.php';
        $backupFile = G3_PlUGIN_DIR . '/backup/options.php';

        $optionsMap = @require_once $mapFile;

        if (!is_array($optionsMap)) {
            return new WP_Error('g3_options_map_error', 'G3 Error: cleanOptions() can not find valid options map.');
        }

        $backupData = [];

        // delete options from DB
        foreach ($optionsMap as $optionKey) {

            // get option value from DB
            $currentValue = get_option($optionKey, null);

            // save to backup array
            $backupData[$optionKey] = $currentValue;

            // delete option data from DB
            delete_option($optionKey);
            delete_site_option($optionKey);
        }

        // write backup data to backup/options.php
        self::writeBackup($backupFile, $backupData);
    }

    private static function writeBackup($backupFile, array $data)
    {
        // generate readable PHP array content
        $export = "<?php\nreturn " . var_export($data, true) . ";\n";

        // create directory if not exists
        $dir = dirname($backupFile);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        // write backup data to file
        file_put_contents($backupFile, $export);
    }
}