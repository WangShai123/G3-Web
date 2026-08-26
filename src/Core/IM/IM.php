<?php
namespace JEALER\G3\Core\IM;

final class IM {
    public const TYPE_CUSTOMER_SERVICE = 'customer_service';

    public const CONVERSATION_OPEN = 'open';
    public const CONVERSATION_CLOSED = 'closed';
    public const CONVERSATION_ARCHIVED = 'archived';

    public const PRESENCE_ONLINE = 'online';
    public const PRESENCE_PUSH_ONLINE = 'push_online';
    public const PRESENCE_OFFLINE = 'offline';

    public const MESSAGE_SENDING = 0;
    public const MESSAGE_SENT = 1;
    public const MESSAGE_DELIVERED = 2;
    public const MESSAGE_READ = 3;

    public const ROLE_CUSTOMER = 'customer';
    public const ROLE_AGENT = 'agent';
    public const ROLE_SYSTEM = 'system';

    public const ACTOR_USER = 'user';
    public const ACTOR_GUEST = 'guest';
    public const ACTOR_AGENT = 'agent';
    public const ACTOR_SYSTEM = 'system';

    public const EVENT_CONVERSATION_CREATED = 'conversation.created';
    public const EVENT_CONVERSATION_UPDATED = 'conversation.updated';
    public const EVENT_CONVERSATION_STATE_CHANGED = 'conversation.state.changed';
    public const EVENT_CONVERSATION_STATUS_CHANGED = 'conversation.status.changed';
    public const EVENT_MESSAGE_CREATED = 'message.created';
    public const EVENT_PARTICIPANT_READ = 'participant.read';

    public const MESSAGE_TEXT = 'text';
    public const MESSAGE_OFFLINE = 'offline';
}
