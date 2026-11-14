<?php

namespace Modules\Core\Transformers;

use Modules\Core\Models\Language;
use Modules\Core\Schemas\Language\LanguageSchema;

trait ResolvesLanguageCodesTrait
{
    /** @var array<int, string> */
    private array $mapCodes = [];

    protected function languageCode(
        int $languageId
    ): string {
        if (empty($this->mapCodes[$languageId])) {
            $this->mapCodes = $this->languageResolve();
        }

        return $this->mapCodes[$languageId];
    }

    /** @return array<int, string> */
    private function languageResolve(): array
    {
        return Language::query()
            ->where(LanguageSchema::IS_ACTIVE, true)
            ->pluck(LanguageSchema::CODE, LanguageSchema::ID)
            ->toArray();
    }
}
