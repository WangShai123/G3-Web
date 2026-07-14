<?php
class ProductWidget extends WP_Widget {
    private int $count;
    private int $type;
    private int $category;
    public function __construct()
    {
        parent::__construct(
            'product_widget',
            __('Product List', 'G3'),
            [
                'classname'                   => 'widget_product',
                'description'                 => __('Retrieve the data for the product list.', 'G3'),
                'customize_selective_refresh' => true,
                'show_instance_in_rest'       => true,
            ]
        );
        $this->count    = 5;
        $this->category = 0;
        $this->type     = 0;
    }

    public function widget($args, $instance)
    {
    }

    public function form($instance)
    {
    }

    public function update($new_instance, $old_instance)
    {
    }

    private function getProducts($count, $category, $type)
    {
    }
}
