<?php
namespace JEALER\G3\Jobs;
use JEALER\G3\Core\Queue\Job;
use JEALER\G3\Core\Queue\Queue;
use JEALER\G3\Services\CustomerService;
use Throwable;

class IMDataCleanerJob extends Job {
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
        $customerService = $this->container->get(CustomerService::class);
        $option          = $customerService->option();
        $days            = (int) ($data['days'] ?? 0);

        if ($days <= 0) {
            $days = (int) ($option['retentionDays'] ?? 180);
        }

        $result = $customerService->cleanupBeforeDays($days);
        $this->logger->info('IM database data cleanup completed.', [
            'module'         => 'im',
            'cleanup_result' => $result,
            'retention_days' => $days,
        ]);
    }

    public function failed(array $data, Throwable $exception): void
    {
        $this->logger->error('IM database data cleanup failed.', [
            'module'    => 'im',
            'data'      => $data,
            'exception' => $exception,
        ]);
    }
}
