<?php

namespace Modules\Contact\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Modules\Contact\Database\Factories\ContactMessageFactory;
use Modules\Contact\Schemas\ContactMessage\ContactMessageSchema;
use Modules\Contact\Schemas\ContactMessage\ContactMessageStatusEnum;
use Modules\Core\Models\Traits\TenantScopedTrait;

/**
 * @property int $id
 * @property string $name
 * @property string $email
 * @property string|null $phone
 * @property string $subject
 * @property string $content
 * @property ContactMessageStatusEnum $status
 * @property string|null $ip_address
 * @property CarbonInterface|null $created_at
 * @property CarbonInterface|null $updated_at
 */
class ContactMessage extends Model
{
    /** @use HasFactory<ContactMessageFactory> */
    use HasFactory;

    use TenantScopedTrait;

    protected $table = ContactMessageSchema::TABLE;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        ContactMessageSchema::NAME,
        ContactMessageSchema::EMAIL,
        ContactMessageSchema::PHONE,
        ContactMessageSchema::SUBJECT,
        ContactMessageSchema::CONTENT,
        ContactMessageSchema::STATUS,
        ContactMessageSchema::IP_ADDRESS,
    ];

    protected function casts(): array
    {
        return [
            ContactMessageSchema::STATUS => ContactMessageStatusEnum::class,
        ];
    }

    protected static function newFactory(): ContactMessageFactory
    {
        return ContactMessageFactory::new();
    }
}
