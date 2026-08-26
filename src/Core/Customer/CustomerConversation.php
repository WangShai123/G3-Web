<?php
namespace JEALER\G3\Core\Customer;

final class CustomerConversation {
    public const STATUS_PENDING = 'pending';
    public const STATUS_ACTIVE = 'active';
    public const STATUS_WRAP_UP = 'wrap_up';
    public const STATUS_CLOSED = 'closed';

    public const CLOSE_BY_AGENT = 'agent';
    public const CLOSE_BY_CUSTOMER = 'customer';
    public const CLOSE_BY_TIMEOUT = 'timeout';
    public const CLOSE_BY_SYSTEM = 'system';

    public const WRAP_LOCK_ALWAYS = 'always';
    public const WRAP_LOCK_NONE = 'none';
    public const WRAP_LOCK_TIMED = 'timed';
}
