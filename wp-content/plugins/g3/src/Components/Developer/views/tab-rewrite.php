<?php

use JEALER\G3\Components\Developer\Includes\RewriteRulesTable;

$rewrite_rules_table = new RewriteRulesTable();
$rewrite_rules_table->prepare_items();
$rewrite_rules_table->display();
