<?php
namespace JEALER\G3\Services;

use Psr\Log\LoggerInterface;
use Psr\Log\LoggerTrait;
use Psr\Log\LogLevel;
use Stringable;

class LogService implements LoggerInterface {
    use LoggerTrait;

    public const DRIVER_FILE     = 'file';
    public const DRIVER_DATABASE = 'database';
    public const TABLE_NAME      = 'g3_logs';
    public const DEFAULT_MAX_SIZE = 10 * 1024 * 1024;

    private string $driver;
    private string $logDirectory;
    private int    $maxSize;
    private string $table;

    public function __construct(array|string $config = [])
    {
        global $wpdb;

        if (is_string($config)) {
            $config = ['driver' => $config];
        }

        $this->driver       = $this->normalizeDriver($config['driver'] ?? self::DRIVER_FILE);
        $this->logDirectory = $this->normalizeLogDirectory($config['directory'] ?? null);
        $this->maxSize      = max(1024, (int) ($config['max_size'] ?? self::DEFAULT_MAX_SIZE));
        $this->table        = ($wpdb->prefix ?? '') . self::TABLE_NAME;
    }

    public function log($level, string|Stringable $message, array $context = []): void
    {
        $entry = $this->buildEntry((string) $level, (string) $message, $context);

        if ($this->driver === self::DRIVER_DATABASE) {
            $this->writeDatabase($entry);
            return;
        }

        $this->writeFile($entry);
    }

    private function buildEntry(string $level, string $message, array $context): array
    {
        $context = $this->normalizeContext($context);
        $message = $this->interpolate($message, $context);

        return [
            'time'    => $this->now(),
            'level'   => $this->normalizeLevel($level),
            'message' => $message,
            'module'  => $context['module'] ?? null,
            'context' => $context,
        ];
    }

    private function writeFile(array $entry): void
    {
        $this->ensureLogDirectory();
        $file = $this->resolveWritableFile();

        $encoded = $this->encode($entry);
        if ($encoded === null) {
            error_log('[G3 LogService] Failed to encode log entry.');
            return;
        }

        $line = $encoded . PHP_EOL;

        if (@file_put_contents($file, $line, FILE_APPEND | LOCK_EX) === false) {
            error_log('[G3 LogService] Failed to write log file: ' . $file);
        }
    }

    private function writeDatabase(array $entry): void
    {
        global $wpdb;

        if (!$wpdb) {
            error_log('[G3 LogService] Database logger is unavailable: $wpdb is missing.');
            return;
        }

        $data = [
            'level'      => $this->sanitizeText($entry['level']),
            'message'    => $entry['message'],
            'context'    => $this->encode($entry['context']),
            'created_at' => $entry['time'],
        ];

        $wpdb->insert(
            $this->table,
            $data,
            ['%s', '%s', '%s', '%s']
        );
    }

    private function resolveWritableFile(): string
    {
        $date  = gmdate('Ymd');
        $index = 1;

        do {
            $suffix    = $index === 1 ? $date : $date . '-' . $index;
            $candidate = $this->logDirectory . DIRECTORY_SEPARATOR . $suffix . '.log';
            $index++;
        } while (is_file($candidate) && filesize($candidate) > $this->maxSize);

        return $candidate;
    }

    private function ensureLogDirectory(): void
    {
        if (!is_dir($this->logDirectory)) {
            if (function_exists('wp_mkdir_p')) {
                wp_mkdir_p($this->logDirectory);
            } else {
                mkdir($this->logDirectory, 0755, true);
            }
        }
    }

    private function interpolate(string $message, array $context = []): string
    {
        $replace = [];

        foreach ($context as $key => $value) {
            if (is_scalar($value) || $value === null || $value instanceof Stringable) {
                $replace['{' . $key . '}'] = (string) $value;
            }
        }

        return strtr($message, $replace);
    }

    private function normalizeContext(array $context): array
    {
        foreach ($context as $key => $value) {
            if ($value instanceof Stringable) {
                $context[$key] = (string) $value;
            } elseif ($value instanceof \Throwable) {
                $context[$key] = [
                    'class'   => $value::class,
                    'message' => $value->getMessage(),
                    'file'    => $value->getFile(),
                    'line'    => $value->getLine(),
                ];
            }
        }

        return $context;
    }

    private function normalizeDriver(string $driver): string
    {
        return match (strtolower($driver)) {
            self::DRIVER_DATABASE, 'db' => self::DRIVER_DATABASE,
            default                    => self::DRIVER_FILE,
        };
    }

    private function normalizeLevel(string $level): string
    {
        $level = strtolower($level);
        $valid = [
            LogLevel::EMERGENCY,
            LogLevel::ALERT,
            LogLevel::CRITICAL,
            LogLevel::ERROR,
            LogLevel::WARNING,
            LogLevel::NOTICE,
            LogLevel::INFO,
            LogLevel::DEBUG,
        ];

        return in_array($level, $valid, true) ? $level : LogLevel::INFO;
    }

    private function normalizeLogDirectory(?string $directory): string
    {
        if (is_string($directory) && trim($directory) !== '') {
            return rtrim($directory, DIRECTORY_SEPARATOR);
        }

        return $this->defaultLogDirectory();
    }

    private function defaultLogDirectory(): string
    {
        $contentDir = defined('WP_CONTENT_DIR')
            ? WP_CONTENT_DIR
            : dirname(__DIR__, 3);

        return $contentDir . DIRECTORY_SEPARATOR . 'G3-Web' . DIRECTORY_SEPARATOR . 'logs';
    }

    private function encode(array $entry): ?string
    {
        $encoded = function_exists('wp_json_encode')
            ? wp_json_encode($entry, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
            : json_encode($entry, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        return $encoded === false ? null : $encoded;
    }

    private function sanitizeText(string $value): string
    {
        return function_exists('sanitize_text_field')
            ? sanitize_text_field($value)
            : trim(strip_tags($value));
    }

    private function now(): string
    {
        return gmdate('Y-m-d H:i:s');
    }
}
