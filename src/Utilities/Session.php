<?php

namespace JEALER\G3\Utilities;

use WP_Session_Tokens;

/**
 * Session Utilities
 * 
 * 会话工具类
 * 
 * @since 1.0.0
 * @author Wang Shai
 */
final class Session {

    /**
     * Get current session
     * 
     * 获取当前会话
     * 
     * @return array | null Current session data or null if no session exists
     */
    public static function current(): array|null
    {
        $session = WP_Session_Tokens::get_instance(get_current_user_id());
        return $session->get(wp_get_session_token());
    }
}
