<?php
use JEALER\G3\Includes\RewriteRulesTable;

$rewrite_rules_table = new RewriteRulesTable();
$rewrite_rules_table->prepare_items();
$rewrite_rules_table->display();
?>

<style>
    .test-rewrite-rules-table {
        margin-top: 16px;
    }
</style>