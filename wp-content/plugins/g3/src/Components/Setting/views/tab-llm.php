<?php
use JEALER\G3\Utilities\Element;

echo Element::tip(
    // AI 可以访问接口来获取站点内容列表，供 AI 模型训练使用。开启后请确保接口安全，并定期检查访问日志。
    __('AI can access the endpoint to get a list of site content for AI model training. Please ensure the security of the endpoint and regularly check access logs after enabling it.', 'G3'),
    '',
    'default',
    'mt-4'
);
echo '<form action="" method="POST">';
settings_fields('llm');
do_settings_sections('g3-settings&tab=llm');
submit_button();
?>
</form>