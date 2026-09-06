<?php
namespace JEALER\G3\Jobs;
use JEALER\G3\Core\Queue\Job;
use JEALER\G3\Core\Queue\Queue;
use JEALER\G3\Services\CustomerService;
use JEALER\G3\Services\IMService;
use Throwable;

class IMMessageSyncJob extends Job {
    public static function dispatch(int $limit = 200): mixed
    {
        return Queue::driver()->push(static::class, ['limit' => $limit], 0, 'default');
    }

    public static function runScheduled(): void
    {
        self::dispatch();
    }

    public function handle(array $data): void
    {
        /** @var IMService $im */
        $im    = $this->container->get(IMService::class);
        $limit = min(1000, max(1, (int) ($data['limit'] ?? 200)));

        /** @var CustomerService $customer */
        $customer = $this->container->get(CustomerService::class);
        $result   = [
            'im'                     => $im->syncCachedData($limit),
            'customer_conversations' => $customer->syncCachedCustomerConversations($limit),
        ];

        $this->logger->info('IM cached data sync completed.', [
            'module' => 'IM Cache',
            'limit'  => $limit,
            'result' => $result,
        ]);
    }

    public function failed(array $data, Throwable $exception): void
    {
        $this->logger->error('IM cached data sync failed.', [
            'module'    => 'IM Cache',
            'data'      => $data,
            'exception' => $exception,
        ]);
    }
}
