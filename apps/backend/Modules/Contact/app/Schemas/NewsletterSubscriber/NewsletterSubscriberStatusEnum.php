<?php

namespace Modules\Contact\Schemas\NewsletterSubscriber;

enum NewsletterSubscriberStatusEnum: string
{
    case SUBSCRIBED = 'subscribed';

    case UNSUBSCRIBED = 'unsubscribed';
}
