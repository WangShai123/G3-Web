<?php

return [
    // driver: 'redis' / 'database'
    'driver'   => 'redis',
    'redis'    => [
        'host'     => '127.0.0.1',
        'port'     => 6379,
        'password' => null,
        'database' => 1,
        'prefix'   => 'g3_queue:',
        'timeout'  => 5,
    ],
    'database' => [
        'table' => 'g3_jobs',
    ],
    // consumer: 'cron' / 'cli' / 'supervisor'
    'consumer' => 'cli',

    // CLI 消费者配置
    'cli'      => [
        // 是否启用自动清理
        'auto_cleanup'     => true,
        // 清理超时保留任务的时间（分钟）
        'reserved_timeout' => 5,
        // 清理旧任务的天数
        'old_jobs_days'    => 1,
        // 每处理多少个任务后触发一次清理
        'cleanup_interval' => 10,
        // 是否启用延迟任务处理
        'process_delayed'  => true,
        // 工作进程休眠时间（秒）
        'sleep_seconds'    => 3,
        // 最大内存限制（MB）
        'memory_limit'     => 128,
        // 最大执行时间（秒，0为无限制）
        'time_limit'       => 0,
    ],

    // Cron 消费者配置
    'cron'     => [
        // 每次cron执行处理的最大任务数
        'jobs_per_run'          => 20,
        // cron执行间隔（分钟）
        'interval_minutes'      => 1,
        // 是否启用延迟任务处理
        'process_delayed'       => true,
        // 是否在cron中执行清理
        'auto_cleanup'          => true,
        // 清理超时保留任务的时间（分钟）
        'reserved_timeout'      => 5,
        // 清理旧任务的天数
        'old_jobs_days'         => 1,
        // cron清理间隔（每N次cron执行后进行一次清理）
        'cron_cleanup_interval' => 5,
        // 智能cron管理：队列为空时自动注销cron
        'smart_cron'            => true,
        // 队列为空多少次后暂停处理cron（切换到检查模式）
        'empty_runs_threshold'  => 3,
        // 队列为空多少次后完全注销cron（动态注销）
        'unregister_threshold'  => 10,
        // 注销cron后多久检查一次是否有新任务（分钟）
        'check_interval'        => 5,
        // 是否启用动态cron注册（队列有任务时自动注册cron）
        'dynamic_registration'  => true,
    ],
];
