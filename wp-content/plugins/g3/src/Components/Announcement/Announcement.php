<?php
namespace JEALER\G3\Components;
use JEALER\G3\Components;
use JEALER\G3\Services\SidebarService;
class Announcement extends Components {
    private string $postType = 'announcement';

    #[\Override]
    protected function admin(): void
    {
        add_filter('post_updated_messages', [$this, 'resetUpdatedMessages']);
    }
    #[\Override]
    protected function postType(): void
    {
        $labels = [
            'name'                  => __('Announcements', 'G3'),
            'singular_name'         => __('Announcement', 'G3'),
            'menu_name'             => __('Announcements', 'G3'),
            'name_admin_bar'        => __('Announcements', 'G3'),
            'add_new'               => __('Add New', 'G3'),
            'add_new_item'          => __('Add New Announcement', 'G3'),
            'new_item'              => __('New Announcement', 'G3'),
            'edit_item'             => __('Edit Announcement', 'G3'),
            'view_item'             => __('View Announcement', 'G3'),
            'all_items'             => __('All Announcements', 'G3'),
            'search_items'          => __('Search Announcements', 'G3'),
            'parent_item_colon'     => __('Parent Announcement:', 'G3'),
            'not_found'             => __('No announcements found.', 'G3'),
            'not_found_in_trash'    => __('No announcements found in trash.', 'G3'),
            'featured_image'        => __('Cover', 'G3'),
            'set_featured_image'    => __('Set Cover', 'G3'),
            'remove_featured_image' => __('Remove Cover', 'G3'),
            'use_featured_image'    => __('Use Cover', 'G3'),
            'archives'              => __('Announcements Archives', 'G3'),
            'insert_into_item'      => __('Insert into Announcement', 'G3'),
            'uploaded_to_this_item' => __('Uploaded to Announcement', 'G3'),
            'filter_items_list'     => __('Filter Announcements List', 'G3'),
            'items_list_navigation' => __('Announcements List Navigation', 'G3'),
            'items_list'            => __('Announcements List', 'G3'),
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
                'supports'        => ['title', 'editor', 'comments', 'revisions', 'author', 'excerpt', 'thumbnail', 'post-formats'],
                'menu_icon'       => 'dashicons-megaphone',
                'taxonomies'      => ['announcement_category', 'post_tag'],
            ]
        );
    }
    #[\Override]
    protected function taxonomy(): void
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
                    'name'          => __('Announcement Categories', 'G3'),
                    'singular_name' => __('Announcement Category', 'G3'),
                    'menu_name'     => __('Categories', 'G3'),
                ],
            ]
        );
    }
    public function resetUpdatedMessages($messages)
    {
        $post           = get_post();
        $postType       = $this->postType;
        $postTypeObject = get_post_type_object($postType);

        $messages[$postType] = [
            0  => '',
            // Unused. Messages start at index 1.
            1  => __('Announcement updated.', 'G3'),
            2  => __('Custom field updated.', 'G3'),
            3  => __('Custom field deleted.', 'G3'),
            4  => __('Announcement updated.', 'G3'),
            /* translators: %s: date and time of the revision */
            5  => isset($_GET['revision']) ? sprintf(__('Announcement restored to version from %s.', 'G3'), wp_post_revision_title((int) $_GET['revision'], false)) : false,
            6  => __('Announcement published.', 'G3'),
            7  => __('Announcement saved.', 'G3'),
            8  => __('Announcement submitted.', 'G3'),
            9  => sprintf(
                __('Announcement scheduled for:<strong>%1$s</strong>.', 'G3'),
                wp_date(get_option('date_format') . ' ' . get_option('time_format'), strtotime($post->post_date))
            ),
            10 => __('Announcement draft updated.', 'G3'),
        ];

        if ($postTypeObject->publicly_queryable) {
            $permalink = get_permalink($post->ID);

            $viewLink                = sprintf(' <a href="%s">%s</a>', esc_url($permalink), __('View Announcement', 'G3'));
            $messages[$postType][1] .= $viewLink;
            $messages[$postType][6] .= $viewLink;
            $messages[$postType][9] .= $viewLink;

            $previewPermalink         = add_query_arg('preview', 'true', $permalink);
            $previewLink              = sprintf('<a target="_blank" href="%s">%s</a>', esc_url($previewPermalink), __('Preview Announcement', 'G3'));
            $messages[$postType][8]  .= $previewLink;
            $messages[$postType][10] .= $previewLink;
        }

        return $messages;
    }
    #[\Override]
    protected function widgets(): void
    {
        SidebarService::registerWidget('AnnouncementWidget', __DIR__);
    }
}