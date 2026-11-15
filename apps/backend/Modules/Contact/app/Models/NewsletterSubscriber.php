<?php

namespace Modules\Contact\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Modules\Contact\Database\Factories\NewsletterSubscriberFactory;
use Modules\Contact\Schemas\NewsletterSubscriber\NewsletterSubscriberSchema;
use Modules\Contact\Schemas\NewsletterSubscriber\NewsletterSubscriberStatusEnum;

/**
 * @property int $id
 * @property string $email
 * @property string|null $locale
 * @property NewsletterSubscriberStatusEnum $status
 * @property CarbonInterface|null $created_at
 * @property CarbonInterface|null $updated_at
 */
class NewsletterSubscriber extends Model
{
    /** @use HasFactory<NewsletterSubscriberFactory> */
    use HasFactory;

    protected $table = NewsletterSubscriberSchema::TABLE;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        NewsletterSubscriberSchema::EMAIL,
        NewsletterSubscriberSchema::LOCALE,
        NewsletterSubscriberSchema::STATUS,
    ];

    protected function casts(): array
    {
        return [
            NewsletterSubscriberSchema::STATUS => NewsletterSubscriberStatusEnum::class,
        ];
    }

    protected static function newFactory(): NewsletterSubscriberFactory
    {
        return NewsletterSubscriberFactory::new();
    }
}
