<?php
namespace JEALER\G3\Components;
use JEALER\G3\Components\Components;
use Override;

class Activity extends Components {
    private string $postType = 'activity';
    protected function postType()
    {
        $activity   = __('Activity', 'G3');
        $activities = __('Activities', 'G3');
        $list       = __('List', 'G3');

        $ss  = __('%s %s', 'G3');
        $sss = __('%s %s %s', 'G3');

        $labels = [
            'name'                  => $activities,
            'singular_name'         => $activity,
            'menu_name'             => $activities,
            'name_admin_bar'        => $activity,
            'add_new'               => __('Add New', 'G3'),
            'add_new_item'          => sprintf($ss, __('Add New', 'G3'), $activity),
            'new_item'              => sprintf(__('New %s', 'G3'), $activity),
            'edit_item'             => sprintf($ss, __('Edit'), $activity),
            'view_item'             => sprintf(__('View %s', 'G3'), $activity),
            'all_items'             => sprintf($ss, __('All', 'G3'), $activity),
            'search_items'          => sprintf($ss, __('Search'), $activity),
            'parent_item_colon'     => sprintf(__('Parent %s', 'G3') . ':', $activity),
            'not_found'             => sprintf(__('No %s found.', 'G3'), $activity),
            'not_found_in_trash'    => sprintf(__('No %s found in trash.', 'G3'), $activity),
            'featured_image'        => __('Cover', 'G3'),
            'set_featured_image'    => __('Set cover', 'G3'),
            'remove_featured_image' => __('Remove cover', 'G3'),
            'use_featured_image'    => __('Use as cover', 'G3'),
            'archives'              => sprintf($ss, $activity, __('Archives')),
            'insert_into_item'      => sprintf(__('Insert into %s', 'G3'), $activity),
            'uploaded_to_this_item' => sprintf(__('Uploaded to this %s', 'G3'), $activity),
            'filter_items_list'     => sprintf($sss, __('Filter', 'G3'), $activity, $list),
            'items_list_navigation' => sprintf($sss, $activity, $list, __('Navigation')),
            'items_list'            => sprintf($ss, $activity, $list),
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
        $categories = __('Categories');
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
                    'name'          => $categories,
                    'singular_name' => __('Category'),
                    'menu_name'     => $categories,
                ]
            ]
        );
    }
}
