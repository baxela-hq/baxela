<?php

namespace Modules\Contact\Actions\Public\ContactMessage;

use Modules\Contact\Exceptions\ContactMessage\CreationFailedException;
use Modules\Contact\Models\ContactMessage;
use Modules\Contact\Schemas\ContactMessage\ContactMessageSchema;
use Modules\Contact\Schemas\ContactMessage\ContactMessageStatusEnum;
use Modules\Core\Contracts\Events\Contact\ContactMessageCreatedEvent;
use Throwable;

class SubmitContactMessageAction
{
    public function __construct(protected ContactMessage $model) {}

    /**
     * @throws CreationFailedException|Throwable
     */
    public function handle(array $data, ?string $ipAddress): ContactMessage
    {
        try {
            $record = ContactMessage::query()->create([
                ContactMessageSchema::NAME => $data[ContactMessageSchema::NAME],
                ContactMessageSchema::EMAIL => $data[ContactMessageSchema::EMAIL],
                ContactMessageSchema::PHONE => $data[ContactMessageSchema::PHONE] ?? null,
                ContactMessageSchema::SUBJECT => $data[ContactMessageSchema::SUBJECT],
                ContactMessageSchema::CONTENT => $data[ContactMessageSchema::CONTENT],
                // set explicitly so the model, the created event and the
                // response carry the default instead of a null attribute
                ContactMessageSchema::STATUS => ContactMessageStatusEnum::UNREAD->value,
                ContactMessageSchema::IP_ADDRESS => $ipAddress,
            ]);
        } catch (Throwable $e) {
            report($e);
            throw new CreationFailedException;
        }

        event(ContactMessageCreatedEvent::fill($record->toArray()));

        return $record;
    }
}
