<?php

namespace Modules\Menu\Transfer;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;
use InvalidArgumentException;
use Modules\Core\Contracts\Gateways\Core\CoreGatewayInterface;
use Modules\Core\Contracts\Gateways\Transfer\DTOs\ImportOptions;
use Modules\Core\Contracts\Gateways\Transfer\DTOs\ImportResult;
use Modules\Core\Contracts\Gateways\Transfer\DTOs\TransferSection;
use Modules\Core\Contracts\Gateways\Transfer\ModuleTransferInterface;
use Modules\Core\Contracts\Gateways\Transfer\TransferStrategyEnum;
use Modules\Core\Schemas\Language\LanguageSchema;
use Modules\Menu\Actions\Admin\Menu\CreateMenuAction;
use Modules\Menu\Actions\Admin\Menu\UpdateMenuAction;
use Modules\Menu\Actions\Admin\MenuLink\CreateMenuLinkAction;
use Modules\Menu\Actions\Admin\MenuLink\UpdateMenuLinkAction;
use Modules\Menu\Models\Menu;
use Modules\Menu\Models\MenuLink;
use Modules\Menu\Schemas\Menu\MenuLocationEnum;
use Modules\Menu\Schemas\Menu\MenuSchema;
use Modules\Menu\Schemas\Menu\MenuTranslationSchema;
use Modules\Menu\Schemas\MenuLink\MenuLinkSchema;
use Modules\Menu\Schemas\MenuLink\MenuLinkTargetEnum;
use Modules\Menu\Schemas\MenuLink\MenuLinkTranslationSchema;
use Throwable;

/**
 * Menu module's implementation of the data-transfer standard: menus
 * (matched by their unique location) before the links referencing them.
 *
 * Links form a tree, so the export orders them parents-first (by depth)
 * and the import replays parents before children, remapping both the
 * menu and parent references. With no unique constraint on links, a
 * row matches an existing link by (menu, parent, url, position).
 */
class MenuTransfer implements ModuleTransferInterface
{
    public const string SECTION_MENUS = 'menus';

    public const string SECTION_MENU_LINKS = 'menu-links';

    private const int SENTINEL_BASE = -1;

    /**
     * Exported source id → local id (or dry-run sentinel).
     *
     * @var array<int, int>
     */
    private array $menuIdBySource = [];

    /**
     * Exported source id → local id (or dry-run sentinel).
     *
     * @var array<int, int>
     */
    private array $linkIdBySource = [];

    private int $sentinel = self::SENTINEL_BASE;

    public function __construct(
        protected CreateMenuAction $createMenu,
        protected UpdateMenuAction $updateMenu,
        protected CreateMenuLinkAction $createMenuLink,
        protected UpdateMenuLinkAction $updateMenuLink,
        protected CoreGatewayInterface $coreGateway,
    ) {}

    public function sections(): array
    {
        return [
            TransferSection::full(self::SECTION_MENUS),
            TransferSection::full(self::SECTION_MENU_LINKS),
        ];
    }

    public function exportRows(string $section): array
    {
        return match ($section) {
            self::SECTION_MENUS => $this->exportMenus(),
            self::SECTION_MENU_LINKS => $this->exportMenuLinks(),
            default => throw new InvalidArgumentException("Unknown section [{$section}]."),
        };
    }

    public function importRows(string $section, array $rows, ImportOptions $options): ImportResult
    {
        return match ($section) {
            self::SECTION_MENUS => $this->importMenus($rows, $options),
            self::SECTION_MENU_LINKS => $this->importMenuLinks($rows, $options),
            default => throw new InvalidArgumentException("Unknown section [{$section}]."),
        };
    }

    /**
     * @return list<array{source_id: int, payload: array<string, mixed>}>
     */
    private function exportMenus(): array
    {
        $codeByLanguageId = $this->codeByLanguageId();

        $rows = [];
        Menu::query()
            ->with(MenuSchema::RES_TRANSLATIONS)
            ->orderBy(MenuSchema::ID)
            ->chunkById(200, function ($menus) use (&$rows, $codeByLanguageId): void {
                foreach ($menus as $menu) {
                    $rows[] = [
                        self::KEY_SOURCE_ID => (int) $menu->getKey(),
                        self::KEY_PAYLOAD => [
                            MenuSchema::LOCATION => $menu->{MenuSchema::LOCATION}->value,
                            MenuSchema::IS_ACTIVE => (bool) $menu->{MenuSchema::IS_ACTIVE},
                            MenuSchema::RES_TRANSLATIONS => $this->exportTranslations(
                                $menu->{MenuSchema::RES_TRANSLATIONS},
                                MenuTranslationSchema::LANGUAGE_ID,
                                [
                                    MenuTranslationSchema::TITLE,
                                    MenuTranslationSchema::DESCRIPTION,
                                ],
                                $codeByLanguageId,
                            ),
                        ],
                    ];
                }
            });

        return $rows;
    }

    /**
     * @return list<array{source_id: int, payload: array<string, mixed>}>
     */
    private function exportMenuLinks(): array
    {
        $codeByLanguageId = $this->codeByLanguageId();

        $rows = [];
        MenuLink::query()
            ->with(MenuLinkSchema::RES_TRANSLATIONS)
            ->orderBy(MenuLinkSchema::ID)
            ->chunkById(500, function ($links) use (&$rows, $codeByLanguageId): void {
                // Parents before children: depth, then position, then id.
                $ordered = $links
                    ->groupBy(MenuLinkSchema::MENU_ID)
                    ->flatMap(fn ($menuLinks) => $this->byTreeDepth($menuLinks));

                foreach ($ordered as $link) {
                    $rows[] = [
                        self::KEY_SOURCE_ID => (int) $link->getKey(),
                        self::KEY_PAYLOAD => [
                            // Source-store menu/parent ids; remapped on import.
                            MenuLinkSchema::MENU_ID => (int) $link->{MenuLinkSchema::MENU_ID},
                            MenuLinkSchema::PARENT_ID => $link->{MenuLinkSchema::PARENT_ID} !== null
                                ? (int) $link->{MenuLinkSchema::PARENT_ID}
                                : null,
                            MenuLinkSchema::POSITION => $link->{MenuLinkSchema::POSITION},
                            MenuLinkSchema::URL => $link->{MenuLinkSchema::URL},
                            MenuLinkSchema::TARGET => $link->{MenuLinkSchema::TARGET}->value,
                            MenuLinkSchema::RES_TRANSLATIONS => $this->exportTranslations(
                                $link->{MenuLinkSchema::RES_TRANSLATIONS},
                                MenuLinkTranslationSchema::LANGUAGE_ID,
                                [
                                    MenuLinkTranslationSchema::TITLE,
                                    MenuLinkTranslationSchema::DESCRIPTION,
                                ],
                                $codeByLanguageId,
                            ),
                        ],
                    ];
                }
            });

        return $rows;
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     */
    private function importMenus(array $rows, ImportOptions $options): ImportResult
    {
        $result = ImportResult::empty();
        $languageIdByCode = $this->languageIdByCode();

        foreach ($rows as $index => $row) {
            $label = self::SECTION_MENUS.'['.($index + 1).']';
            $payload = $row[self::KEY_PAYLOAD] ?? null;
            $sourceId = $row[self::KEY_SOURCE_ID] ?? null;

            if (! is_array($payload) || ! is_numeric($sourceId)) {
                $result->recordFailed($label.': invalid_row');

                continue;
            }

            $validator = Validator::make($payload, $this->menuShapeRules());
            if ($validator->fails()) {
                $result->recordFailed($label.': '.$validator->errors()->first());

                continue;
            }

            try {
                $item = $this->resolveTranslations(
                    $payload,
                    MenuSchema::RES_TRANSLATIONS,
                    MenuTranslationSchema::class,
                    $languageIdByCode,
                    $result,
                    $label,
                );
                if ($item === null) {
                    continue;
                }

                $existing = Menu::query()
                    ->where(MenuSchema::LOCATION, $payload[MenuSchema::LOCATION])
                    ->first();

                $validator = Validator::make($item, $this->menuRules($existing?->getKey()));
                if ($validator->fails()) {
                    $result->recordFailed($label.': '.$validator->errors()->first());

                    continue;
                }

                if ($options->dryRun) {
                    $this->menuIdBySource[(int) $sourceId] = $existing?->getKey() !== null
                        ? (int) $existing->getKey()
                        : $this->nextSentinel();
                    $this->recordOutcome($result, $options, $existing !== null);

                    continue;
                }

                if ($existing !== null) {
                    $this->menuIdBySource[(int) $sourceId] = (int) $existing->getKey();

                    if ($options->strategy === TransferStrategyEnum::SKIP) {
                        $result->recordSkipped();

                        continue;
                    }

                    $this->updateMenu->handle((string) $existing->getKey(), $item);
                    $result->recordUpdated();

                    continue;
                }

                $record = $this->createMenu->handle($item);
                $this->menuIdBySource[(int) $sourceId] = (int) $record->getKey();
                $result->recordCreated();
            } catch (Throwable $e) {
                report($e);
                $result->recordFailed($label.': '.($e->getMessage() !== '' ? $e->getMessage() : class_basename($e)));
            }
        }

        return $result;
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     */
    private function importMenuLinks(array $rows, ImportOptions $options): ImportResult
    {
        $result = ImportResult::empty();
        $languageIdByCode = $this->languageIdByCode();

        foreach ($rows as $index => $row) {
            $label = self::SECTION_MENU_LINKS.'['.($index + 1).']';
            $payload = $row[self::KEY_PAYLOAD] ?? null;
            $sourceId = $row[self::KEY_SOURCE_ID] ?? null;

            if (! is_array($payload) || ! is_numeric($sourceId)) {
                $result->recordFailed($label.': invalid_row');

                continue;
            }

            $menuId = $this->remap($payload[MenuLinkSchema::MENU_ID] ?? null, $this->menuIdBySource);
            if ($menuId === null) {
                $result->recordFailed($label.': unresolved_menu');

                continue;
            }
            $payload[MenuLinkSchema::MENU_ID] = $menuId;

            // Parents replay before children, so a parent source id that
            // is part of this file resolves here; one that is not fails.
            $parentId = $payload[MenuLinkSchema::PARENT_ID];
            if ($parentId !== null) {
                $remappedParent = $this->remap($parentId, $this->linkIdBySource);
                if ($remappedParent === null) {
                    $result->recordFailed($label.': unresolved_parent');

                    continue;
                }
                $payload[MenuLinkSchema::PARENT_ID] = $remappedParent;
            }

            $validator = Validator::make($payload, $this->linkShapeRules());
            if ($validator->fails()) {
                $result->recordFailed($label.': '.$validator->errors()->first());

                continue;
            }

            try {
                $item = $this->resolveTranslations(
                    $payload,
                    MenuLinkSchema::RES_TRANSLATIONS,
                    MenuLinkTranslationSchema::class,
                    $languageIdByCode,
                    $result,
                    $label,
                );
                if ($item === null) {
                    continue;
                }

                $existing = $this->matchLink($payload, $menuId);

                if ($options->dryRun) {
                    $this->linkIdBySource[(int) $sourceId] = $existing?->getKey() !== null
                        ? (int) $existing->getKey()
                        : $this->nextSentinel();
                    $this->recordOutcome($result, $options, $existing !== null);

                    continue;
                }

                if ($existing !== null) {
                    // Register before the strategy check: children of a
                    // skipped parent still need the remap to resolve.
                    $this->linkIdBySource[(int) $sourceId] = (int) $existing->getKey();

                    if ($options->strategy === TransferStrategyEnum::SKIP) {
                        $result->recordSkipped();

                        continue;
                    }

                    $this->updateMenuLink->handle((string) $menuId, (string) $existing->getKey(), $item);
                    $result->recordUpdated();

                    continue;
                }

                $record = $this->createMenuLink->handle((string) $menuId, $item);
                $this->linkIdBySource[(int) $sourceId] = (int) $record->getKey();
                $result->recordCreated();
            } catch (Throwable $e) {
                report($e);
                $result->recordFailed($label.': '.($e->getMessage() !== '' ? $e->getMessage() : class_basename($e)));
            }
        }

        return $result;
    }

    /**
     * A link matches an existing row by (menu, parent, url, position) —
     * the closest thing to a natural key the table has.
     *
     * @param  array<string, mixed>  $payload
     */
    private function matchLink(array $payload, int $menuId): ?MenuLink
    {
        $query = MenuLink::query()
            ->where(MenuLinkSchema::MENU_ID, $menuId)
            ->where(MenuLinkSchema::URL, $payload[MenuLinkSchema::URL]);

        // Null comparisons never match in SQL — spell them out.
        if ($payload[MenuLinkSchema::PARENT_ID] === null) {
            $query->whereNull(MenuLinkSchema::PARENT_ID);
        } else {
            $query->where(MenuLinkSchema::PARENT_ID, $payload[MenuLinkSchema::PARENT_ID]);
        }

        $position = $payload[MenuLinkSchema::POSITION] ?? null;
        if ($position === null) {
            $query->whereNull(MenuLinkSchema::POSITION);
        } else {
            $query->where(MenuLinkSchema::POSITION, $position);
        }

        return $query->first();
    }

    /**
     * @return array<string, list<mixed>>
     */
    private function menuShapeRules(): array
    {
        return [
            MenuSchema::LOCATION => ['required', 'string', new Enum(MenuLocationEnum::class)],
            MenuSchema::IS_ACTIVE => ['required', 'boolean'],
            MenuSchema::RES_TRANSLATIONS => ['required', 'array', 'min:1'],
            MenuSchema::RES_TRANSLATIONS.'.*.'.MenuTranslationSchema::COL_LANGUAGE => ['required', 'string', 'distinct', 'size:2'],
            MenuSchema::RES_TRANSLATIONS.'.*.'.MenuTranslationSchema::TITLE => ['required', 'string', 'max:255'],
            MenuSchema::RES_TRANSLATIONS.'.*.'.MenuTranslationSchema::DESCRIPTION => ['nullable', 'string'],
        ];
    }

    /**
     * Mirrors MenuRequest: one menu per location, ignoring the record an
     * UPDATE strategy would overwrite.
     *
     * @return array<string, list<mixed>>
     */
    private function menuRules(int|string|null $ignoreId): array
    {
        return [
            MenuSchema::LOCATION => ['required', 'string',
                Rule::unique(MenuSchema::TABLE, MenuSchema::LOCATION)->ignore($ignoreId)],
            MenuSchema::RES_TRANSLATIONS => ['required', 'array', 'min:1'],
        ];
    }

    /**
     * @return array<string, list<mixed>>
     */
    private function linkShapeRules(): array
    {
        return [
            MenuLinkSchema::PARENT_ID => ['nullable', 'integer'],
            MenuLinkSchema::URL => ['required', 'string', 'max:255'],
            MenuLinkSchema::TARGET => ['required', 'string', new Enum(MenuLinkTargetEnum::class)],
            MenuLinkSchema::POSITION => ['nullable', 'integer', 'max:255'],
            MenuLinkSchema::RES_TRANSLATIONS => ['required', 'array', 'min:1'],
            MenuLinkSchema::RES_TRANSLATIONS.'.*.'.MenuLinkTranslationSchema::COL_LANGUAGE => ['required', 'string', 'distinct', 'size:2'],
            MenuLinkSchema::RES_TRANSLATIONS.'.*.'.MenuLinkTranslationSchema::TITLE => ['required', 'string', 'max:255'],
            MenuLinkSchema::RES_TRANSLATIONS.'.*.'.MenuLinkTranslationSchema::DESCRIPTION => ['nullable', 'string'],
        ];
    }

    /**
     * Rows of a loaded translations relation as `[{language: code, ...}]`.
     *
     * @param  iterable<int, mixed>  $translations
     * @param  list<string>  $valueKeys
     * @param  array<int, string>  $codeByLanguageId
     * @return list<array<string, mixed>>
     */
    private function exportTranslations(
        iterable $translations,
        string $languageIdKey,
        array $valueKeys,
        array $codeByLanguageId,
    ): array {
        $exported = [];
        foreach ($translations as $translation) {
            $code = $codeByLanguageId[(int) $translation->{$languageIdKey}] ?? null;
            if ($code === null) {
                continue;
            }

            $row = [MenuTranslationSchema::COL_LANGUAGE => $code];
            foreach ($valueKeys as $key) {
                $row[$key] = $translation->{$key};
            }
            $exported[] = $row;
        }

        return $exported;
    }

    /**
     * Replace language codes with ids in a translation array.
     *
     * @param  array<string, mixed>  $payload
     * @param  class-string  $translationSchema
     * @param  array<string, int>  $languageIdByCode
     * @return array<string, mixed>|null
     */
    private function resolveTranslations(
        array $payload,
        string $translationsKey,
        string $translationSchema,
        array $languageIdByCode,
        ImportResult $result,
        string $label,
    ): ?array {
        $translations = [];
        foreach ($payload[$translationsKey] as $translation) {
            $languageId = $languageIdByCode[$translation[$translationSchema::COL_LANGUAGE]] ?? null;
            if ($languageId === null) {
                $result->recordFailed($label.': unresolved_language:'.$translation[$translationSchema::COL_LANGUAGE]);

                return null;
            }

            $translations[] = [
                $translationSchema::LANGUAGE_ID => $languageId,
                $translationSchema::TITLE => $translation[$translationSchema::TITLE],
                $translationSchema::DESCRIPTION => $translation[$translationSchema::DESCRIPTION] ?? null,
            ];
        }

        $payload[$translationsKey] = $translations;

        return $payload;
    }

    /**
     * Sort one menu's links parents-first: repeated passes over the
     * remaining links emit rows whose parent has already been emitted.
     *
     * @param  Collection<int, MenuLink>  $links
     * @return Collection<int, MenuLink>
     */
    private function byTreeDepth(Collection $links): Collection
    {
        $ordered = collect();
        $remaining = $links->values();
        $emitted = collect();

        while ($remaining->isNotEmpty()) {
            $ready = $remaining->filter(
                fn (MenuLink $link) => $link->{MenuLinkSchema::PARENT_ID} === null
                    || $emitted->contains('id', $link->{MenuLinkSchema::PARENT_ID}),
            );

            if ($ready->isEmpty()) {
                // A cycle or a dangling parent: emit the rest in id order
                // rather than looping forever; the import will fail the
                // dangling rows with unresolved_parent.
                return $ordered->merge($remaining->sortBy(MenuLinkSchema::ID));
            }

            foreach ($ready->sortBy([MenuLinkSchema::POSITION, MenuLinkSchema::ID]) as $link) {
                $ordered->push($link);
                $emitted->push($link);
                $remaining = $remaining->reject(fn (MenuLink $row) => $row->id === $link->id);
            }
        }

        return $ordered;
    }

    /**
     * Count a row's would-be outcome for a dry run.
     */
    private function recordOutcome(ImportResult $result, ImportOptions $options, bool $exists): void
    {
        if (! $exists) {
            $result->recordCreated();
        } elseif ($options->strategy === TransferStrategyEnum::SKIP) {
            $result->recordSkipped();
        } else {
            $result->recordUpdated();
        }
    }

    /**
     * @param  array<int, int>  $map
     */
    private function remap(mixed $sourceId, array $map): ?int
    {
        return is_numeric($sourceId) ? ($map[(int) $sourceId] ?? null) : null;
    }

    /**
     * @return array<string, int>
     */
    private function languageIdByCode(): array
    {
        $codes = $this->coreGateway->getActiveLanguages()
            ->pluck(LanguageSchema::CODE)
            ->all();

        return $this->coreGateway->getLanguageIdsByCodes($codes);
    }

    /**
     * @return array<int, string>
     */
    private function codeByLanguageId(): array
    {
        $codeByLanguageId = [];
        foreach ($this->coreGateway->getActiveLanguages() as $language) {
            $codeByLanguageId[(int) $language->getKey()] = $language->{LanguageSchema::CODE};
        }

        return $codeByLanguageId;
    }

    private function nextSentinel(): int
    {
        return $this->sentinel--;
    }
}
