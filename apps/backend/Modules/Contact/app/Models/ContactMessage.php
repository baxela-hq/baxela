<?php

namespace Modules\Contact\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Modules\Contact\Database\Factories\ContactMessageFactory;
use Modules\Contact\Schemas\ContactMessage\ContactMessageSchema;
use Modules\Contact\Schemas\ContactMessage\ContactMessageStatusEnum;

class ContactMessage extends Model
{
    use HasFactory;

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
