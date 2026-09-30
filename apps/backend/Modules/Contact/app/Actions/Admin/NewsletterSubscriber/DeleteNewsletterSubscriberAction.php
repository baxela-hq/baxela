<?php

namespace Modules\Contact\Actions\Admin\NewsletterSubscriber;

class DeleteNewsletterSubscriberAction extends AbstractNewsletterSubscriberAction
{
    public function handle(string $id): bool
    {
        $record = $this->model->findOrFail($id);

        return $record->delete();
    }
}
