<?php
namespace JEALER\G3\Services;
use JEALER\G3\Utilities\Validator;
use JEALER\G3\Services\SystemService;
use WP_User;
class UserService {

    /**
     * Meta key
     * 
     * 元数据键
     * 
     * @var string
     * @since 1.0.0
     * @author Wang Shai
     */
    public const META_KEY = 'g3_user_meta';

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
     * @since 1.0.0
     * @author Wang Shai
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
     * @since 1.0.0
     * @author Wang Shai
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
     * @since 1.0.0
     * @author Wang Shai
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

    public static function renderStatus(int $statusId)
    {
        match ($statusId) {
            100 => __('Unknown', 'G3'),
            101 => __('Pending'),
            default => __('Actived')
        };
    }

}