<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Core\Schemas\Shared\TenantColumn;
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
            $table->unsignedBigInteger(TenantColumn::TENANT_ID);
            $table->index(TenantColumn::TENANT_ID);
            $table->unsignedBigInteger(FolderSchema::USER_ID)->index();
            $table->unsignedBigInteger(FolderSchema::PARENT_ID)->index()->nullable();
            $table->string(FolderSchema::NAME);
            $table->unsignedTinyInteger(FolderSchema::POSITION)->nullable();
            $table->timestamps();
            $table->unique([TenantColumn::TENANT_ID, FolderSchema::NAME, FolderSchema::PARENT_ID]);
        });

        DB::statement(
            'CREATE UNIQUE INDEX media_folders_root_name_unique ON '.FolderSchema::TABLE
            .' ('.TenantColumn::TENANT_ID.', '.FolderSchema::NAME.') WHERE '.FolderSchema::PARENT_ID.' IS NULL'
        );
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists(FolderSchema::TABLE);
    }
};
