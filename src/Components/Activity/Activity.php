<?php
namespace JEALER\G3\Components;
use JEALER\G3\Components\Components;
use Override;

class Activity extends Components {
    private string $postType = 'activity';
    protected function postType()
    {
        $labels = [
            'name'               => __('Activities', 'G3'),
            'singular_name'      => __('Activity', 'G3'),
            'menu_name'          => __('Activities', 'G3'),
            'name_admin_bar'     => __('Activity', 'G3'),
            'add_new'            => __('Add New', 'G3'),
            'add_new_item'       => __('Add New Activity', 'G3'),
            'new_item'           => __('Add New', 'G3'),
            'edit_item'          => __('Edit'),
            'view_item'          => __('View'),
            'all_items'          => __('All'),
            'search_items'       => __('Search'),
            'parent_item_colon'  => __('Parent'),
            'not_found'          => __('No items found.'),
            'not_found_in_trash' => __('No items found.'),
            // 'featured_image'        => __('Cover Image', 'G3'),
            // 'set_featured_image'    => __('Set cover image', 'G3'),
            // 'remove_featured_image' => __('Remove cover image', 'G3'),
            // 'use_featured_image'    => __('Use as cover image', 'G3'),
            'archives'           => __('Archives'),
            'insert_into_item'   => __('Add'),
            // 'uploaded_to_this_item' => __('UPloaded to this activity', 'G3'),
            // 'filter_items_list'     => __('Filter activities list', 'G3'),
            // 'items_list_navigation' => __('Activities list navigation', 'G3'),
            // 'items_list'            => __('Activities list', 'G3'),
        ];
        register_post_type(
            $this->postType,
            [
                'labels'             => $labels,
                'public'             => true,
                'publicly_queryable' => true,
                'show_ui'            => true,
                'show_in_menu'       => true,
                'show_in_admin_bar'  => true,
                'show_in_nav_menus'  => true,
                'query_var'          => true,
                'rewrite'            => [
                    'slug'       => $this->postType,
                    'with_front' => false // 避免继承全局固定链接前缀
                ],
                'capability_type'    => 'post',
                'has_archive'        => true,
                'hierarchical'       => false,
                'menu_position'      => null,
                'show_in_rest'       => true,
                'supports'           => ['title', 'editor', 'comments', 'revisions', 'author', 'excerpt', 'thumbnail', 'post-formats'],
                'menu_icon'          => 'dashicons-universal-access-alt',
                'taxonomies'         => ['activity_category', 'post_tag'],
            ]
        );
    }
    public function taxonomy()
    {
        register_taxonomy(
            'activity_category',
            $this->postType,
            [
                'hierarchical'      => true,
                'show_ui'           => true,
                'show_admin_column' => true,
                'query_var'         => true,
                'has_archive'       => true,
                'labels'            => [
                    'name'          => __('Categories'),
                    'singular_name' => __('Category'),
                    'menu_name'     => __('Categories'),
                    // 'search_items'      => __('Search Categories', 'G3'),
                    // 'not_found'         => __('No categories found.', 'G3'),
                    // 'all_items'         => __('All Categories', 'G3'),
                    // 'parent_item'       => __('Parent Category', 'G3'),
                    // 'parent_item_colon' => __('Parent Category:', 'G3'),
                    // 'edit_item'         => __('Edit Category', 'G3'),
                    // 'update_item'       => __('Update Category', 'G3'),
                    // 'add_new_item'      => __('Add New Category', 'G3'),
                    // 'new_item_name'     => __('New Category Name', 'G3'),
                ]
            ]
        );
    }
}
