<?php
namespace JEALER\G3\Services;
use JEALER\G3\Utilities\System;
use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;
use Psr\Log\InvalidArgumentException;
use Stringable;
use WP_Error;

class LogService implements LoggerInterface {

    /**
     * Log table name
     * 
     * 日志表名
     * 
     * @var string
     */
    private string $table;

    /**
     * Cache group name
     * 
     * 缓存组名
     * 
     * @var string
     */
    const CACHE_GROUP = 'g3_logs';

    /**
     * Log table name
     * 
     * 日志表名
     * 
     * @var string
     */
    const TABLE_NAME = 'g3_logs';

    public function __construct()
    {
        global $wpdb;
        $this->table = $wpdb->prefix . self::TABLE_NAME;
    }

    /**
     * System is unusable.
     *
     * @param string|Stringable $message
     * @param array $context
     *
     * @return void
     *
     * @throws InvalidArgumentException
     */
    public function emergency(string|Stringable $message, array $context = []): void
    {
        $this->log(LogLevel::EMERGENCY, $message, $context);
    }

    /**
     * Action must be taken immediately.
     *
     * Example: Entire website down, database unavailable, etc. This should
     * trigger the SMS alerts and wake you up.
     *
     * @param string|Stringable $message
     * @param array $context
     *
     * @return void
     *
     * @throws InvalidArgumentException
     */
    public function alert(string|Stringable $message, array $context = []): void
    {
        $this->log(LogLevel::ALERT, $message, $context);
    }

    /**
     * Critical conditions.
     *
     * Example: Application component unavailable, unexpected exception.
     *
     * @param string|Stringable $message
     * @param array $context
     *
     * @return void
     *
     * @throws InvalidArgumentException
     */
    public function critical(string|Stringable $message, array $context = []): void
    {
        $this->log(LogLevel::CRITICAL, $message, $context);
    }

    /**
     * Runtime errors that do not require immediate action but should typically
     * be logged and monitored.
     *
     * @param string|Stringable $message
     * @param array $context
     *
     * @return void
     *
     * @throws InvalidArgumentException
     */
    public function error(string|Stringable $message, array $context = []): void
    {
        $this->log(LogLevel::ERROR, $message, $context);
    }

    /**
     * Exceptional occurrences that are not errors.
     *
     * Example: Use of deprecated APIs, poor use of an API, undesirable things
     * that are not necessarily wrong.
     *
     * @param string|Stringable $message
     * @param array $context
     *
     * @return void
     *
     * @throws InvalidArgumentException
     */
    public function warning(string|Stringable $message, array $context = []): void
    {
        $this->log(LogLevel::WARNING, $message, $context);
    }

    /**
     * Normal but significant events.
     *
     * @param string|Stringable $message
     * @param array $context
     *
     * @return void
     *
     * @throws InvalidArgumentException
     */
    public function notice(string|Stringable $message, array $context = []): void
    {
        $this->log(LogLevel::NOTICE, $message, $context);
    }

    /**
     * Interesting events.
     *
     * Example: User logs in, SQL logs.
     *
     * @param string|Stringable $message
     * @param array $context
     *
     * @return void
     *
     * @throws InvalidArgumentException
     */
    public function info(string|Stringable $message, array $context = []): void
    {
        $this->log(LogLevel::INFO, $message, $context);
    }

    /**
     * Detailed debug information.
     *
     * @param string|Stringable $message
     * @param array $context
     *
     * @return void
     *
     * @throws InvalidArgumentException
     */
    public function debug(string|Stringable $message, array $context = []): void
    {
        $this->log(LogLevel::DEBUG, $message, $context);
    }

    /**
     * Logs with an arbitrary level.
     *
     * @param mixed $level
     * @param string|Stringable $message
     * @param array $context
     *
     * @return void
     *
     * @throws InvalidArgumentException
     */
    public function log($level, string|Stringable $message, array $context = []): void
    {
        $message = (string) $message;

        // Interpolate context values into message placeholders
        $interpolatedMessage = $this->interpolate($message, $context);

        // Get request information if available
        $request = $this->getRequestInfo();

        $meta = $context;
        unset($meta['module'], $meta['user_id'], $meta['ip']);

        // Create log entry
        $this->create(
            'system',
            $interpolatedMessage,
            $level,
            $context['module'] ?? null,
            $context['user_id'] ?? null,
            $context['ip'] ?? null,
            $meta,
            $request
        );
    }

    /**
     * Interpolates context values into the message placeholders.
     *
     * @param string $message
     * @param array $context
     * @return string
     */
    private function interpolate(string $message, array $context = []): string
    {
        // Build a replacement array with braces around the context keys
        $replace = [];
        foreach ($context as $key => $val) {
            // Check if the placeholder is a string or array
            if (!is_array($val) && (!is_object($val) || method_exists($val, '__toString'))) {
                $replace['{' . $key . '}'] = $val;
            }
        }

        // Interpolate replacement values into the message and return
        return strtr($message, $replace);
    }

    /**
     * Get request information
     * 
     * 获取请求信息
     * 
     * @return array
     */
    private function getRequestInfo(): array
    {
        $request = [
            'method'       => $_SERVER['REQUEST_METHOD'] ?? '',
            'uri'          => $_SERVER['REQUEST_URI'] ?? '',
            'query_string' => $_SERVER['QUERY_STRING'] ?? '',
            'ip'           => System::ip(),
            'user_agent'   => $_SERVER['HTTP_USER_AGENT'] ?? '',
            'referer'      => $_SERVER['HTTP_REFERER'] ?? '',
            'timestamp'    => current_time('mysql'),
        ];

        return $request;
    }

    /**
     * Create a new log entry
     * 
     * 创建新的日志条目
     * 
     * @param string $type Log type
     * @param string $message Log message
     * @param string $level Log level
     * @param string|null $module Business module
     * @param int|null $userId User ID
     * @param string|null $ipAddress Client IP address
     * @param array $meta Additional metadata
     * @param array $request Request information
     * @return int|WP_Error Log ID on success, WP_Error on failure
     */
    public function create(
        string $type,
        string $message,
        string $level = 'info',
        ?string $module = null,
        ?int $userId = null,
        ?string $ipAddress = null,
        array $meta = [],
        array $request = []
    ): int|WP_Error
    {
        global $wpdb;

        // Validate log level
        $validLevels = [
            LogLevel::EMERGENCY,
            LogLevel::ALERT,
            LogLevel::CRITICAL,
            LogLevel::ERROR,
            LogLevel::WARNING,
            LogLevel::NOTICE,
            LogLevel::INFO,
            LogLevel::DEBUG
        ];

        if (!in_array(strtolower($level), $validLevels)) {
            $level = 'info';
        }

        // Prepare data
        $data = [
            'type'       => sanitize_text_field($type),
            'level'      => sanitize_text_field($level),
            'message'    => $message,
            'created_at' => current_time('mysql'),
        ];

        // Add optional fields
        if ($module !== null) {
            $data['module'] = sanitize_text_field($module);
        }

        if ($userId !== null) {
            $data['user_id'] = absint($userId);
        }

        if ($ipAddress !== null) {
            $data['ip_address'] = sanitize_text_field(substr($ipAddress, 0, 45));
        }

        // Add meta data if provided
        if (!empty($meta)) {
            $data['meta'] = maybe_serialize($meta);
        }

        // Add request data if provided
        if (!empty($request)) {
            $data['request'] = maybe_serialize($request);
        }

        // Determine column format
        $formats = ['%s', '%s', '%s', '%s']; // type, level, message, created_at
        if ($module !== null) $formats[] = '%s'; // module
        if ($userId !== null) $formats[] = '%d'; // user_id
        if ($ipAddress !== null) $formats[] = '%s'; // ip_address
        if (!empty($meta)) $formats[] = '%s'; // meta
        if (!empty($request)) $formats[] = '%s'; // request

        // Insert into database
        $result = $wpdb->insert($this->table, $data, $formats);

        if ($result === false) {
            return new WP_Error(
                'log_creation_failed',
                'Failed to create log entry',
                ['error' => $wpdb->last_error]
            );
        }

        $logId = $wpdb->insert_id;

        // Cache the newly created log
        $this->setCache($logId, [
            'id'         => $logId,
            'type'       => $type,
            'level'      => $level,
            'module'     => $module,
            'user_id'    => $userId,
            'ip_address' => $ipAddress,
            'message'    => $message,
            'meta'       => $meta,
            'request'    => $request,
            'created_at' => $data['created_at'],
        ]);

        return $logId;
    }

    /**
     * Get a log by ID
     * 
     * 根据ID获取日志
     * 
     * @param int $logId Log ID
     * @return array|false Log data or false if not found
     */
    public function get(int $logId): array|false
    {
        // First, try to get from cache
        $cachedLog = $this->getCache($logId);
        if ($cachedLog !== false) {
            return $cachedLog;
        }

        global $wpdb;

        // If not in cache, query the database
        $log = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT id, type, level, message, meta, request, created_at FROM " . $this->table . " WHERE id = %d",
                $logId
            ),
            ARRAY_A
        );

        if ($log) {
            // Unserialize meta if exists
            if (isset($log['meta']) && !empty($log['meta'])) {
                $log['meta'] = maybe_unserialize($log['meta']);
            } else {
                $log['meta'] = [];
            }

            // Unserialize request if exists
            if (isset($log['request']) && !empty($log['request'])) {
                $log['request'] = maybe_unserialize($log['request']);
            } else {
                $log['request'] = [];
            }

            // Cache the result
            $this->setCache($logId, $log);

            return $log;
        }

        return false;
    }

    /**
     * Update a log entry
     * 
     * 更新日志条目
     * 
     * @param int $logId Log ID
     * @param array $data Data to update
     * @return bool|WP_Error True on success, WP_Error on failure
     */
    public function update(int $logId, array $data): bool|WP_Error
    {
        global $wpdb;

        // Prepare update data
        $updateData = [];
        $format     = [];

        if (isset($data['type'])) {
            $updateData['type'] = sanitize_text_field($data['type']);
            $format[]           = '%s';
        }

        if (isset($data['level'])) {
            $validLevels = [
                LogLevel::EMERGENCY,
                LogLevel::ALERT,
                LogLevel::CRITICAL,
                LogLevel::ERROR,
                LogLevel::WARNING,
                LogLevel::NOTICE,
                LogLevel::INFO,
                LogLevel::DEBUG
            ];
            if (in_array(strtolower($data['level']), $validLevels)) {
                $updateData['level'] = sanitize_text_field($data['level']);
                $format[]            = '%s';
            }
        }

        if (isset($data['message'])) {
            $updateData['message'] = $data['message'];
            $format[]              = '%s';
        }

        if (isset($data['meta'])) {
            $updateData['meta'] = maybe_serialize($data['meta']);
            $format[]           = '%s';
        }

        if (isset($data['request'])) {
            $updateData['request'] = maybe_serialize($data['request']);
            $format[]              = '%s';
        }

        if (empty($updateData)) {
            return new WP_Error(
                'no_data_to_update',
                'No data provided for update'
            );
        }

        // Add updated timestamp if needed
        if (!isset($updateData['updated_at'])) {
            $updateData['updated_at'] = current_time('mysql');
            $format[]                 = '%s';
        }

        // Perform update
        $where        = ['id' => $logId];
        $where_format = ['%d'];

        $result = $wpdb->update(
            $this->table,
            $updateData,
            $where,
            $format,
            $where_format
        );

        if ($result === false) {
            return new WP_Error(
                'log_update_failed',
                'Failed to update log entry',
                ['error' => $wpdb->last_error]
            );
        }

        // Clear cache for this log
        $this->deleteCache($logId);

        return true;
    }

    /**
     * Delete a log entry
     * 
     * 删除日志条目
     * 
     * @param int $logId Log ID
     * @return bool|WP_Error True on success, WP_Error on failure
     */
    public function delete(int $logId): bool|WP_Error
    {
        global $wpdb;

        // Delete from database
        $result = $wpdb->delete(
            $this->table,
            ['id' => $logId],
            ['%d']
        );

        if ($result === false) {
            return new WP_Error(
                'log_delete_failed',
                'Failed to delete log entry',
                ['error' => $wpdb->last_error]
            );
        }

        // Clear cache for this log
        $this->deleteCache($logId);

        return true;
    }

    /**
     * Enhanced query method with additional filter options
     * 
     * 增强查询方法，支持更多过滤选项
     * 
     * @param array $filters Filters to apply
     * @param int $limit Number of results per page
     * @param int $offset Offset for pagination
     * @param string $orderBy Column to order by
     * @param string $order Direction of order
     * @return array Array of logs and total count
     */
    public function query(array $filters = [], int $limit = 20, int $offset = 0, string $orderBy = 'id', string $order = 'DESC'): array
    {
        global $wpdb;

        // Build WHERE clause
        $whereClause = 'WHERE 1=1';
        $params      = [];

        if (!empty($filters['type'])) {
            $whereClause .= ' AND type = %s';
            $params[]     = $filters['type'];
        }

        if (!empty($filters['level'])) {
            $whereClause .= ' AND level = %s';
            $params[]     = $filters['level'];
        }

        if (!empty($filters['module'])) {
            $whereClause .= ' AND module = %s';
            $params[]     = $filters['module'];
        }

        if (!empty($filters['user_id'])) {
            $whereClause .= ' AND user_id = %d';
            $params[]     = $filters['user_id'];
        }

        if (!empty($filters['ip_address'])) {
            $whereClause .= ' AND ip_address = %s';
            $params[]     = $filters['ip_address'];
        }

        if (!empty($filters['date_from'])) {
            $whereClause .= ' AND created_at >= %s';
            $params[]     = $filters['date_from'];
        }

        if (!empty($filters['date_to'])) {
            $whereClause .= ' AND created_at <= %s';
            $params[]     = $filters['date_to'];
        }

        if (!empty($filters['search'])) {
            $whereClause .= ' AND message LIKE %s';
            $params[]     = '%' . $wpdb->esc_like($filters['search']) . '%';
        }

        // Validate order by
        $allowedOrderBy = ['id', 'type', 'level', 'module', 'user_id', 'ip_address', 'created_at'];
        if (!in_array($orderBy, $allowedOrderBy)) {
            $orderBy = 'id';
        }

        // Validate order direction
        $order = strtoupper($order);
        if (!in_array($order, ['ASC', 'DESC'])) {
            $order = 'DESC';
        }

        // Get total count
        $countQuery = "SELECT COUNT(*) FROM " . $this->table . " " . $whereClause;
        if (!empty($params)) {
            $countQuery = $wpdb->prepare($countQuery, $params);
        }
        $totalCount = $wpdb->get_var($countQuery);

        // Get logs
        $query    = "SELECT id, type, level, module, user_id, ip_address, message, meta, request, created_at FROM " . $this->table . " " . $whereClause . " ORDER BY " . $orderBy . " " . $order . " LIMIT %d OFFSET %d";
        $params[] = $limit;
        $params[] = $offset;

        $preparedQuery = $wpdb->prepare($query, $params);
        $logs          = $wpdb->get_results($preparedQuery, ARRAY_A);

        // Process logs and add to cache
        foreach ($logs as &$log) {
            // Unserialize meta if exists
            if (isset($log['meta']) && !empty($log['meta'])) {
                $log['meta'] = maybe_unserialize($log['meta']);
            } else {
                $log['meta'] = [];
            }

            // Unserialize request if exists
            if (isset($log['request']) && !empty($log['request'])) {
                $log['request'] = maybe_unserialize($log['request']);
            } else {
                $log['request'] = [];
            }

            // Cache each log individually
            $this->setCache($log['id'], $log);
        }

        return [
            'logs'  => $logs,
            'total' => (int) $totalCount,
            'pages' => ceil($totalCount / $limit),
        ];
    }

    /**
     * Get cache for a specific log
     * 
     * 获取特定日志的缓存
     * 
     * @param int $logId Log ID
     * @return array|false Cached log data or false if not found
     */
    private function getCache(int $logId): array|false
    {
        $cached = wp_cache_get($logId, self::CACHE_GROUP);

        return $cached !== false ? $cached : false;
    }

    /**
     * Set cache for a specific log
     * 
     * 设置特定日志的缓存
     * 
     * @param int $logId Log ID
     * @param array $data Log data
     * @return bool True on success
     */
    private function setCache(int $logId, array $data): bool
    {
        return wp_cache_set($logId, $data, self::CACHE_GROUP, HOUR_IN_SECONDS);
    }

    /**
     * Delete cache for a specific log
     * 
     * 删除特定日志的缓存
     * 
     * @param int $logId Log ID
     * @return bool True on success
     */
    private function deleteCache(int $logId): bool
    {
        return wp_cache_delete($logId, self::CACHE_GROUP);
    }

    /**
     * Clear all log caches
     * 
     * 清除所有日志缓存
     * 
     * @return bool True on success
     */
    public function clearAllCaches(): bool
    {
        return wp_cache_flush_group(self::CACHE_GROUP);
    }

    /**
     * Clean old logs based on retention policy
     * 
     * 根据保留策略清理旧日志
     * 
     * @param int $days Number of days to retain logs
     * @return int|WP_Error Number of deleted logs or WP_Error on failure
     */
    public function cleanOldLogs(int $days = 30): int|WP_Error
    {
        global $wpdb;

        $dateThreshold = date('Y-m-d H:i:s', strtotime("-{$days} days"));

        $result = $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM " . $this->table . " WHERE created_at < %s",
                $dateThreshold
            )
        );

        if ($result === false) {
            return new WP_Error(
                'log_cleanup_failed',
                'Failed to clean old logs',
                ['error' => $wpdb->last_error]
            );
        }

        // Clear all caches after cleanup
        $this->clearAllCaches();

        return (int) $result;
    }
}
