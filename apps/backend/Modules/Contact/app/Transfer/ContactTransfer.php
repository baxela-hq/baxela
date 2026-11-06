<?php

namespace Modules\Contact\Transfer;

use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use InvalidArgumentException;
use Modules\Contact\Models\ContactMessage;
use Modules\Contact\Models\NewsletterSubscriber;
use Modules\Contact\Schemas\ContactMessage\ContactMessageSchema;
use Modules\Contact\Schemas\NewsletterSubscriber\NewsletterSubscriberSchema;
use Modules\Contact\Schemas\NewsletterSubscriber\NewsletterSubscriberStatusEnum;
use Modules\Core\Contracts\Gateways\Transfer\DTOs\ImportOptions;
use Modules\Core\Contracts\Gateways\Transfer\DTOs\ImportResult;
use Modules\Core\Contracts\Gateways\Transfer\DTOs\TransferSection;
use Modules\Core\Contracts\Gateways\Transfer\ModuleTransferInterface;
use Modules\Core\Contracts\Gateways\Transfer\TransferStrategyEnum;
use Throwable;

/**
 * Contact module's implementation of the data-transfer standard.
 *
 * Newsletter subscribers are the migratable audience: matched by email
 * and upserted exactly like the public subscribe flow does (existing
 * rows get their locale/status refreshed, re-subscribes flip back).
 * Contact messages are correspondence history: export-only.
 */
class ContactTransfer implements ModuleTransferInterface
{
    public const string SECTION_NEWSLETTER_SUBSCRIBERS = 'newsletter-subscribers';

    public const string SECTION_CONTACT_MESSAGES = 'contact-messages';

    public function sections(): array
    {
        return [
            TransferSection::full(self::SECTION_NEWSLETTER_SUBSCRIBERS),
            TransferSection::exportOnly(self::SECTION_CONTACT_MESSAGES),
        ];
    }

    public function exportRows(string $section): array
    {
        return match ($section) {
            self::SECTION_NEWSLETTER_SUBSCRIBERS => $this->exportSubscribers(),
            self::SECTION_CONTACT_MESSAGES => $this->exportMessages(),
            default => throw new InvalidArgumentException("Unknown section [{$section}]."),
        };
    }

    public function importRows(string $section, array $rows, ImportOptions $options): ImportResult
    {
        return match ($section) {
            self::SECTION_NEWSLETTER_SUBSCRIBERS => $this->importSubscribers($rows, $options),
            // Export-only: the Transfer engine skips this section before
            // it ever reaches here.
            default => throw new InvalidArgumentException("Unknown section [{$section}]."),
        };
    }

    /**
     * @return list<array{source_id: int, payload: array<string, mixed>}>
     */
    private function exportSubscribers(): array
    {
        $rows = [];
        NewsletterSubscriber::query()
            ->orderBy(NewsletterSubscriberSchema::ID)
            ->chunkById(200, function ($subscribers) use (&$rows): void {
                foreach ($subscribers as $subscriber) {
                    $rows[] = [
                        self::KEY_SOURCE_ID => (int) $subscriber->getKey(),
                        self::KEY_PAYLOAD => [
                            NewsletterSubscriberSchema::EMAIL => $subscriber->{NewsletterSubscriberSchema::EMAIL},
                            NewsletterSubscriberSchema::LOCALE => $subscriber->{NewsletterSubscriberSchema::LOCALE},
                            NewsletterSubscriberSchema::STATUS => $subscriber->{NewsletterSubscriberSchema::STATUS}->value,
                        ],
                    ];
                }
            });

        return $rows;
    }

    /**
     * @return list<array{source_id: int, payload: array<string, mixed>}>
     */
    private function exportMessages(): array
    {
        $rows = [];
        ContactMessage::query()
            ->orderBy(ContactMessageSchema::ID)
            ->chunkById(200, function ($messages) use (&$rows): void {
                foreach ($messages as $message) {
                    $rows[] = [
                        self::KEY_SOURCE_ID => (int) $message->getKey(),
                        self::KEY_PAYLOAD => [
                            ContactMessageSchema::NAME => $message->{ContactMessageSchema::NAME},
                            ContactMessageSchema::EMAIL => $message->{ContactMessageSchema::EMAIL},
                            ContactMessageSchema::PHONE => $message->{ContactMessageSchema::PHONE},
                            ContactMessageSchema::SUBJECT => $message->{ContactMessageSchema::SUBJECT},
                            ContactMessageSchema::CONTENT => $message->{ContactMessageSchema::CONTENT},
                            ContactMessageSchema::STATUS => $message->{ContactMessageSchema::STATUS}->value,
                            ContactMessageSchema::IP_ADDRESS => $message->{ContactMessageSchema::IP_ADDRESS},
                            'created_at' => $message->{ContactMessageSchema::CREATED_AT}?->toIso8601String(),
                        ],
                    ];
                }
            });

        return $rows;
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     */
    private function importSubscribers(array $rows, ImportOptions $options): ImportResult
    {
        $result = ImportResult::empty();

        foreach ($rows as $index => $row) {
            $label = self::SECTION_NEWSLETTER_SUBSCRIBERS.'['.($index + 1).']';
            $payload = $row[self::KEY_PAYLOAD] ?? null;

            if (! is_array($payload)) {
                $result->recordFailed($label.': invalid_row');

                continue;
            }

            $validator = Validator::make($payload, $this->subscriberRules());
            if ($validator->fails()) {
                $result->recordFailed($label.': '.$validator->errors()->first());

                continue;
            }

            try {
                $existing = NewsletterSubscriber::query()
                    ->where(NewsletterSubscriberSchema::EMAIL, $payload[NewsletterSubscriberSchema::EMAIL])
                    ->first();

                if ($options->dryRun) {
                    if ($existing === null) {
                        $result->recordCreated();
                    } elseif ($options->strategy === TransferStrategyEnum::SKIP) {
                        $result->recordSkipped();
                    } else {
                        $result->recordUpdated();
                    }

                    continue;
                }

                if ($existing !== null && $options->strategy === TransferStrategyEnum::SKIP) {
                    $result->recordSkipped();

                    continue;
                }

                // The upsert mirrors the public subscribe flow; the status
                // comes from the row so unsubscribed state can travel too.
                NewsletterSubscriber::query()->updateOrCreate(
                    [NewsletterSubscriberSchema::EMAIL => $payload[NewsletterSubscriberSchema::EMAIL]],
                    [
                        NewsletterSubscriberSchema::LOCALE => $payload[NewsletterSubscriberSchema::LOCALE] ?? null,
                        NewsletterSubscriberSchema::STATUS => $payload[NewsletterSubscriberSchema::STATUS]
                            ?? NewsletterSubscriberStatusEnum::SUBSCRIBED->value,
                    ]
                );

                if ($existing === null) {
                    $result->recordCreated();
                } else {
                    $result->recordUpdated();
                }
            } catch (Throwable $e) {
                report($e);
                $result->recordFailed($label.': '.($e->getMessage() !== '' ? $e->getMessage() : class_basename($e)));
            }
        }

        return $result;
    }

    /**
     * @return array<string, list<mixed>>
     */
    private function subscriberRules(): array
    {
        return [
            NewsletterSubscriberSchema::EMAIL => ['required', 'email', 'max:255'],
            NewsletterSubscriberSchema::LOCALE => ['nullable', 'string', 'max:12'],
            NewsletterSubscriberSchema::STATUS => [
                'nullable',
                Rule::enum(NewsletterSubscriberStatusEnum::class),
            ],
        ];
    }
}
