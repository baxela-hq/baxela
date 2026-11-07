<?php

namespace Modules\Contact\Tests\Feature;

use Illuminate\Http\Testing\File;
use Illuminate\Http\UploadedFile;
use Modules\Auth\Models\User;
use Modules\Contact\Models\ContactMessage;
use Modules\Contact\Models\NewsletterSubscriber;
use Modules\Contact\Schemas\ContactMessage\ContactMessageSchema;
use Modules\Contact\Schemas\ContactMessage\ContactMessageStatusEnum;
use Modules\Contact\Schemas\NewsletterSubscriber\NewsletterSubscriberSchema;
use Modules\Contact\Schemas\NewsletterSubscriber\NewsletterSubscriberStatusEnum;

trait HelperTrait
{
    public function baseUrl(string $endpoint): string
    {
        return 'api/v1/contact'.$endpoint;
    }

    /** The Transfer module's endpoints hosting this module's export/import. */
    public function transferUrl(string $endpoint): string
    {
        return 'api/v1/transfer'.$endpoint;
    }

    public function superAdminUser(): User
    {
        return User::factory()->superAdmin()->create();
    }

    public function seedTransferContact(): array
    {
        $subscriber = NewsletterSubscriber::query()->create([
            NewsletterSubscriberSchema::EMAIL => 'fan@example.com',
            NewsletterSubscriberSchema::LOCALE => 'en',
            NewsletterSubscriberSchema::STATUS => NewsletterSubscriberStatusEnum::SUBSCRIBED->value,
        ]);

        $message = ContactMessage::query()->create([
            ContactMessageSchema::NAME => 'Jane Doe',
            ContactMessageSchema::EMAIL => 'jane@example.com',
            ContactMessageSchema::PHONE => null,
            ContactMessageSchema::SUBJECT => 'Question',
            ContactMessageSchema::CONTENT => 'Do you ship to DE?',
            ContactMessageSchema::STATUS => ContactMessageStatusEnum::UNREAD->value,
            ContactMessageSchema::IP_ADDRESS => '127.0.0.1',
        ]);

        return ['subscriber' => $subscriber, 'message' => $message];
    }

    /**
     * Export the (seeded) contact data through the API and return the
     * decoded envelope.
     *
     * @return array<string, mixed>
     */
    public function exportedContactFile(): array
    {
        return json_decode(
            $this->getJson($this->transferUrl('/admin/exports/contact'))->assertOk()->streamedContent(),
            true
        );
    }

    /**
     * Export through the module's own data endpoint and return the
     * decoded envelope.
     *
     * @return array<string, mixed>
     */
    public function exportedContactDataFile(): array
    {
        return json_decode(
            $this->getJson($this->baseUrl('/admin/data/export'))->assertOk()->streamedContent(),
            true
        );
    }

    /** The multipart file field for the data-import endpoints. */
    public function transferFile(string $content, string $name = 'transfer.json'): File
    {
        return UploadedFile::fake()->createWithContent($name, $content);
    }
}
