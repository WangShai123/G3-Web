<?php
namespace JEALER\G3\Services;
use JEALER\G3\Utilities\Validator;
use JEALER\G3\Services\SystemService;
use WP_User;

/**
 * User Service
 * 
 * 用户服务
 * 
 * @since 1.0.0
 * @author Wang Shai
 */
class UserService {

    /**
     * Meta key
     * 
     * 元数据键
     * 
     * @var string
     */
    const META_KEY = 'g3_user_meta';

    const ROLE_OPTION_KEY    = 'g3_option_roles';
    const GROUP_OPTION_KEY   = 'g3_option_groups';
    const MANAGER_OPTION_KEY = 'g3_option_managers';
    const PREMIUM_OPTION_KEY = 'g3_option_premiums';

    const MEMBERSHIP_OPTION_KEY = 'g3_option_memberships';
    const DURATION_OPTION_KEY   = 'g3_option_durations';

    /**
     * Get user meta data
     * 
     * 获取用户元数据
     * 
     * @param int|object $id userId / WP_User
     * @param string $metaKey meta key
     * @param string $arrayKey array key if meta value is array
     * @param mixed $default default value if meta not exists
     * @return mixed meta value
     */
    public static function getMeta(int|object $id, string $metaKey, string $arrayKey = '', mixed $default = null): mixed
    {
        if (\is_object($id)) {
            if ($id instanceof WP_User) {
                $id = $id->ID;
            } else {
                return $default;
            }
        }

        $metaValue = get_user_meta($id, $metaKey, true);

        if (is_array($metaValue) && $arrayKey !== null && isset($metaValue[$arrayKey])) {
            return $metaValue[$arrayKey];
        }

        return ($metaValue !== '' && $metaValue !== null) ? $metaValue : $default;
    }

    /**
     * Get Default Avatar URL
     * 
     * 获取默认头像 URL
     *
     * @return string avatar url
     */
    public static function getDefaultAvatarUrl(): string
    {
        $default = get_option(SystemService::OPTION_KEY)['avatar'] ?? '';
        return Validator::isImage($default) ? $default : G3_IMG_URL . '/avatar.png';
    }

    /**
     * Get Avatar URL
     * 
     * 获取用户头像 URL
     *
     * @param int $userId user id
     * @return string avatar url
     */
    public static function getAvatarUrl($userId = 0): string
    {
        if (!$userId) {
            $userId = get_current_user_id();
        } else {
            $userId = absint($userId);
        }

        $userAvatar = self::getMeta($userId, self::META_KEY, 'avatar', []);

        if (!$userAvatar || !Validator::isImage($userAvatar)) {
            $userAvatar = self::getDefaultAvatarUrl();
        }

        return $userAvatar;
    }

    /**
     * Render User Status
     * 
     * 渲染用户状态
     * 
     * @param int $statusId status id
     * @return string status text
     */
    public static function renderStatus(int $statusId): string
    {
        return match ($statusId) {
            100 => __('Unknown', 'G3'),
            101 => __('Pending'),
            default => __('Active')
        };
    }
}
