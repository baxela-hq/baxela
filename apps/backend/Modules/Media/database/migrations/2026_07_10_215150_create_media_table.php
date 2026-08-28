<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Modules\Media\Schemas\Folder\FolderSchema;
use Modules\Media\Schemas\Media\MediaDiskEnum;
use Modules\Media\Schemas\Media\MediaMimeTypeEnum;
use Modules\Media\Schemas\Media\MediaSchema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create(MediaSchema::TABLE, function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger(MediaSchema::USER_ID);
            $table->foreignId(MediaSchema::FOLDER_ID)
                ->nullable()
                ->constrained(FolderSchema::TABLE)->onDelete('cascade');
            $table->enum(MediaSchema::DISK, MediaDiskEnum::cases())->nullable();
            $table->string(MediaSchema::PATH);
            $table->string(MediaSchema::NAME);
            $table->string(MediaSchema::FILENAME);
            $table->string(MediaSchema::EXTENSION)->nullable();
            $table->enum(MediaSchema::MIME_TYPE, MediaMimeTypeEnum::cases());
            $table->unsignedBigInteger(MediaSchema::SIZE);
            $table->text(MediaSchema::METADATA)->nullable();
            $table->timestamps();
        });

        Schema::Table(MediaSchema::TABLE, function (Blueprint $table) {
            $table->index(MediaSchema::USER_ID);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists(MediaSchema::TABLE);
    }
};
