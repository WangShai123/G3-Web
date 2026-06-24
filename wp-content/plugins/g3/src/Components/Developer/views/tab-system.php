<?php
use JEALER\G3\Utilities\System;

global $wpdb;
?>
<div class="mt-4">
    <table class="wp-list-table widefat">
        <!-- <thead>
            <th class="text-left w-2/5">Title</th>
            <th class="text-left">Description</th>
        </thead> -->
        <tr>
            <td colspan="2" class="sys-subtitle">
                <?php _e('System', 'G3'); ?>
            </td>
        </tr>
        <tbody>
            <?php
            /**
             *  - G3
             *  - WordPress
             *  - Server
             *  - MySQL
             */
            $data1 = [
                'G3 Web'    => G3_VERSION,
                'WordPress' => get_bloginfo('version'),
                'Server'    => $_SERVER['SERVER_SOFTWARE'],
                'MySQL'     => $wpdb->db_version(),
            ];
            foreach ($data1 as $key => $value) {
                echo '<tr><td>' . $key . '</td><td>' . $value . '</td></tr>';
            }
            ?>

            <tr>
                <td colspan="2" class="sys-subtitle">PHP</td>
            </tr>
            <?php
            /**
             *  - PHP version
             *  - PHP memory_limit
             *  - PHP post_max_size
             *  - PHP max_execution_time
             *  - PHP JIT status
             */
            $data2 = [
                'PHP version'            => phpversion(),
                'PHP memory_limit'       => ini_get('memory_limit'),
                'PHP post_max_size'      => ini_get('post_max_size'),
                'PHP max_execution_time' => ini_get('max_execution_time'),
                'PHP JIT status'         => ini_get('opcache.jit'),
            ];
            foreach ($data2 as $key => $value) {
                echo '<tr><td>' . $key . '</td><td>' . $value . '</td></tr>';
            }
            ?>

            <tr>
                <td colspan="2" class="sys-subtitle">
                    <?php _e('Cache', 'G3'); ?>
                </td>
            </tr>
            <?php
            /**
             *  - Zend OPcache status
             *  - Memcached status
             *  - Redis status
             */
            $data3 = [
                'Zend OPcache' => ini_get('opcache.enable') ? 'enabled' : 'disabled',
                'Redis'        => class_exists('Redis') ? 'enabled' : 'disabled',
                'Memcached'    => class_exists('Memcached') ? 'enabled' : 'disabled',
            ];
            foreach ($data3 as $key => $value) {
                echo '<tr><td>' . $key . '</td><td>' . $value . '</td></tr>';
            }
            ?>

            <tr>
                <td colspan="2" class="sys-subtitle">WordPress Debug</td>
            </tr>
            <?php
            /**
             *  - WP_DEBUG
             *  - WP_DEBUG_LOG
             *  - WP_DEBUG_DISPLAY
             *  - SCRIPT_DEBUG
             *  - WP_ENVIRONMENT_TYPE
             *  - ERROR_LOG PATH
             */
            $data4 = [
                'WP_DEBUG'            => defined('WP_DEBUG') ? (WP_DEBUG ? 'enabled' : 'disabled') : 'Not set',
                'WP_DEBUG_LOG'        => defined('WP_DEBUG_LOG') ? (WP_DEBUG_LOG ? 'enabled' : 'disabled') : 'Not set',
                'WP_DEBUG_DISPLAY'    => defined('WP_DEBUG_DISPLAY') ? (WP_DEBUG_DISPLAY ? 'enabled' : 'disabled') : 'Not set',
                'SCRIPT_DEBUG'        => defined('SCRIPT_DEBUG') ? (SCRIPT_DEBUG ? 'enabled' : 'disabled') : 'Not set',
                'WP_ENVIRONMENT_TYPE' => defined('WP_ENVIRONMENT_TYPE') ? WP_ENVIRONMENT_TYPE : 'Not set',
                'ERROR_LOG PATH'      => System::errorLogPath(),
            ];
            foreach ($data4 as $key => $value) {
                echo '<tr><td>' . $key . '</td><td>' . $value . '</td></tr>';
            }
            ?>
        </tbody>
    </table>
</div>
<style>
    .sys-subtitle {
        background-color: #f5f5f5;
        font-weight: 600;
    }
</style>