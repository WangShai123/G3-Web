<?php
namespace JEALER\G3\Utilities;
use InvalidArgumentException;

final class Date {

    /**
     * Get human readable time string
     *
     * 获取人类可读的时间字符串
     *
     * @param int $timestamp
     * @return string
     */
    public static function humanTime(int $timestamp = 0): string
    {
        if (!$timestamp) {
            // Default: Post publish time
            $timestamp = get_the_time('U');
        }

        $timeDiff = current_time('timestamp') - $timestamp;

        return match (true) {
            $timeDiff < 60      => \sprintf(
                _n('%s second ago', '%s seconds ago', $timeDiff, 'G3'),
                $timeDiff
            ),
            $timeDiff < 3600    => \sprintf(
                _n('%s minute ago', '%s minutes ago', intdiv($timeDiff, 60), 'G3'),
                intdiv($timeDiff, 60)
            ),
            $timeDiff < 86400   => \sprintf(
                _n('%s hour ago', '%s hours ago', intdiv($timeDiff, 3600), 'G3'),
                intdiv($timeDiff, 3600)
            ),
            $timeDiff < 2592000 => \sprintf(
                _n('%s day ago', '%s days ago', intdiv($timeDiff, 86400), 'G3'),
                intdiv($timeDiff, 86400)
            ),
            default             => wp_date(get_option('date_format'), $timestamp),
        };
    }

    /**
     * Get Localized Date and Time based on WordPress settings
     *
     * 按照 WordPress 设置的日期和时间格式返回当前时区的日期和时间字符串
     *
     * @param int $timestamp
     * @return string|bool
     */
    public static function dateTime(int $timestamp): bool|string
    {
        return wp_date(get_option('date_format') . ' ' . get_option('time_format'), $timestamp);
    }

    /**
     * Get Localized Date based on WordPress settings
     *
     * 按照 WordPress 设置的日期格式返回当前时区的日期字符串
     *
     * @param int $timestamp
     * @return string|bool
     */
    public static function date(int $timestamp): bool|string
    {
        return wp_date(get_option('date_format'), $timestamp);
    }

    /**
     * Get Localized Time based on WordPress settings
     *
     * 按照 WordPress 设置的时间格式返回当前时区的时间字符串
     *
     * @param int $timestamp timestamp
     * @return string|bool time string or false on error
     */
    public static function time(int $timestamp): bool|string
    {
        return wp_date(get_option('time_format'), $timestamp);
    }

    public static function utcDateTime(): string
    {
        return gmdate('Y-m-d H:i:s');
    }

    /**
     * Convert to seconds
     *
     * 转换为秒
     *
     * @param int $time
     * @param string $unit
     * @return int|InvalidArgumentException
     */
    public static function toSeconds(int $time, string $unit): int|InvalidArgumentException
    {
        return match ($unit) {
            'second' => $time,
            'minute' => $time * 60,
            'hour'   => $time * 60 * 60,
            'day'    => $time * 60 * 60 * 24,
            'week'   => $time * 60 * 60 * 24 * 7,
            'month'  => $time * 60 * 60 * 24 * 30,
            'year'   => $time * 60 * 60 * 24 * 365,
            default  => throw new InvalidArgumentException('Invalid unit'),
        };
    }

    /**
     * Format seconds into human-readable time string.
     *
     * 将秒数格式化为人类可读的时间字符串。
     *
     * @param int $seconds
     * @return string Formatted time string (e.g., "1分30秒", "2天3小时").
     * @since 1.0.0
     * @author Wang Shai
     */
    public static function formatSeconds(int $seconds): string
    {
        if ($seconds < 0 || $seconds === 0) {
            return '0 ' . _n('second', 'seconds', 0);
        }
        $units = [
            'year'   => 365 * 24 * 60 * 60,
            'month'  => 30 * 24 * 60 * 60,
            'week'   => 7 * 24 * 60 * 60,
            'day'    => 24 * 60 * 60,
            'hour'   => 60 * 60,
            'minute' => 60,
            'second' => 1,
        ];
        $parts = [];
        foreach ($units as $unitName => $unitValue) {
            if ($seconds >= $unitValue) {
                $count    = intdiv($seconds, $unitValue);
                $seconds %= $unitValue;

                $singular = $unitName;
                $plural   = $singular . 's';
                $label    = sprintf(_n('%s ' . $singular, '%s ' . $plural, $count), $count);
                $parts[]  = "{$label}";
            }
        }

        return implode(' ', $parts);
    }
}
