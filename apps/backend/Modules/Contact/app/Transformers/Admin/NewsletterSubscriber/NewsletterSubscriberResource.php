<?php

namespace Modules\Contact\Transformers\Admin\NewsletterSubscriber;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Contact\Schemas\NewsletterSubscriber\NewsletterSubscriberSchema;

class NewsletterSubscriberResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            NewsletterSubscriberSchema::ID => $this->{NewsletterSubscriberSchema::ID},
            NewsletterSubscriberSchema::EMAIL => $this->{NewsletterSubscriberSchema::EMAIL},
            NewsletterSubscriberSchema::LOCALE => $this->{NewsletterSubscriberSchema::LOCALE},
            NewsletterSubscriberSchema::STATUS => $this->{NewsletterSubscriberSchema::STATUS},
            NewsletterSubscriberSchema::CREATED_AT => $this->{NewsletterSubscriberSchema::CREATED_AT},
            NewsletterSubscriberSchema::UPDATED_AT => $this->{NewsletterSubscriberSchema::UPDATED_AT},
        ];
    }
}
