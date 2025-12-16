<?php
namespace JEALER\G3\Components;

use JEALER\G3\Components;
use JEALER\G3\Utilities\Container;
use JEALER\G3\Utilities\Option;
use JEALER\G3\Utilities\Validator;
use JEALER\G3\Services\SwiperService;
class Swiper extends Components {
    public array $option;
    protected function options(): void
    {
        $default      = Option::get(SwiperService::LOCATION_OPTION_KEY, [
            'home' => __('Home')
        ]);
        $this->option = Option::cache(SwiperService::LOCATION_OPTION_KEY, $default);
    }
    protected function admin(): void
    {
        $this->wpAjax();
    }
    protected function adminMenu(): void
    {
        $this->submenu();
        $this->edit();
        add_action('admin_head', function () {
            remove_submenu_page('themes.php', 'swiper');
        });
    }
    private function submenu(): void
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
    }
    public function render(): void
    {
        echo '<div class="wrap">';
        echo '<h1 class="wp-heading-inline">' . __('Swiper', 'G3') . '</h1>';
        $tabs = [
            'swipers'   => __('Edit Swipers', 'G3'),
            'locations' => __('Manage Locations', 'G3'),
        ];
        Container::tab('swiper', 'swipers', $tabs);
        echo '</div>';
    }
    public function edit(): void
    {
        add_submenu_page(
            'themes.php',
            __('Add Swiper', 'G3'),
            __('Add Swiper', 'G3'),
            'manage_options',
            'swiper',
            [$this, 'editSwiper']
        );
    }
    public function editSwiper(): void
    {
        @require_once __DIR__ . '/views/page-edit.php';
    }
    public function wpAjax()
    {
        add_action('wp_ajax_edit_location', function () {
            if (!current_user_can('manage_options')) {
                wp_send_json_error([
                    'code'    => 403,
                    'message' => __('Access Denied!', 'G3'),
                ], 403);
            }

            $key  = $_POST['key'] ?? null;
            $name = $_POST['name'] ?? null;

            if (!$key || !$name) {
                wp_send_json_error([
                    'code'    => 400,
                    'message' => __('Invalid data', 'G3'),
                ], 400);
            }

            if (!preg_match('/^[a-zA-Z]+$/', $key)) {
                wp_send_json_error([
                    'code'    => 400,
                    'message' => __('Slug: Only supports English Letter', 'G3')
                ], 400);
            }

            $this->option[$key] = $name;
            update_option(SwiperService::LOCATION_OPTION_KEY, $this->option);

            wp_send_json_success([
                'code'    => 200,
                'message' => __('Updated', 'G3'),
            ]);
        });

        add_action('wp_ajax_edit_swiper', function () {
            if (!current_user_can('manage_options')) {
                wp_send_json_error([
                    'code'    => 403,
                    'message' => __('Access Denied!', 'G3'),
                ], 403);
            }
            $id       = $_POST['id'] ?? null;
            $title    = $_POST['title'] ?? null;
            $media    = $_POST['media'] ?? null;
            $link     = $_POST['link'] ?? null;
            $target   = $_POST['target'] ?? null;
            $location = $_POST['location'] ?? null;
            $sort     = $_POST['sort'] ?? null;
            $status   = $_POST['status'] ?? null;

            if (!$title || !$location || !$sort || !$status) {
                wp_send_json_error([
                    'code'    => 400,
                    'message' => __('Invalid Data', 'G3'),
                ], 400);
            }
            if (!Validator::isImage($media)) {
                wp_send_json_error([
                    'code'    => 400,
                    'message' => __('Invalid Image', 'G3'),
                ], 400);
            }
            if (!Validator::isURL($link)) {
                wp_send_json_error([
                    'code'    => 400,
                    'message' => __('Invalid Link', 'G3'),
                ], 400);
            }

            $dateTime = date('Y-m-d H:i:s');
            $user     = wp_get_current_user()->ID;

            global $wpdb;
            $table = $wpdb->prefix . 'g3_swipers';
            $group = 'g3_swiper';

            $data = [
                'title'    => $title,
                'media'    => $media,
                'link'     => $link,
                'target'   => (int) $target,
                'location' => $location,
                'sort'     => (int) $sort,
                'status'   => (int) $status,
                'user'     => $user,
                'updated'  => $dateTime,
            ];

            if (!$id) {
                $data['created'] = $dateTime;
                $wpdb->insert($table, $data);
                $id = $wpdb->insert_id;

                wp_send_json_success([
                    'code'    => 200,
                    'message' => __('Added!', 'G3'),
                    'id'      => $id,
                ]);
            }
            $wpdb->update($table, $data, ['id' => $id]);
            wp_cache_set($id, $data, $group);
            wp_send_json_success([
                'code'    => 200,
                'message' => __('Updated', 'G3'),
                'id'      => $id,
            ]);
        });
    }

}