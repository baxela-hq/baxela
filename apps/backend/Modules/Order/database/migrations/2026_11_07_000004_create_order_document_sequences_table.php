<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Modules\Order\Schemas\Document\DocumentSequenceSchema;
use Modules\Order\Schemas\Document\DocumentSequenceTypeEnum;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create(DocumentSequenceSchema::TABLE, function (Blueprint $table) {
            $table->id();
            $table->enum(DocumentSequenceSchema::TYPE, DocumentSequenceTypeEnum::cases());
            // Document year, resolved through the store-timezone resolver
            // (D3) — never the server timezone.
            $table->year(DocumentSequenceSchema::YEAR);
            $table->unsignedBigInteger(DocumentSequenceSchema::LAST_NUMBER)->default(0);
            $table->timestamps();

            // The allocation lock target: exactly one row per (type, year);
            // first allocation creates it under a lock (D3).
            $table->unique([DocumentSequenceSchema::TYPE, DocumentSequenceSchema::YEAR]);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists(DocumentSequenceSchema::TABLE);
    }
};
