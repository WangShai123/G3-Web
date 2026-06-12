<?php
namespace JEALER\G3\Utilities;

/**
 * Event Dispatcher
 * 
 * 事件分发器，用于处理事件订阅和发布
 * 
 * @since 1.0.0
 * @author Wang Shai
 */
class Event {

    /**
     * Listeners storage
     * 
     * 监听器存储
     * 
     * @var array
     */
    private static array $listeners = [];

    /**
     * Subscribe to an event
     * 
     * 订阅事件
     * 
     * @param string $event Event name
     * @param callable $callback Callback function
     * @param int $priority Priority of the listener (lower numbers are executed first)
     * @return void
     */
    public static function subscribe(string $event, callable $callback, int $priority = 10): void
    {
        if (!isset(self::$listeners[$event])) {
            self::$listeners[$event] = [];
        }

        self::$listeners[$event][] = [
            'callback' => $callback,
            'priority' => $priority
        ];

        // Sort listeners by priority
        self::sortListeners($event);
    }

    /**
     * Dispatch an event
     * 
     * 分发事件
     * 
     * @param string $event Event name
     * @param mixed ...$args Arguments to pass to the callbacks
     * @return void
     */
    public static function dispatch(string $event, ...$args): void
    {
        if (!isset(self::$listeners[$event])) {
            return;
        }

        foreach (self::$listeners[$event] as $listener) {
            call_user_func_array($listener['callback'], $args);
        }
    }

    /**
     * Remove a listener
     * 
     * 移除监听器
     * 
     * @param string $event Event name
     * @param callable|null $callback Specific callback to remove, or null to remove all
     * @return void
     */
    public static function removeListener(string $event, ?callable $callback = null): void
    {
        if (!isset(self::$listeners[$event])) {
            return;
        }

        if ($callback === null) {
            unset(self::$listeners[$event]);
            return;
        }

        foreach (self::$listeners[$event] as $index => $listener) {
            if ($listener['callback'] === $callback) {
                unset(self::$listeners[$event][$index]);
            }
        }

        // Re-index array after removal
        self::$listeners[$event] = array_values(self::$listeners[$event]);
    }

    /**
     * Sort listeners by priority
     * 
     * 按优先级对监听器进行排序
     * 
     * @param string $event Event name
     * @return void
     */
    private static function sortListeners(string $event): void
    {
        usort(self::$listeners[$event], function ($a, $b) {
            return $a['priority'] <=> $b['priority'];
        });
    }

    /**
     * Clear all listeners
     * 
     * 清空所有监听器
     * 
     * @return void
     */
    public static function clearListeners(): void
    {
        self::$listeners = [];
    }
}
