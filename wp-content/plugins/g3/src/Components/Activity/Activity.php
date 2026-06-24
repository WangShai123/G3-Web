<?php
namespace JEALER\G3\Components;
use JEALER\G3\Components\Components;
use Override;

class Activity extends Components {
    private string $postType = 'activity';

    public function postType(): void
    {
        $labels = [
            'name'                  => __('Activities', 'G3'),
            'singular_name'         => __('Activity', 'G3'),
            'menu_name'             => __('Activities', 'G3'),
            'name_admin_bar'        => __('Activity', 'G3'),
            'add_new'               => __('Add New', 'G3'),
            'add_new_item'          => __('Add New Activity', 'G3'),
            'new_item'              => __('Add New', 'G3'),
            'edit_item'             => __('Edit Activity', 'G3'),
            'view_item'             => __('View Activity', 'G3'),
            'all_items'             => __('All Activities', 'G3'),
            'search_items'          => __('Search', 'G3'),
            'parent_item_colon'     => __('Parent Activity:', 'G3'),
            'not_found'             => __('No activities found.', 'G3'),
            'not_found_in_trash'    => __('No activities found in trash.', 'G3'),
            'featured_image'        => __('Cover Image', 'G3'),
            'set_featured_image'    => __('Set cover image', 'G3'),
            'remove_featured_image' => __('Remove cover image', 'G3'),
            'use_featured_image'    => __('Use as cover image', 'G3'),
            'archives'              => __('Activity Archives', 'G3'),
            'insert_into_item'      => __('Insert into activity', 'G3'),
            'uploaded_to_this_item' => __('UPloaded to this activity', 'G3'),
            'filter_items_list'     => __('Filter activities list', 'G3'),
            'items_list_navigation' => __('Activities list navigation', 'G3'),
            'items_list'            => __('Activities list', 'G3'),
        ];
        $args   = [
            'labels'             => $labels,
            'public'             => true,
            'publicly_queryable' => true,
            'show_ui'            => true,
            'show_in_menu'       => true,
            'show_in_admin_bar'  => true,
            'show_in_nav_menus'  => true,
            'query_var'          => true,
            'rewrite'            => ['slug' => $this->postType],
            'capability_type'    => 'post',
            'has_archive'        => true,
            'hierarchical'       => false,
            'menu_position'      => null,
            'show_in_rest'       => true,
            'supports'           => ['title', 'editor', 'comments', 'revisions', 'author', 'excerpt', 'thumbnail', 'post-formats'],
            'menu_icon'          => 'dashicons-universal-access-alt',
        ];
        register_post_type($this->postType, $args);
    }
    public function taxonomy(): void
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
                    'name'              => __('Categories', 'G3'),
                    'singular_name'     => __('Category', 'G3'),
                    'search_items'      => __('Search Categories', 'G3'),
                    'not_found'         => __('No categories found.', 'G3'),
                    'all_items'         => __('All Categories', 'G3'),
                    'parent_item'       => __('Parent Category', 'G3'),
                    'parent_item_colon' => __('Parent Category:', 'G3'),
                    'edit_item'         => __('Edit Category', 'G3'),
                    'update_item'       => __('Update Category', 'G3'),
                    'add_new_item'      => __('Add New Category', 'G3'),
                    'new_item_name'     => __('New Category Name', 'G3'),
                    'menu_name'         => __('Categories', 'G3'),
                ]
            ]
        );
        register_taxonomy(
            'activity_tag',
            $this->postType,
            [
                'hierarchical'      => false,
                'public'            => true,
                'show_ui'           => true,
                'query_var'         => true,
                'show_admin_column' => true,
                '_builtin'          => true,
            ]
        );
    }
}
