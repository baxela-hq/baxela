<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Modules\Media\Schemas\Folder\FolderSchema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create(FolderSchema::TABLE, function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger(FolderSchema::USER_ID)->index();
            $table->unsignedBigInteger(FolderSchema::PARENT_ID)->index()->nullable();
            $table->string(FolderSchema::NAME);
            $table->unsignedTinyInteger(FolderSchema::POSITION)->nullable();
            $table->timestamps();
            $table->unique([FolderSchema::NAME, FolderSchema::PARENT_ID]);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists(FolderSchema::class);
    }
};
