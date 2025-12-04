<?php
namespace JEALER\G3\Components;
use JEALER\G3\Components;
use JEALER\G3\Services\UserService;

class User extends Components {
    #[\Override]
    protected function init(): void
    {
        add_filter('get_avatar', [$this, 'resetAvatar'], 1, 6);
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
        $userAvatar = UserService::getMeta($user_id, UserService::$metaKey, 'avatar', '');

        // fallback to default avatar
        $defaultAvatar = get_option('g3_option_general')['avatar'] ?? '';
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
}