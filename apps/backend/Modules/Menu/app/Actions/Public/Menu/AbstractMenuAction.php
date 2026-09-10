<?php

namespace Modules\Menu\Actions\Public\Menu;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\Relation;
use Modules\Menu\Models\Menu;
use Modules\Menu\Schemas\Menu\MenuSchema;
use Modules\Menu\Schemas\MenuLink\MenuLinkSchema;

abstract class AbstractMenuAction
{
    public function __construct(protected Menu $model) {}

    /**
     * Eager-load a menu's links as a nested tree (children keyed by
     * parent_id), every level ordered by position. Translations are loaded
     * alongside so the public resource can localize each node.
     *
     * @return array<int, string|array>
     */
    protected function linkRelations(int $maxDepth = 6): array
    {
        $relations = [];

        for ($level = 1; $level <= $maxDepth; $level++) {
            $path = MenuSchema::RES_LINKS.str_repeat('.'.MenuLinkSchema::RES_CHILDREN, $level - 1);
            $relations[$path] = fn (Relation $query) => $query->orderBy(MenuLinkSchema::POSITION);
            $relations[] = $path.'.'.MenuLinkSchema::RES_TRANSLATIONS;
        }

        $relations[] = MenuSchema::RES_TRANSLATIONS;

        return $relations;
    }

    protected function scope(): Builder
    {
        return $this->model
            ->where(MenuSchema::IS_ACTIVE, true)
            ->with($this->linkRelations());
    }
}
