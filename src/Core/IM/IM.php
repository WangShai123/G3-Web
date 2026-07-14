<?php
namespace JEALER\G3\Core\IM;

final class IM {
    public const TYPE_CUSTOMER_SERVICE = 'customer_service';

    public const STATUS_PENDING = 'pending';
    public const STATUS_BOT_HANDLED = 'bot-handled';
    public const STATUS_HANDLED = 'handled';
    public const STATUS_ON_HOLD = 'onHold';
    public const STATUS_CLOSED = 'closed';
    public const STATUS_TIMEOUT = 'timeout';

    public const ROLE_CUSTOMER = 'customer';
    public const ROLE_AGENT = 'agent';
    public const ROLE_SYSTEM = 'system';

    public const ACTOR_USER = 'user';
    public const ACTOR_GUEST = 'guest';
    public const ACTOR_AGENT = 'agent';
    public const ACTOR_SYSTEM = 'system';

    public const EVENT_CONVERSATION_CREATED = 'conversation.created';
    public const EVENT_CONVERSATION_UPDATED = 'conversation.updated';
    public const EVENT_CONVERSATION_STATUS_CHANGED = 'conversation.status.changed';
    public const EVENT_MESSAGE_CREATED = 'message.created';
    public const EVENT_PARTICIPANT_READ = 'participant.read';

    public const MESSAGE_TEXT = 'text';
    public const MESSAGE_OFFLINE = 'offline';
}
