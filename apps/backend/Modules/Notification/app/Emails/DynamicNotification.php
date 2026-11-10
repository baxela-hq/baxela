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
     *
     * @param  list<array{data: string, name: string, mime: string}>  $attachedDocuments  raw attachments; deliberately NOT named $attachments — that property already exists on Mailable
     */
    public function __construct(
        public $subject,
        public $body,
        public $meta,
        public array $attachedDocuments = [],
    ) {}

    /**
     * Build the message.
     */
    public function build(): self
    {
        $message = $this
            ->subject($this->subject)
            ->html($this->body);

        foreach ($this->attachedDocuments as $document) {
            $message->attachData($document['data'], $document['name'], [
                'mime' => $document['mime'],
            ]);
        }

        return $message;
    }
}
