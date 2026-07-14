<?php
namespace JEALER\G3\Components;
use JEALER\G3\Components\Components;
use JEALER\G3\Services\UserService;
use JEALER\G3\Services\SystemService;
use JEALER\G3\Utilities\Common;
use JEALER\G3\Utilities\Element;
use JEALER\G3\Utilities\Message;
use JEALER\G3\Utilities\Option;
use JEALER\G3\Utilities\Response;
use Override;
use WP_User;

class User extends Components {
    protected function hooks()
    {
        $this->filter([
            'g3_filter_title' => [[$this, 'templateTitle'], 10, 1],
        ]);
    }
    #[Override]
    protected function init(): void
    {
        add_filter('get_avatar', [$this, 'resetAvatar'], 1, 6);
    }
    #[Override]
    protected function adminMenu(): void
    {
        add_submenu_page(
            'g3-settings',
            __('Users'),
            __('Users'),
            'manage_options',
            'user-settings',
            [$this, 'userSettingsRender'],
            4
        );
        add_submenu_page(
            'g3-settings',
            __('Membership', 'G3'),
            __('Membership', 'G3'),
            'manage_options',
            'membership',
            [$this, 'membershipPaymentRender'],
            5
        );
    }
    public function userSettingsRender(): void
    {
        echo '<div class="wrap">';
        echo '<h1 class="wp-heading-inline">' . __('Users') . '</h1>';
        Element::tab('User', 'level', [
            'level'   => __('Level Group', 'G3'),
            'custom'  => __('Custom Group', 'G3'),
            'manager' => __('Manager Group', 'G3'),
            'premium' => __('Premium Config', 'G3')
        ]);
        echo '</div>';
    }
    public function membershipPaymentRender(): void
    {
        echo '<div class="wrap">';
        echo '<h1 class="wp-heading-inline">' . __('Membership', 'G3') . '</h1>';
        Element::tab('User', 'projects', [
            'projects' => __('Membership Projects', 'G3'),
            'duration' => __('Membership Duration', 'G3'),
            'card'     => __('Membership Card', 'G3')
        ]);
        echo '</div>';
    }
    public function resetAvatar($avatar, $id_or_email, $size, $default, $alt): bool|string
    {
        if (!get_option('show_avatars')) return false;

        $safeAlt = $alt === false ? '' : esc_attr($alt);

        $size = is_numeric($size) ? absint($size) : 64;

        /**
         * Parse $id_or_email, support:
         * - user_id
         * - email
         * - WP_Comment
         * - WP_User
         * - WP_Post
         */
        $user_id = 0;

        if (is_object($id_or_email)) {

            if (isset($id_or_email->user_id)) {
                // WP_Comment
                $user_id = (int) $id_or_email->user_id;
            } elseif ($id_or_email instanceof WP_User) {
                // WP_User
                $user_id = $id_or_email->ID;
            }

        } elseif (is_numeric($id_or_email)) {
            $user_id = absint($id_or_email);

        } elseif (is_email($id_or_email)) {
            // email => user_id
            $user_id = (int) email_exists($id_or_email);
        }

        // if not found, try current user
        if (!$user_id) {
            $currentUser = wp_get_current_user();
            if ($currentUser && $currentUser->ID) {
                $user_id = $currentUser->ID;
            }
        }

        if (!$user_id) {
            return $avatar;
        }

        // get user avatar from meta
        $userAvatar = UserService::getMeta($user_id, UserService::META_KEY, 'avatar', '');

        // fallback to default avatar
        $option        = get_option(SystemService::OPTION_KEY, []);
        $defaultAvatar = is_array($option) ? ($option['avatar'] ?? '') : '';
        $avatarUrl     = !empty($userAvatar) ? $userAvatar : $defaultAvatar;

        // if avatar url is empty, return default avatar
        if (empty($avatarUrl)) {
            return $avatar;
        }

        return sprintf(
            '<img id="user_avatar_img" crossOrigin="anonymous" src="%s" class="avatar avatar-%d photo" width="%d" height="%d" alt="%s" />',
            esc_url($avatarUrl),
            $size,
            $size,
            $size,
            $safeAlt
        );
    }
    public function templateTitle(string $title): string
    {
        $var = get_query_var('g3_var_my', false);
        if ($var) {
            return match ($var) {
                'home'    => __('My Homepage', 'G3'),
                'message' => __('My Messages', 'G3'),
                'order'   => __('My Orders', 'G3'),
                'wallet'  => __('My Wallet', 'G3'),
                'profile' => __('My Profile', 'G3'),
                'setting' => __('My Settings', 'G3'),
                default   => $title,
            };
        }

        $var = get_query_var('g3_var_user', false);
        if ($var) {
            return match ($var) {
                'login'    => __('Login', 'G3'),
                'register' => __('Register', 'G3'),
                'reset'    => __('Reset Password', 'G3'),
                default    => $title,
            };
        }

        return $title;
    }
    protected function ajax(): void
    {
        $roles = get_option(UserService::ROLE_OPTION_KEY, []);

        add_action('wp_ajax_g3_reset_role', function () use ($roles) {
            $slug = $_POST['slug'] ?? '';
            if ($slug === 'abandon') {
                if ($roles['abandon']['name'] === 'Limited User') {
                    Response::ajaxError(__('The role name has been initialized.', 'G3'));
                }
                $roles['abandon']['name'] = 'Limited User';
                update_option(UserService::ROLE_OPTION_KEY, $roles);
                Response::ajaxUpdated();
            } else if ($slug === 'beginner') {
                if ($roles['beginner']['name'] === 'Beginner') {
                    Response::ajaxError(__('The role name has been initialized.', 'G3'));
                }
                $roles['beginner']['name'] = 'Beginner';
                update_option(UserService::ROLE_OPTION_KEY, $roles);
                Response::ajaxUpdated();
            }
            Response::ajaxIllegal();
        });
        add_action('wp_ajax_g3_delete_role', function () use ($roles) {
            if (!isset($_POST['slug'])) {
                Response::ajaxIllegal();
            }
            unset($roles[$_POST['slug']]);
            $result = update_option(UserService::ROLE_OPTION_KEY, $roles);
            if ($result) {
                Response::ajaxDeleted();
            } else {
                Response::ajaxFailed();
            }
        });
        add_action('wp_ajax_g3_edit_role', function () use ($roles) {
            $data = $_POST['data'];

            if (!isset($data['slug']) && !$data['slug']) {
                Response::ajaxError(__('Data missing', 'G3'));
            }

            if ($data['slug'] !== 'abandon') {
                if (!preg_match('/^[a-zA-Z0-9]+$/', $data['slug'])) {
                    Response::ajaxError(__('The role slug can only contain English and numbers.', 'G3'));
                }
                if (!preg_match('/^[0-9]\d*$/', $data['start']) || !preg_match('/^[1-9]\d*$/', $data['end'])) {
                    Response::ajaxError(__('Start and end credits must be positive integers.', 'G3'));
                }
            }

            $start = (int) $data['start'];
            $end   = (int) $data['end'];
            if ($start >= $end && $data['slug'] !== 'abandon') {
                Response::ajaxError(__('The start credits must be less than the end credits.', 'G3'));
            }

            $roles[$data['slug']]['name'] = $data['name'];
            $roles[$data['slug']]['slug'] = $data['slug'];
            if ($data['start'] !== '-∞') {
                $roles[$data['slug']]['credits'][0] = $start;
            }
            $roles[$data['slug']]['credits'][1] = $end;

            $result = update_option(UserService::ROLE_OPTION_KEY, $roles);
            if ($result) {
                Response::ajaxUpdated();
            } else {
                Response::ajaxFailed();
            }
        });

        add_action('wp_ajax_g3_edit_custom_role', function () {
            $data = $_POST['data'];
            if (!isset($data['name']) || !isset($data['slug']) || trim($data['name']) == '' || trim($data['slug']) == '') {
                Response::ajaxError(__('Data missing', 'G3'));
            }
            if (!preg_match('/^[a-zA-Z0-9]+$/', $data['slug'])) {
                Response::ajaxError(__('The role slug can only contain English and numbers.', 'G3'));
            }
            $roles                = get_option(UserService::GROUP_OPTION_KEY, []);
            $roles[$data['slug']] = [
                'name' => $data['name'],
                'slug' => $data['slug']
            ];
            $result               = update_option(UserService::GROUP_OPTION_KEY, $roles);
            if ($result) {
                Response::ajaxUpdated();
            } else {
                Response::ajaxFailed();
            }
        });
        add_action('wp_ajax_g3_delete_custom_role', function () {
            $slug = $_POST['slug'];
            if (!isset($slug) || trim($slug) === '') {
                Response::ajaxError(__('Data missing', 'G3'));
            }
            $roles = get_option(UserService::GROUP_OPTION_KEY, []);
            unset($roles[$slug]);
            $result = update_option(UserService::GROUP_OPTION_KEY, $roles);
            if ($result) {
                Response::ajaxDeleted();
            } else {
                Response::ajaxFailed();
            }
        });

        add_action('wp_ajax_g3_edit_manager_role', function () {
            $data = $_POST['data'];
            if (!isset($data['name']) || !isset($data['slug']) || trim($data['name']) == '' || trim($data['slug']) == '') {
                Response::ajaxError(__('Data missing', 'G3'));
            }
            if (!preg_match('/^[a-zA-Z0-9]+$/', $data['slug'])) {
                Response::ajaxError(__('The role slug can only contain English and numbers.', 'G3'));
            }
            $roles                = get_option(UserService::MANAGER_OPTION_KEY, []);
            $roles[$data['slug']] = [
                'name' => $data['name'],
                'slug' => $data['slug'],
                'type' => 2
            ];
            $result               = update_option(UserService::MANAGER_OPTION_KEY, $roles);
            if ($result) {
                Response::ajaxUpdated();
            } else {
                Response::ajaxFailed();
            }
        });
        add_action('wp_ajax_g3_delete_manager_role', function () {
            $slug = $_POST['slug'];
            if (!isset($slug) || trim($slug) === '') {
                Response::ajaxError(__('Data missing', 'G3'));
            }
            $roles = get_option(UserService::MANAGER_OPTION_KEY, []);
            unset($roles[$slug]);
            $result = update_option(UserService::MANAGER_OPTION_KEY, $roles);
            if ($result) {
                Response::ajaxDeleted();
            } else {
                Response::ajaxFailed();
            }
        });

        add_action('wp_ajax_g3_edit_premium_config', function () {
            $data = $_POST['data'];
            if (!isset($data['name']) || !isset($data['slug']) || trim($data['name']) == '' || trim($data['slug']) == '') {
                Response::ajaxError(__('Data missing', 'G3'));
            }
            if (!preg_match('/^[a-zA-Z0-9]+$/', $data['slug'])) {
                Response::ajaxError(__('The role slug can only contain English and numbers.', 'G3'));
            }
            $roles                = get_option(UserService::PREMIUM_OPTION_KEY, []);
            $roles[$data['slug']] = [
                'name' => $data['name'],
                'slug' => $data['slug']
            ];
            $result               = update_option(UserService::PREMIUM_OPTION_KEY, $roles);
            if ($result) {
                Response::ajaxUpdated();
            } else {
                Response::ajaxFailed();
            }
        });
        add_action('wp_ajax_g3_delete_premium_config', function () {
            $slug = $_POST['slug'];
            if (!isset($slug) || trim($slug) === '') {
                Response::ajaxError(__('Data missing', 'G3'));
            }
            $roles = get_option(UserService::PREMIUM_OPTION_KEY, []);
            unset($roles[$slug]);
            $result = update_option(UserService::PREMIUM_OPTION_KEY, $roles);
            if ($result) {
                Response::ajaxDeleted();
            } else {
                Response::ajaxFailed();
            }
        });

        add_action('wp_ajax_g3_edit_membership_duration', function () {
            $data     = $_POST['data'];
            $name     = $data['name'] ?? '';
            $slug     = $data['slug'] ?? '';
            $duration = (int) ($data['duration'] ?? 0);
            $unit     = $data['unit'] ?? '';

            if (empty($name) || empty($slug) || !$duration || empty($unit)) {
                Response::ajaxParamMissing();
            }
            if (!preg_match('/^[a-zA-Z0-9_]+$/', $slug)) {
                Response::ajaxError(__('Slug Param only allows English letters, numbers, and underscores', 'G3'));
            }

            $array        = get_option(UserService::DURATION_OPTION_KEY, []);
            $array[$slug] = [
                'name'     => $name,
                'slug'     => $slug,
                'duration' => Common::toSeconds($duration, $unit)
            ];

            $result = update_option(UserService::DURATION_OPTION_KEY, $array);
            if ($result) {
                Response::ajaxUpdated();
            } else {
                Response::ajaxFailed();
            }
        });
        add_action('wp_ajax_g3_delete_membership_duration', function () {
            $slug = $_POST['data']['slug'] ?? '';
            if (empty($slug)) {
                Response::ajaxParamMissing();
            }

            $array = get_option(UserService::DURATION_OPTION_KEY, []);
            unset($array[$slug]);
            $result = update_option(UserService::DURATION_OPTION_KEY, $array);
            if ($result) {
                Response::ajaxDeleted();
            } else {
                Response::ajaxFailed();
            }
        });

        add_action('wp_ajax_g3_edit_membership_project', function () {
            $data     = $_POST['data'];
            $name     = $data['name'] ?? '';
            $duration = $data['duration'] ?? '';
            $price    = $data['price'] ?? 0;
            if (empty($name) || empty($duration) || !$price) {
                Response::ajaxParamMissing();
            }
            if (!preg_match('/^[a-zA-Z0-9_]+$/', $name)) {
                Response::ajaxError(__('Slug Param only allows English letters, numbers, and underscores', 'G3'));
            }
            $array      = get_option(UserService::MEMBERSHIP_OPTION_KEY, []);
            $id         = md5($name . '-' . $duration);
            $array[$id] = [
                'id'       => $id,
                'name'     => $name,
                'duration' => $duration,
                'price'    => $price
            ];
            $result     = update_option(UserService::MEMBERSHIP_OPTION_KEY, $array);
            if ($result) {
                Response::ajaxUpdated();
            } else {
                Response::ajaxFailed();
            }
        });
        add_action('wp_ajax_g3_delete_membership_project', function () {
            $id = $_POST['data']['id'] ?? '';
            if (empty($id)) {
                Response::ajaxParamMissing();
            }
            $array = get_option(UserService::MEMBERSHIP_OPTION_KEY, []);
            unset($array[$id]);
            $result = update_option(UserService::MEMBERSHIP_OPTION_KEY, $array);
            if ($result) {
                Response::ajaxDeleted();
            } else {
                Response::ajaxFailed();
            }
        });
    }
}
