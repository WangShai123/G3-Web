<?php
namespace JEALER\G3\Components;
use JEALER\G3\Components\Components;
use JEALER\G3\Services\SidebarService;
use Override;

class Announcement extends Components {
    private string $postType = 'announcement';
    protected function hooks()
    {
        $this->filter([
            'post_updated_messages' => [[$this, 'resetUpdatedMessages'], 1]
        ]);
    }
    protected function postType()
    {
        $labels = [
            'name'               => __('Announcements', 'G3'),
            'singular_name'      => __('Announcement', 'G3'),
            'menu_name'          => __('Announcements', 'G3'),
            'name_admin_bar'     => __('Announcements', 'G3'),
            'add_new'            => __('Add New', 'G3'),
            'add_new_item'       => __('Add New', 'G3'),
            'new_item'           => __('New Announcement', 'G3'),
            'edit_item'          => __('Edit'),
            'view_item'          => __('View'),
            'all_items'          => __('All'),
            'search_items'       => __('Search'),
            'parent_item_colon'  => __('Parent'),
            'not_found'          => __('No items found.'),
            'not_found_in_trash' => __('No items found.'),
            // 'featured_image'        => __('Cover', 'G3'),
            // 'set_featured_image'    => __('Set Cover', 'G3'),
            // 'remove_featured_image' => __('Remove Cover', 'G3'),
            // 'use_featured_image'    => __('Use Cover', 'G3'),
            'archives'           => __('Archives'),
            'insert_into_item'   => __('Add'),
            // 'uploaded_to_this_item' => __('Uploaded to Announcement', 'G3'),
            // 'filter_items_list'     => __('Filter Announcements List', 'G3'),
            // 'items_list_navigation' => __('Announcements List Navigation', 'G3'),
            // 'items_list'            => __('Announcements List', 'G3'),
        ];
        register_post_type(
            $this->postType,
            [
                'labels'          => $labels,
                'public'          => true,
                'show_in_menu'    => true,
                'query_var'       => true,
                'capability_type' => 'post',
                'has_archive'     => true,
                'menu_position'   => null,
                'show_in_rest'    => true,
                'supports'        => ['title', 'editor', 'comments', 'revisions', 'author', 'excerpt', 'thumbnail', 'post-formats'],
                'menu_icon'       => 'dashicons-megaphone',
                'taxonomies'      => ['announcement_category', 'post_tag'],
            ]
        );
    }
    protected function taxonomy()
    {
        register_taxonomy(
            'announcement_category',
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
                ],
            ]
        );
    }
    public function resetUpdatedMessages($messages): array
    {
        $post           = get_post();
        $postType       = $this->postType;
        $postTypeObject = get_post_type_object($postType);

        $messages[$postType] = [
            0  => '',
            // Unused. Messages start at index 1.
            1  => __('Updated', 'G3'),
            2  => __('Updated', 'G3'),
            3  => __('Deleted', 'G3'),
            4  => __('Updated', 'G3'),
            /* translators: %s: date and time of the revision */
            5  => isset($_GET['revision']) ? sprintf(__('Announcement restored to version from %s.', 'G3'), wp_post_revision_title((int) $_GET['revision'], false)) : false,
            6  => __('Published', 'G3'),
            7  => __('Saved', 'G3'),
            8  => __('Submitted', 'G3'),
            9  => sprintf(
                __('Announcement scheduled for:<strong>%1$s</strong>.', 'G3'),
                wp_date(get_option('date_format') . ' ' . get_option('time_format'), strtotime($post->post_date))
            ),
            10 => __('Updated', 'G3'),
        ];

        if ($postTypeObject->publicly_queryable) {
            $permalink = get_permalink($post->ID);

            $viewLink                = sprintf(' <a href="%s" target="_blank">%s</a>', esc_url($permalink), __('View'));
            $messages[$postType][1] .= $viewLink;
            $messages[$postType][6] .= $viewLink;
            $messages[$postType][9] .= $viewLink;

            $previewPermalink         = add_query_arg('preview', 'true', $permalink);
            $previewLink              = sprintf('<a target="_blank" href="%s">%s</a>', esc_url($previewPermalink), __('Preview'));
            $messages[$postType][8]  .= $previewLink;
            $messages[$postType][10] .= $previewLink;
        }

        return $messages;
    }
    protected function widgets()
    {
        SidebarService::registerWidget('AnnouncementWidget', __DIR__);
    }
}
