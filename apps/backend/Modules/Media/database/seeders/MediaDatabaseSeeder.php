<?php

namespace Modules\Media\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Media\Models\Folder;
use Modules\Media\Schemas\Folder\FolderSchema;

class MediaDatabaseSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // firstOrCreate, not create: the unique([name, parent_id]) index
        // can't guard parent_id IS NULL in MySQL, so plain creates would
        // duplicate the root folders on every re-run.
        $tree = [
            'Catalog' => ['Product'],
            'Content' => [],
            'Settings' => [],
        ];

        $position = 0;
        foreach ($tree as $name => $children) {
            $parent = Folder::query()->firstOrCreate(
                [FolderSchema::NAME => $name, FolderSchema::PARENT_ID => null],
                [FolderSchema::USER_ID => 1, FolderSchema::POSITION => ++$position],
            );

            foreach ($children as $childPosition => $childName) {
                Folder::query()->firstOrCreate(
                    [FolderSchema::NAME => $childName, FolderSchema::PARENT_ID => $parent->getKey()],
                    [FolderSchema::USER_ID => 1, FolderSchema::POSITION => $childPosition + 1],
                );
            }
        }
    }
}
