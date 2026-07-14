<?php
namespace JEALER\G3\Jobs;
use JEALER\G3\Core\Container\Container;
use JEALER\G3\Core\Queue\Job;
use JEALER\G3\Core\Queue\Queue;
use JEALER\G3\Services\CustomerService;
use Throwable;

class CustomerMessageJob extends Job {
    public static function dispatch(int $days = 0): mixed
    {
        return Queue::driver()->push(static::class, ['days' => $days], 0, 'default');
    }

    public static function runScheduled(): void
    {
        self::dispatch();
    }

    public function handle(array $data): void
    {
        if (!$this->dep) return;
        /** @var CustomerService $service */
        $service = Container::use(CustomerService::class);
        $option  = $service->option();
        $days    = (int) ($data['days'] ?? 0);

        if ($days <= 0) {
            $days = (int) ($option['retentionDays'] ?? 180);
        }

        $timeouts = $service->markTimeoutConversations((int) ($option['timeoutMinutes'] ?? 120));
        $result   = $service->cleanupBeforeDays($days);
        error_log('[G3 CustomerMessageJob] Timeout conversations: ' . $timeouts . '; cleanup result: ' . wp_json_encode($result, JSON_UNESCAPED_UNICODE));
    }

    public function failed(array $data, Throwable $exception): void
    {
        error_log('[G3 CustomerMessageJob] Cleanup failed: ' . $exception->getMessage());
    }
}
