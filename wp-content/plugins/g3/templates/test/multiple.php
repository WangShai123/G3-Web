<?php
get_header();

echo '<h1>Test Page: Multiple Query_Vars</h1>';
echo '<p>First Query Var: ' . get_query_var('multiple_id') . '</p>';
echo '<p>Second Query Var: ' . get_query_var('multiple_name') . '</p>';
get_footer();