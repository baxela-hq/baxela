<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Modules\Contact\Schemas\NewsletterSubscriber\NewsletterSubscriberSchema;
use Modules\Contact\Schemas\NewsletterSubscriber\NewsletterSubscriberStatusEnum;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create(NewsletterSubscriberSchema::TABLE, function (Blueprint $table) {
            $table->id();
            $table->string(NewsletterSubscriberSchema::EMAIL)->unique();
            $table->string(NewsletterSubscriberSchema::LOCALE, 12)->nullable();
            $table->enum(NewsletterSubscriberSchema::STATUS, NewsletterSubscriberStatusEnum::cases())
                ->default(NewsletterSubscriberStatusEnum::SUBSCRIBED->value);
            $table->timestamps();

            $table->index([NewsletterSubscriberSchema::STATUS, NewsletterSubscriberSchema::CREATED_AT]);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists(NewsletterSubscriberSchema::TABLE);
    }
};
