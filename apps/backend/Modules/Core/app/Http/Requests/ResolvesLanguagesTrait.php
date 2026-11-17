<?php

namespace Modules\Core\Http\Requests;

use Modules\Core\Models\Language;
use Modules\Core\Schemas\Language\LanguageSchema;

trait ResolvesLanguagesTrait
{
    /** @var array<string, int> */
    protected array $languageMap = [];

    protected function resolveLanguages(): void
    {
        if (! $this->exists('translations')) {
            return;
        }

        $codes = collect((array) $this->input('translations'))
            ->pluck('language')
            ->unique()
            ->all();

        $this->languageMap = $this->languageResolve($codes);
        if (empty($this->languageMap)) {
            return;
        }

        //        $missing = array_diff(
        //            $codes,
        //            array_keys($this->languageMap)
        //        );

        $translations = collect((array) $this->input('translations'))
            ->map(function ($translation) {
                $translation['language_id'] =
                    $this->languageMap[$translation['language']] ?? null;

                return $translation;
            })
            ->all();

        $this->merge([
            'translations' => $translations,
        ]);

    }

    protected function resolveLanguagesFor(string $key): void
    {
        if (! $this->exists($key)) {
            return;
        }

        $codes = collect((array) $this->input($key))
            ->pluck('language')
            ->unique()
            ->all();

        $this->languageMap = array_merge($this->languageMap, $this->languageResolve($codes));
        if (empty($this->languageMap)) {
            return;
        }

        $items = collect((array) $this->input($key))
            ->map(function ($item) {
                $item['language_id'] =
                    $this->languageMap[$item['language']] ?? null;

                return $item;
            })
            ->all();

        $this->merge([
            $key => $items,
        ]);
    }

    /**
     * @param  list<string>  $codes
     * @return array<string, int>
     */
    private function languageResolve(array $codes): array
    {
        return Language::query()
            ->whereIn(LanguageSchema::CODE, $codes)
            ->pluck(LanguageSchema::ID, LanguageSchema::CODE)
            ->toArray();
    }
}
