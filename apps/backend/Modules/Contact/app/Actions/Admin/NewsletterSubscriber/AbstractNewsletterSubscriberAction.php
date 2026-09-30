<?php

namespace Modules\Contact\Actions\Admin\NewsletterSubscriber;

use Modules\Contact\Models\NewsletterSubscriber;

abstract class AbstractNewsletterSubscriberAction
{
    public function __construct(protected NewsletterSubscriber $model) {}
}
