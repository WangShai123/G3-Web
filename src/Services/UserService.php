<?php
namespace JEALER\G3\Services;
use JEALER\G3\Components\Auth;
use JEALER\G3\Core\Service\Service;
use JEALER\G3\Services\MailerService;
use JEALER\G3\Utilities\Message;
use JEALER\G3\Utilities\Validator;
use JEALER\G3\Services\SystemService;
use WP_User;
use WP_Error;

class UserService extends Service {
    const META_KEY              = 'g3_user_meta';
    const ROLE_OPTION_KEY       = 'g3_option_roles';
    const GROUP_OPTION_KEY      = 'g3_option_groups';
    const MANAGER_OPTION_KEY    = 'g3_option_managers';
    const PREMIUM_OPTION_KEY    = 'g3_option_premiums';
    const MEMBERSHIP_OPTION_KEY = 'g3_option_memberships';
    const DURATION_OPTION_KEY   = 'g3_option_durations';
    const EXT_TABLE             = 'g3_user_extra';
    const CARD_TABLE            = 'g3_user_card';
    const EXTRA_CACHE_GROUP     = 'g3_user_extra';
    const CARD_CACHE_GROUP      = 'g3_user_card';
    const QUERY_CACHE_GROUP     = 'g3_user_query';
    const G3_LANG_COOKIE        = 'g3_user_language';
    const G3_TIMEZONE_COOKIE    = 'g3_user_timezone';
    const USER_COOKIE           = 'g3_user';
    const VISITOR_COOKIE        = 'g3_visitor_id';
    const VISITOR_SCRIPT_ID     = 'g3-visitor-finger-config';
    const RESET_PASSWORD_TTL    = 3600;
    private ?WP_User       $user        = null;
    private array          $extra       = [];
    private array          $card        = [];
    public string          $extTable;
    public string          $cardTable;
    private ?MailerService $mailService = null;

    public function __construct()
    {
        parent::__construct();
        $this->extTable    = $this->wpdb->prefix . self::EXT_TABLE;
        $this->cardTable   = $this->wpdb->prefix . self::CARD_TABLE;
        $this->mailService = $this->container->get(MailerService::class);
    }

    public function init(int|WP_User|null $userId = null): ?UserService
    {
        if ($userId === null) {
            $userId = get_current_user_id();
        }
        $user = $userId instanceof WP_User ? $userId : get_user($userId);

        if ($user instanceof WP_User) {
            $this->user  = $user;
            $this->extra = $this->getExtra($userId);
            $this->card  = $this->getCard($userId);
            $this->cache = array_merge($this->normalizeUserData((array) $this->user), $this->extra, $this->card);
            return $this;
        }
        return null;
    }

    public function getExtra(int|WP_User $id): array|WP_Error
    {
        $userId = $this->userId($id);
        if ($userId <= 0) return [];

        $cached = wp_cache_get($userId, self::EXTRA_CACHE_GROUP);
        if (is_array($cached)) return $cached;

        $row   = $this->wpdb->get_row(
            $this->wpdb->prepare("SELECT * FROM `{$this->extTable}` WHERE `user_id` = %d", $userId),
            ARRAY_A
        );
        $extra = $this->normalizeExtra(is_array($row) ? $row : ['user_id' => $userId]);
        // cache for 1 week
        $result = wp_cache_set($userId, $extra, self::EXTRA_CACHE_GROUP, WEEK_IN_SECONDS);
        if (false === $result) {
            return new WP_Error('cache_failed', 'cache failed for user extra data', [
                'status'  => 500,
                'user_id' => $userId,
            ]);
        }
        return $extra;
    }
    public function getCard(int|WP_User $id): array|WP_Error
    {
        $userId = $this->userId($id);
        if ($userId <= 0) return [];

        $cached = wp_cache_get($userId, self::CARD_CACHE_GROUP);
        if (is_array($cached)) return $cached;

        $row  = $this->wpdb->get_row(
            $this->wpdb->prepare("SELECT * FROM `{$this->cardTable}` WHERE `user_id` = %d", $userId),
            ARRAY_A
        );
        $card = $this->normalizeCard(is_array($row) ? $row : ['user_id' => $userId]);
        // cache for 1 week
        $result = wp_cache_set($userId, $card, self::CARD_CACHE_GROUP, WEEK_IN_SECONDS);
        if (false === $result) {
            return new WP_Error('cache_failed', 'cache failed for user card data', [
                'status'  => 500,
                'user_id' => $userId,
            ]);
        }
        return $card;
    }
    private function normalizeExtra(array $row): array
    {
        return [
            'user_id'         => (int) ($row['user_id'] ?? 0),
            'role'            => (string) ($row['role'] ?? ''),
            'group'           => (string) ($row['group'] ?? ''),
            'premium'         => (string) ($row['premium'] ?? ''),
            'visit'           => (int) ($row['visit'] ?? 0),
            'third_party_ids' => (array) ($row['third_party_ids'] ?? [])
        ];
    }
    private function normalizeCard(array $row): array
    {
        return [
            'user_id'        => (int) ($row['user_id'] ?? 0),
            'avatar'         => (string) ($row['avatar'] ?? ''),
            'description'    => (string) ($row['description'] ?? ''),
            'gender'         => (int) ($row['gender'] ?? 0),
            'phone'          => (string) ($row['phone'] ?? ''),
            'country'        => (string) ($row['country'] ?? ''),
            'province'       => (string) ($row['province'] ?? ''),
            'city'           => (string) ($row['city'] ?? ''),
            'district'       => (string) ($row['district'] ?? ''),
            'address'        => (string) ($row['address'] ?? ''),
            'social_account' => (array) ($row['social_account'] ?? []),
        ];
    }
    private function normalizeUserData(array $userData, ?array $unset = null): array
    {
        // @todo
        $userlessKeys = [
            ''
        ];

        return [];
    }
    private function userId(int|WP_User $id): int
    {
        if ($id instanceof WP_User) {
            return (int) $id->ID;
        }

        return is_int($id) ? $id : 0;
    }

    /**
     * Get user meta data
     * 
     * 获取用户元数据
     * @deprecated
     * @todo 待删除
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
     * @deprecated
     * @todo 待删除
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
     * @deprecated
     * @todo 待删除
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

    public static function resetPasswordUrl(WP_User $user, string $key): string
    {
        if (!Auth::onAuthOverride()) {
            return add_query_arg(
                [
                    'action' => 'rp',
                    'key'    => $key,
                    'login'  => $user->user_login,
                ],
                network_site_url('wp-login.php', 'login')
            );
        }

        return add_query_arg(
            [
                'key'   => $key,
                'login' => $user->user_login,
            ],
            home_url('/user/reset-password/')
        );
    }

    public static function lostPasswordUrl(): string
    {
        return Auth::onAuthOverride() ? home_url('/user/lost-password/') : wp_lostpassword_url();
    }

    public static function loginUrl(string $redirect = '', bool $forceReauth = false): string
    {
        if (!Auth::onAuthOverride()) {
            return wp_login_url($redirect, $forceReauth);
        }

        $args = [];
        if ($redirect !== '') {
            $args['redirect_to'] = $redirect;
        }
        if ($forceReauth) {
            $args['reauth'] = '1';
        }

        return add_query_arg($args, home_url('/user/login/'));
    }

    public static function registerUrl(): string
    {
        return Auth::onAuthOverride() ? home_url('/user/register/') : wp_registration_url();
    }

    public static function passwordResetExpiration(int $expiration): int
    {
        return Auth::onAuthOverride() ? self::RESET_PASSWORD_TTL : $expiration;
    }

    public static function authTemplatePath(mixed $value = null): ?string
    {
        if (!Auth::onAuthOverride() || !is_string($value)) {
            return null;
        }

        return match ($value) {
            'register'       => 'user/register.php',
            'login'          => 'user/login.php',
            'lost-password'  => 'user/lost-password.php',
            'reset-password' => 'user/reset-password.php',
            default          => null,
        };
    }

    public function lostPasswordContext(): array
    {
        $context = [
            'success' => false,
            'message' => '',
        ];

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            return $context;
        }

        $nonce = $this->requestString('_wpnonce');
        if (!wp_verify_nonce($nonce, 'g3_lost_password')) {
            $context['message'] = Message::illegalRequest();
            return $context;
        }

        $login = $this->requestString('user_login');
        if ($login === '') {
            $context['message'] = __('<strong>Error:</strong> Please enter a username or email address.');
            return $context;
        }

        $user = is_email($login) ? get_user_by('email', $login) : get_user_by('login', $login);
        if ($user instanceof WP_User) {
            $this->sendPasswordResetLink($user);
        }

        $context['success'] = true;
        $context['message'] = __('If the account exists, a password reset email has been sent.', 'G3');

        return $context;
    }

    public function resetPasswordContext(): array
    {
        $login = $this->requestString('login');
        $key   = $this->requestString('key');

        $context = [
            'valid'    => false,
            'success'  => false,
            'message'  => '',
            'login'    => $login,
            'key'      => $key,
            'loginUrl' => self::loginUrl(),
        ];

        if ($login === '' || $key === '') {
            $context['message'] = MailerService::resetLinkExpiredMsg();
            return $context;
        }

        $user = check_password_reset_key($key, $login);
        if ($user instanceof WP_Error) {
            $context['message'] = $this->passwordResetErrorMessage($user);
            return $context;
        }

        if (!$user instanceof WP_User) {
            $context['message'] = MailerService::resetLinkExpiredMsg();
            return $context;
        }

        $context['valid'] = true;
        $context['user']  = $user;

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            return $context;
        }

        $nonce = $this->requestString('_wpnonce');
        if (!wp_verify_nonce($nonce, 'g3_reset_password')) {
            $context['valid']   = false;
            $context['message'] = Message::illegalRequest();
            return $context;
        }

        $password = $this->requestPassword('password');
        $confirm  = $this->requestPassword('password_confirm');

        if (strlen($password) < 12) {
            $context['message'] = __('Hint: The password should be at least twelve characters long. To make it stronger, use upper and lower case letters, numbers, and symbols like ! " ? $ % ^ &amp; ).');
            return $context;
        }

        if ($password !== $confirm) {
            $context['message'] = __('<strong>Error:</strong> The passwords do not match.');
            return $context;
        }

        reset_password($user, $password);

        $context['success'] = true;
        $context['message'] = __('Your password has been reset.');

        return $context;
    }

    private function requestString(string $key): string
    {
        $value = $_REQUEST[$key] ?? '';
        if (is_array($value)) {
            return '';
        }

        return sanitize_text_field(wp_unslash((string) $value));
    }

    private function requestPassword(string $key): string
    {
        $value = $_POST[$key] ?? '';
        if (is_array($value)) {
            return '';
        }

        return (string) wp_unslash($value);
    }

    private function passwordResetErrorMessage(WP_Error $error): string
    {
        return match ($error->get_error_code()) {
            'expired_key' => __('<strong>Error:</strong> Your password reset link has expired. Please request a new link below.'),
            'invalid_key' => MailerService::resetLinkExpiredMsg(),
            default       => $error->get_error_message() ?: MailerService::resetLinkExpiredMsg(),
        };
    }

    private function sendPasswordResetLink(WP_User $user): void
    {
        $key = get_password_reset_key($user);
        if ($key instanceof WP_Error) {
            return;
        }

        $resetUrl = self::resetPasswordUrl($user, $key);
        $mail     = MailerService::renderTemplate('reset-password', [
            'user'              => $user,
            'userLogin'         => $user->user_login,
            'resetUrl'          => $resetUrl,
            'expirationSeconds' => self::RESET_PASSWORD_TTL,
            'expirationMinutes' => (int) ceil(self::RESET_PASSWORD_TTL / 60),
        ], [
            'to'      => $user->user_email,
            'subject' => wp_specialchars_decode(get_bloginfo('name'), ENT_QUOTES) . ' ' . __('Reset Password'),
            'message' => sprintf(
                "%s\n\n%s",
                __('To reset your password, visit the following address:'),
                $resetUrl
            ),
            'headers' => ['Content-Type: text/html; charset=UTF-8'],
        ]);

        if ($mail === null) {
            return;
        }

        $this->mailService->sendMail(
            $user->user_email,
            (string) $mail['subject'],
            (string) $mail['message'],
            [],
            $mail['headers']
        );
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
            100     => __('Unknown', 'G3'),
            101     => __('Pending'),
            default => __('Active')
        };
    }
}
