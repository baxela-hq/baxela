<?php

namespace Modules\Catalog\Support\ProductImport;

use Illuminate\Support\Facades\DB;
use Modules\Catalog\Models\Option;
use Modules\Catalog\Models\OptionTranslation;
use Modules\Catalog\Models\OptionValue;
use Modules\Catalog\Models\OptionValueTranslation;
use Modules\Catalog\Schemas\Option\OptionSchema;
use Modules\Catalog\Schemas\Option\OptionTranslationSchema;
use Modules\Catalog\Schemas\OptionValue\OptionValueSchema;
use Modules\Catalog\Schemas\OptionValue\OptionValueTranslationSchema;
use Modules\Core\Support\Slug;

/**
 * Resolves the option cells of a variant row (e.g. "Color" + "Red")
 * to ids of the global options catalog, matching titles against the
 * default language translations the same loose way headers are
 * matched. Missing options and values are either reported or created
 * on the fly, titled in the default language.
 */
final class ProductImportOptionResolver
{
    /** Normalized option title => option id, false when ambiguous. @var array<string, int|false> */
    private array $optionIdByTitle = [];

    /** "optionId|normalized value title" => value id, false when ambiguous. @var array<string, int|false> */
    private array $valueIdByKey = [];

    /** Map keys added since the last mark(), evicted when a transaction rolls back. @var array<int, string[]>[] */
    private array $addedKeys = ['option' => [], 'value' => []];

    public function __construct(
        private readonly bool $createMissing,
        private readonly int $defaultLanguageId,
    ) {
        $this->loadOptions();
    }

    /**
     * Resolve one name/value pair. Returns the option value id, or an
     * error message when the pair cannot be resolved in strict mode.
     *
     * @return array{0: int|null, 1: string|null}
     */
    public function resolve(string $name, string $value): array
    {
        $nameKey = ProductImportFields::normalize($name);

        $optionId = $this->optionIdByTitle[$nameKey] ?? null;
        if ($optionId === null) {
            if (! $this->createMissing) {
                return [null, "Unknown option: {$name}."];
            }

            $optionId = $this->createOption($name, $nameKey);
        }

        if ($optionId === false) {
            return [null, "Ambiguous option title: {$name}."];
        }

        $valueKey = $optionId.'|'.ProductImportFields::normalize($value);
        $valueId = $this->valueIdByKey[$valueKey] ?? null;
        if ($valueId === null) {
            if (! $this->createMissing) {
                return [null, "Unknown value: {$value} for option: {$name}."];
            }

            $valueId = $this->createValue($optionId, $value, $valueKey);
        }

        if ($valueId === false) {
            return [null, "Ambiguous value title: {$value} for option: {$name}."];
        }

        return [(int) $valueId, null];
    }

    /**
     * Remember how many ids were auto-created so far; a rollback of the
     * surrounding transaction must evict everything created after this
     * point, or later groups would attach to rows that no longer exist.
     */
    public function mark(): void
    {
        $this->addedKeys = ['option' => [], 'value' => []];
    }

    public function forget(): void
    {
        foreach ($this->addedKeys['option'] as $key) {
            unset($this->optionIdByTitle[$key]);
        }
        foreach ($this->addedKeys['value'] as $key) {
            unset($this->valueIdByKey[$key]);
        }
        $this->mark();
    }

    private function loadOptions(): void
    {
        $options = Option::query()
            ->with([OptionSchema::RES_VALUES])
            ->get();

        $valueIds = $options
            ->flatMap(fn (Option $option) => $option->{OptionSchema::RES_VALUES}->pluck(OptionValueSchema::ID))
            ->all();

        $titlesByOption = OptionTranslation::query()
            ->where(OptionTranslationSchema::LANGUAGE_ID, $this->defaultLanguageId)
            ->get()
            ->mapToGroups(fn (OptionTranslation $translation) => [
                $translation->{OptionTranslationSchema::OPTION_ID} => $translation->{OptionTranslationSchema::TITLE},
            ]);

        $titlesByValue = OptionValueTranslation::query()
            ->where(OptionValueTranslationSchema::LANGUAGE_ID, $this->defaultLanguageId)
            ->whereIn(OptionValueTranslationSchema::OPTION_VALUE_ID, $valueIds ?: [0])
            ->get()
            ->mapToGroups(fn (OptionValueTranslation $translation) => [
                $translation->{OptionValueTranslationSchema::OPTION_VALUE_ID} => $translation->{OptionValueTranslationSchema::TITLE},
            ]);

        foreach ($options as $option) {
            foreach ($titlesByOption->get($option->{OptionSchema::ID}, []) as $title) {
                $this->remember($this->optionIdByTitle, ProductImportFields::normalize($title),
                    (int) $option->{OptionSchema::ID});
            }

            foreach ($option->{OptionSchema::RES_VALUES} as $value) {
                foreach ($titlesByValue->get($value->{OptionValueSchema::ID}, []) as $title) {
                    $this->remember($this->valueIdByKey,
                        $option->{OptionSchema::ID}.'|'.ProductImportFields::normalize($title),
                        (int) $value->{OptionValueSchema::ID});
                }
            }
        }
    }

    /**
     * First id wins; a second, different id turns the title ambiguous
     * so resolution errors instead of silently picking one.
     *
     * @param  array<string, int|false>  $map
     */
    private function remember(array &$map, string $key, int $id): void
    {
        if (array_key_exists($key, $map) && $map[$key] !== $id) {
            $map[$key] = false;

            return;
        }

        $map[$key] = $map[$key] ?? $id;
    }

    private function createOption(string $title, string $titleKey): int|false
    {
        if (array_key_exists($titleKey, $this->optionIdByTitle)) {
            return $this->optionIdByTitle[$titleKey];
        }

        $option = Option::query()->create([
            OptionSchema::POSITION => ((int) Option::query()->max(OptionSchema::POSITION)) + 1,
        ]);
        $option->translations()->create([
            OptionTranslationSchema::LANGUAGE_ID => $this->defaultLanguageId,
            OptionTranslationSchema::TITLE => $title,
            OptionTranslationSchema::SLUG => $this->uniqueTranslationSlug(
                $title, OptionTranslationSchema::TABLE,
                OptionTranslationSchema::LANGUAGE_ID, OptionTranslationSchema::SLUG, 'option'),
        ]);

        return $this->cache('option', $this->optionIdByTitle, $titleKey,
            (int) $option->{OptionSchema::ID});
    }

    private function createValue(int $optionId, string $title, string $valueKey): int|false
    {
        if (array_key_exists($valueKey, $this->valueIdByKey)) {
            return $this->valueIdByKey[$valueKey];
        }

        $value = OptionValue::query()->create([
            OptionValueSchema::OPTION_ID => $optionId,
            OptionValueSchema::POSITION => ((int) OptionValue::query()
                ->where(OptionValueSchema::OPTION_ID, $optionId)
                ->max(OptionValueSchema::POSITION)) + 1,
        ]);
        $value->translations()->create([
            OptionValueTranslationSchema::OPTION_VALUE_ID => $value->{OptionValueSchema::ID},
            OptionValueTranslationSchema::LANGUAGE_ID => $this->defaultLanguageId,
            OptionValueTranslationSchema::TITLE => $title,
            OptionValueTranslationSchema::SLUG => $this->uniqueTranslationSlug(
                $title, OptionValueTranslationSchema::TABLE,
                OptionValueTranslationSchema::LANGUAGE_ID, OptionValueTranslationSchema::SLUG, 'value'),
        ]);

        return $this->cache('value', $this->valueIdByKey, $valueKey,
            (int) $value->{OptionValueSchema::ID});
    }

    /**
     * Translation slugs are unique per language across their whole
     * table (an option and a value may both be "Red"), numbered on
     * collision exactly like product slugs.
     */
    private function uniqueTranslationSlug(
        string $title,
        string $table,
        string $languageColumn,
        string $slugColumn,
        string $fallback,
    ): string {
        $base = Slug::slugify($title);
        if ($base === '') {
            $base = $fallback;
        }

        $candidate = $base;
        $suffix = 0;
        while (DB::table($table)
            ->where($languageColumn, $this->defaultLanguageId)
            ->where($slugColumn, $candidate)
            ->exists()) {
            $candidate = $base.'-'.(++$suffix);
        }

        return $candidate;
    }

    /**
     * @param  array<string, int|false>  $map
     */
    private function cache(string $type, array &$map, string $key, int $id): int
    {
        $map[$key] = $id;
        $this->addedKeys[$type][] = $key;

        return $id;
    }
}
