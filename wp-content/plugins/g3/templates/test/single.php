<?php
get_header();

echo '<h1>Test Page: Single Query_Vars</h1>';
echo '<p>Query Var: ' . get_query_var('test_id') . '</p>';

get_footer();