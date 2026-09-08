<?php

namespace Modules\Core\Contracts\Events\Contact;

use Modules\Core\Contracts\Events\AbstractBaseEvent;

class ContactMessageCreatedEvent extends AbstractBaseEvent
{
    public int $id;

    public string $name;

    public string $email;

    public ?string $phone;

    public string $subject;

    public string $content;

    public string $status;

    public ?string $ip_address;

    public string $created_at;
}
