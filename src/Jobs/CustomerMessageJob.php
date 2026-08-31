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
        /** @var CustomerService $customerService */
        $customerService = Container::use(CustomerService::class);
        $option          = $customerService->option();
        $days            = (int) ($data['days'] ?? 0);

        if ($days <= 0) {
            $days = (int) ($option['retentionDays'] ?? 180);
        }

        $timeouts = $customerService->markTimeoutConversations((int) ($option['timeoutMinutes'] ?? CustomerService::defaultOption()['timeoutMinutes']));
        $result   = $customerService->cleanupBeforeDays($days);
        $this->logger->info('Customer conversations cleanup completed.', [
            'module'                => 'customer',
            'timeout_conversations' => $timeouts,
            'cleanup_result'        => $result,
            'retention_days'        => $days,
        ]);
    }

    public function failed(array $data, Throwable $exception): void
    {
        $this->logger->error('Customer conversations cleanup failed.', [
            'module'    => 'customer',
            'data'      => $data,
            'exception' => $exception,
        ]);
    }
}
