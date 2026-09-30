<?php

namespace Modules\Contact\Actions\Public\NewsletterSubscriber;

use Modules\Contact\Models\NewsletterSubscriber;
use Modules\Contact\Schemas\NewsletterSubscriber\NewsletterSubscriberSchema;
use Modules\Contact\Schemas\NewsletterSubscriber\NewsletterSubscriberStatusEnum;

class SubscribeNewsletterAction
{
    public function __construct(protected NewsletterSubscriber $model) {}

    /**
     * Idempotent subscription: re-submitting a known email (even one that
     * had unsubscribed) flips it back to subscribed instead of failing on
     * the unique constraint.
     */
    public function handle(array $data, ?string $locale): NewsletterSubscriber
    {
        return NewsletterSubscriber::query()->updateOrCreate(
            [NewsletterSubscriberSchema::EMAIL => $data[NewsletterSubscriberSchema::EMAIL]],
            [
                NewsletterSubscriberSchema::LOCALE => $locale,
                NewsletterSubscriberSchema::STATUS => NewsletterSubscriberStatusEnum::SUBSCRIBED->value,
            ]
        );
    }
}
