<?php
namespace JEALER\G3\Utilities;

final class Date {
    /**
     * Get human readable time string
     * 
     * 获取人类可读的时间字符串
     * 
     * @param int $time timestamp
     * @return string human readable time string
     * @since 1.0.0
     * @author Wang Shai
     */
    public static function humanTime(int $time = 0): string
    {
        if (!$time) {
            // Default: Post publish time
            $time = get_the_time('U');
        }

        $timeDiff = current_time('timestamp') - $time;

        return match (true) {
            $timeDiff < 60 => \sprintf(
                _n('%s second ago', '%s seconds ago', $timeDiff, 'G3'),
                $timeDiff
            ),
            $timeDiff < 3600 => \sprintf(
                _n('%s minute ago', '%s minutes ago', intdiv($timeDiff, 60), 'G3'),
                intdiv($timeDiff, 60)
            ),
            $timeDiff < 86400 => \sprintf(
                _n('%s hour ago', '%s hours ago', intdiv($timeDiff, 3600), 'G3'),
                intdiv($timeDiff, 3600)
            ),
            $timeDiff < 2592000 => \sprintf(
                _n('%s day ago', '%s days ago', intdiv($timeDiff, 86400), 'G3'),
                intdiv($timeDiff, 86400)
            ),
            default => wp_date(get_option('date_format'), $time),
        };
    }

    /**
     * Get Localized Date and Time based on WordPress settings
     * 
     * 按照 WordPress 设置的日期和时间格式返回当前时区的日期和时间字符串
     * 
     * @param int $timestamp timestamp
     * @return string date and time string
     * @since 1.0.0
     * @author Wang Shai
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
     * @param int $timestamp timestamp
     * @return string date string
     * @since 1.0.0
     * @author Wang Shai
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
     * @return string time string
     * @since 1.0.0
     * @author Wang Shai
     */
    public static function time(int $timestamp): bool|string
    {
        return wp_date(get_option('time_format'), $timestamp);
    }
}