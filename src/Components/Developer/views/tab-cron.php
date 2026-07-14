<?php
use JEALER\G3\Components\Developer\Includes\CronListTable;

$table = new CronListTable();
echo '<form method="post">';
$table->display();
wp_nonce_field('bulk-cron_jobs');
echo '</form>';
