<?php

namespace Modules\Notification\Emails;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class DynamicNotification extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * Create a new message instance.
     */
    public function __construct(
        public $subject,
        public $body,
        public $meta,
    ) {}

    /**
     * Build the message.
     */
    public function build(): self
    {
        return $this
            ->subject($this->subject)
            ->html($this->body);
    }
}
