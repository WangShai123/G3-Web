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
        $announcement  = __('Announcement', 'G3');
        $announcements = __('Announcements', 'G3');
        $list          = __('List', 'G3');

        $ss  = __('%s %s', 'G3');
        $sss = __('%s %s %s', 'G3');

        $addNew = __('Add New', 'G3');

        $labels = [
            'name'                  => $announcements,
            'singular_name'         => $announcement,
            'menu_name'             => $announcements,
            'name_admin_bar'        => $announcements,
            'add_new'               => $addNew,
            'add_new_item'          => sprintf($ss, $addNew, $announcement),
            'new_item'              => sprintf(__('New %s', 'G3'), $announcement),
            'edit_item'             => sprintf($ss, __('Edit'), $announcement),
            'view_item'             => sprintf(__('View %s', 'G3'), $announcement),
            'all_items'             => sprintf($ss, __('All', 'G3'), $announcement),
            'search_items'          => sprintf($ss, __('Search'), $announcement),
            'parent_item_colon'     => sprintf(__('Parent %s', 'G3') . ':', $announcement),
            'not_found'             => sprintf(__('No %s found.', 'G3'), $announcement),
            'not_found_in_trash'    => sprintf(__('No %s found in trash.', 'G3'), $announcement),
            'featured_image'        => __('Cover', 'G3'),
            'set_featured_image'    => __('Set cover', 'G3'),
            'remove_featured_image' => __('Remove cover', 'G3'),
            'use_featured_image'    => __('Use as cover', 'G3'),
            'archives'              => sprintf($ss, $announcement, __('Archives')),
            'insert_into_item'      => sprintf(__('Insert into %s', 'G3'), $announcement),
            'uploaded_to_this_item' => sprintf(__('Uploaded to this %s', 'G3'), $announcement),
            'filter_items_list'     => sprintf($sss, __('Filter', 'G3'), $announcements, $list),
            'items_list_navigation' => sprintf($sss, $announcements, $list, __('Navigation')),
            'items_list'            => sprintf($ss, $announcements, $list),
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
        $categories = __('Categories');
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
                    'name'          => $categories,
                    'singular_name' => __('Category'),
                    'menu_name'     => $categories,
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
            5  => isset($_GET['revision']) ? sprintf(__('Restored to version from %s.', 'G3'), wp_post_revision_title((int) $_GET['revision'], false)) : false,
            6  => __('Published'),
            7  => __('Saved'),
            8  => __('Submitted', 'G3'),
            9  => sprintf(
                __('Scheduled for:<strong>%1$s</strong>.', 'G3'),
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
