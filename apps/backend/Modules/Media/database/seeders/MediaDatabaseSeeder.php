<?php

namespace Modules\Media\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Media\Models\Folder;
use Modules\Media\Models\Media;
use Modules\Media\Schemas\Folder\FolderSchema;

class MediaDatabaseSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // $this->call([]);
        //        Folder::factory()->count(3)->create();
        //        Media::factory()->count(10)->create();
        $folders = ['Catalog', 'Content', 'Settings'];
        foreach ($folders as $i => $folder) {
            Folder::query()->create([
                FolderSchema::USER_ID => 1,
                FolderSchema::PARENT_ID => null,
                FolderSchema::NAME => $folder,
                FolderSchema::POSITION => $i + 1,
            ]);
        }
    }
}
