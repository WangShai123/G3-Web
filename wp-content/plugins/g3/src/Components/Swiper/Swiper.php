<?php

namespace JEALER\G3\Components;

use JEALER\G3\Components\Components;
use JEALER\G3\Services\SwiperService;
use JEALER\G3\Utilities\Element;
use JEALER\G3\Utilities\Option;
use JEALER\G3\Utilities\Validator;
use JEALER\G3\Utilities\Response;
use Override;

class Swiper extends Components {

    public array $option;

    #[Override]
    protected function options(): void
    {
        $this->option = Option::get(SwiperService::LOCATION_OPTION_KEY, [
            'home' => __('Home')
        ]);
    }

    #[Override]
    protected function adminMenu(): void
    {
        add_submenu_page(
            'themes.php',
            __('Swiper', 'G3'),
            __('Swiper', 'G3'),
            'manage_options',
            'swipers',
            [$this, 'render'],
            4
        );
        add_submenu_page(
            'themes.php',
            __('Add Swiper', 'G3'),
            __('Add Swiper', 'G3'),
            'manage_options',
            'swiper',
            [$this, 'editSwiper']
        );
        add_action('admin_head', function () {
            remove_submenu_page('themes.php', 'swiper');
        });
    }

    public function render(): void
    {
        echo '<div class="wrap">';
        echo '<h1 class="wp-heading-inline">' . __('Swiper', 'G3') . '</h1>';
        $tabs = [
            'swipers'   => __('Edit Swipers', 'G3'),
            'locations' => __('Manage Locations', 'G3'),
        ];
        Element::tab('Swiper', 'swipers', $tabs);
        echo '</div>';
    }

    public function editSwiper(): void
    {
        @require_once __DIR__ . '/views/page-edit.php';
    }

    #[Override]
    protected function ajax(): void
    {
        add_action('wp_ajax_edit_swiper', function () {
            if (!current_user_can('manage_options')) {
                Response::ajaxForbidden();
            }
            $id       = $_POST['id'] ?? null;
            $title    = $_POST['title'] ?? null;
            $media    = $_POST['media'] ?? null;
            $link     = $_POST['link'] ?? null;
            $target   = $_POST['target'] ?? null;
            $location = $_POST['location'] ?? null;
            $sort     = $_POST['sort'] ?? null;
            $status   = $_POST['status'] ?? null;

            if ($status == null) {
                Response::ajaxError(__('Data Missing', 'G3') . '. ' . __('Please set status data again', 'G3'));
            }
            if (!$title || !$location || !$sort) {
                Response::ajaxError(__('Please fill in the complete data', 'G3'));
            }
            if (!Validator::isImage($media)) {
                Response::ajaxError(__('Invalid Image', 'G3'));
            }
            if (!Validator::isURL($link)) {
                Response::ajaxError(__('Invalid Link', 'G3'));
            }

            $userId = wp_get_current_user()->ID;

            global $wpdb;
            $table = $wpdb->prefix . SwiperService::TABLE;

            $data = [
                'title'      => $title,
                'media'      => $media,
                'link'       => $link,
                'target'     => (int) $target,
                'location'   => $location,
                'sort'       => (int) $sort,
                'status'     => (int) $status,
                'user_id'    => $userId,
                'updated_at' => gmdate('Y-m-d H:i:s'),
            ];

            if (!$id) {
                $data['created_at'] = gmdate('Y-m-d H:i:s');
                $wpdb->insert($table, $data);
                $id = $wpdb->insert_id;
                Response::ajaxUpdated();
                wp_cache_delete($location, SwiperService::QUERY_CACHE_GROUP);
            } else {
                $result = $wpdb->update($table, $data, ['id' => $id]);
                if ($result) {
                    wp_cache_delete($id, SwiperService::CACHE_GROUP);
                    wp_cache_delete($location, SwiperService::QUERY_CACHE_GROUP);
                    Response::ajaxUpdated();
                } else {
                    Response::ajaxFailed();
                }
            }
        });

        add_action('wp_ajax_edit_swiper_location', function () {
            if (!current_user_can('manage_options')) {
                Response::ajaxForbidden();
            }

            $key  = $_POST['key'] ?? null;
            $name = $_POST['name'] ?? null;

            if (!$key || !$name) {
                Response::ajaxError(__('Please fill in the complete data', 'G3'));
            }

            if (!preg_match('/^[a-zA-Z]+$/', $key)) {
                Response::ajaxError(__('Slug: Only supports English Letter', 'G3'));
            }

            $this->option[$key] = $name;

            $result = update_option(SwiperService::LOCATION_OPTION_KEY, $this->option);
            if ($result) {
                Response::ajaxUpdated();
            } else {
                Response::ajaxFailed();
            }
        });

        add_action('wp_ajax_delete_swiper_location', function () {
            if (!current_user_can('manage_options')) {
                Response::ajaxForbidden();
            }
            $key = $_POST['key'] ?? null;
            if (!$key) {
                Response::ajaxError(__('Please fill in the complete data', 'G3'));
            }
            $result = SwiperService::deleteLocations($key);
            if ($result) {
                Response::ajaxDeleted();
            } else {
                Response::ajaxError(__('Delete Failed', 'G3'));
            }
        });
    }

}