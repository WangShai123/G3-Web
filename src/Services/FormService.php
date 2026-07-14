<?php
namespace JEALER\G3\Services;
use JEALER\G3\Core\Service\Service;
use JEALER\G3\Utilities\System;
use WP_Error;
use wpdb;

class FormService extends Service {
    const TABLE = 'g3_form';
    private string $table;
    const FORM_OPTION_KEY   = 'g3_option_form';
    const CACHE_GROUP       = 'g3_forms';
    const QUERY_CACHE_GROUP = 'g3_form_queries';
    private array $allowedFields = ['title', 'content', 'email', 'ext', 'status'];
    public function __construct()
    {
        parent::__construct();
        $this->table = $this->wpdb->prefix . self::TABLE;
    }

    public function create(array $data): int|WP_Error
    {
        // check: title, content, email
        if (!isset($data['title']) || !isset($data['content']) || !isset($data['email'])) {
            return new WP_Error('invalid_data', 'Invalid data: title, content, and email are required', 400);
        }
        // check: email
        $email = sanitize_email($data['email']);
        if (!is_email($email)) {
            return new WP_Error('invalid_email', 'Invalid email format', 400);
        }
        if (strlen($email) > 100) {
            return new WP_Error('email_too_long', 'Email is too long', 400);
        }
        // check: title
        $title = sanitize_text_field($data['title']);
        if (empty($title)) {
            return new WP_Error('invalid_title', 'Title cannot be empty after sanitization', 400);
        }
        if (strlen($title) > 255) {
            return new WP_Error('title_too_long', 'Title is too long (max 255 chars)', 400);
        }
        // check: content
        // $content = wp_kses_post($data['content']);
        $content = sanitize_textarea_field($data['content']);
        // TEXT/MEDIUMTEXT fields
        if (strlen($content) > 65535) {
            return new WP_Error('content_too_long', 'Content is too long', 400);
        }
        // check: ext
        $ext = null;
        if (isset($data['ext']) && is_array($data['ext'])) {
            // limit depth or size
            if (count($data['ext']) > 20) {
                return new WP_Error('ext_too_large', 'Ext data is too large', 400);
            }
            $ext = maybe_serialize($data['ext']);
        }
        // check: ip
        $ip = System::ip();
        if ($ip === false || empty($ip)) {
            return new WP_Error('illegal_request', 'Could not determine client IP', 400);
        }

        $insertData = [
            'title'      => $title,
            'content'    => $content,
            'email'      => $email,
            'ip'         => $ip,
            'created_at' => gmdate('Y-m-d H:i:s'),
            'status'     => 0,
        ];

        if ($ext !== null) {
            $insertData['ext'] = $ext;
        }

        $result = $this->wpdb->insert($this->table, $insertData);

        if ($result === false) {
            error_log('FormService create failed: ' . $this->wpdb->last_error);
            return new WP_Error('db_insert_error', 'Failed to save form data', 500);
        }

        $insertId = (int) $this->wpdb->insert_id;
        $this->setCache($insertId, array_merge(['id' => $insertId], $insertData));

        return $insertId;
    }

    public function getById(int $id): ?array
    {
        $cached = $this->getCache($id);
        if ($cached !== false) {
            return $cached;
        }

        $row = $this->wpdb->get_row(
            $this->wpdb->prepare("SELECT * FROM {$this->table} WHERE id = %d", $id),
            ARRAY_A
        );

        if (!$row) {
            return null;
        }

        if (isset($row['ext']) && !empty($row['ext'])) {
            $row['ext'] = maybe_unserialize($row['ext']);
        }

        $this->setCache($id, $row);

        return $row;
    }

    public function delete(array|int $ids): int|bool
    {
        $normalizedIds = $this->normalizeIds($ids);
        if (empty($normalizedIds)) {
            return false;
        }

        if (count($normalizedIds) > 1) {
            $idsList = implode(',', $normalizedIds);
            $result  = $this->wpdb->query("DELETE FROM {$this->table} WHERE id IN ({$idsList})");
        } else {
            $result = $this->wpdb->delete($this->table, ['id' => $normalizedIds[0]]);
        }

        if ($result !== false) {
            foreach ($normalizedIds as $id) {
                $this->deleteCache($id);
            }
        }

        return $result;
    }

    public function update(array $data, array|int $ids): bool
    {
        $updateData    = $this->sanitizeUpdateData($data);
        $normalizedIds = $this->normalizeIds($ids);

        if (empty($updateData) || empty($normalizedIds)) {
            return false;
        }

        $setParts      = [];
        $prepareValues = [];
        foreach ($updateData as $field => $value) {
            if ($field === 'status') {
                $setParts[]      = "{$field} = %d";
                $prepareValues[] = (int) $value;
            } else {
                $setParts[]      = "{$field} = %s";
                $prepareValues[] = (string) $value;
            }
        }

        $idPlaceholders = implode(',', array_fill(0, count($normalizedIds), '%d'));
        $sql            = "UPDATE {$this->table} SET " . implode(', ', $setParts) . " WHERE id IN ({$idPlaceholders})";
        $result         = $this->wpdb->query(
            $this->wpdb->prepare($sql, ...array_merge($prepareValues, $normalizedIds))
        );

        if ($result === false) {
            return false;
        }

        foreach ($normalizedIds as $id) {
            $this->deleteCache($id);
        }

        return true;
    }

    public function updateStatus(int $status, array|int $ids): bool
    {
        $normalizedIds = $this->normalizeIds($ids);
        if (empty($normalizedIds)) {
            return false;
        }

        $idPlaceholders = implode(',', array_fill(0, count($normalizedIds), '%d'));
        $sql            = "UPDATE {$this->table} SET status = %d WHERE id IN ({$idPlaceholders})";
        $result         = $this->wpdb->query(
            $this->wpdb->prepare($sql, ...array_merge([$status], $normalizedIds))
        );

        if ($result === false) {
            return false;
        }

        foreach ($normalizedIds as $id) {
            $this->deleteCache($id);
        }

        return true;
    }

    private function normalizeIds(array|int $ids): array
    {
        if (!is_array($ids)) {
            $id = (int) $ids;
            return $id > 0 ? [$id] : [];
        }

        $normalized = array_values(array_filter(array_map('intval', $ids), static function ($id) {
            return $id > 0;
        }));

        return array_values(array_unique($normalized));
    }

    private function sanitizeUpdateData(array $data): array
    {
        $sanitized = [];

        foreach ($data as $key => $value) {
            if (!in_array($key, $this->allowedFields, true)) {
                continue;
            }

            if ($key === 'title') {
                $value = sanitize_text_field((string) $value);
            } elseif ($key === 'content') {
                $value = sanitize_textarea_field((string) $value);
            } elseif ($key === 'email') {
                $value = sanitize_email((string) $value);
                if (!is_email($value)) {
                    continue;
                }
            } elseif ($key === 'ext' && is_array($value)) {
                $value = maybe_serialize($value);
            } elseif ($key === 'status') {
                $value = (string) ((int) $value);
            }

            $sanitized[$key] = (string) $value;
        }

        return $sanitized;
    }

    private function getCache(int $id): array|false
    {
        $cached = wp_cache_get($id, self::CACHE_GROUP);
        return $cached !== false ? $cached : false;
    }

    private function setCache(int $id, array $data): bool
    {
        return wp_cache_set($id, $data, self::CACHE_GROUP, USER_CACHE_TTL);
    }

    private function deleteCache(int $id): bool
    {
        return wp_cache_delete($id, self::CACHE_GROUP);
    }
}
