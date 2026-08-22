<?php
namespace JEALER\G3\Cache;
use JEALER\G3\Services\UserService;
use WP_Session_Tokens;

class SessionTokens extends WP_Session_Tokens {

    private function get_cache_key(): string
    {
        return $this->user_id;
    }

    /**
     * 复刻父类私有 hash_token 逻辑，解决私有方法不可访问报错
     * @param string $token 原始会话token
     * @return string sha256哈希字符串
     */
    private function hashToken(string $token): string
    {
        return hash('sha256', $token);
    }

    /**
     * 从 Redis 获取该用户全部会话（替代 get_user_meta DB 查询）
     */
    protected function get_sessions(): array
    {
        $key      = $this->get_cache_key();
        $sessions = wp_cache_get($key);

        if (false === $sessions) {
            return [];
        }

        return is_array($sessions) ? $sessions : [];
    }

    /**
     * 将会话数组写入 Redis（替代 update_user_meta DB 写入）
     */
    protected function update_sessions(array $sessions): void
    {
        $key = $this->get_cache_key();

        // 默认 一周过期
        $ttl = WEEK_IN_SECONDS;
        if (defined('G3_SESSION_TOKEN_TTL')) {
            $ttl = G3_SESSION_TOKEN_TTL;
        }

        wp_cache_set($key, $sessions, UserService::SESSION_TOKEN_CACHE_GROUP, $ttl);
    }

    /**
     * 清空当前用户所有会话
     */
    protected function destroy_all_sessions(): void
    {
        $key = $this->get_cache_key();
        wp_cache_delete($key, UserService::SESSION_TOKEN_CACHE_GROUP);
    }

    /**
     * 获取单条会话：get_session($verifier)
     * @param string $verifier hash后的token key
     * @return array|null
     */
    public function get_session($verifier): ?array
    {
        $sessions = $this->get_sessions();
        return $sessions[$verifier] ?? null;
    }

    /**
     * 更新单条会话：刷新过期、UA、IP
     * @param string $verifier
     * @param array $session
     */
    public function update_session($verifier, $session = null): void
    {
        $sessions            = $this->get_sessions();
        $sessions[$verifier] = $session;
        $this->update_sessions($sessions);
    }

    /**
     * 销毁除当前会话外所有设备会话
     * @param string $verifier 当前设备token key
     */
    public function destroy_other_sessions($verifier): void
    {
        $current_verifier = $this->hashToken($verifier);
        $all_sessions     = $this->get_sessions();

        // 只保留当前设备，其余全部删除
        $new_sessions = [];
        if (isset($all_sessions[$current_verifier])) {
            $new_sessions[$current_verifier] = $all_sessions[$current_verifier];
        }

        $this->update_sessions($new_sessions);
    }

    /**
     * 清理过期会话
     */
    public function cleanExpiredSessions(): void
    {
        $sessions = $this->get_sessions();
        $now      = time();
        $updated  = false;

        foreach ($sessions as $verifier => $session) {
            if ($session['expiration'] <= $now) {
                unset($sessions[$verifier]);
                $updated = true;
            }
        }

        if ($updated) {
            $this->update_sessions($sessions);
        }
    }
}
