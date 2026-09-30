<?php

namespace Modules\Contact\Actions\Admin\NewsletterSubscriber;

use Modules\Contact\Models\NewsletterSubscriber;
use Modules\Contact\Schemas\NewsletterSubscriber\NewsletterSubscriberSchema;

class UpdateNewsletterSubscriberStatusAction extends AbstractNewsletterSubscriberAction
{
    public function handle(string $id, array $data): NewsletterSubscriber
    {
        $record = NewsletterSubscriber::query()->findOrFail($id);
        $record->update([
            NewsletterSubscriberSchema::STATUS => $data[NewsletterSubscriberSchema::STATUS],
        ]);

        return $record;
    }
}
