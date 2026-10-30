<?php

namespace Modules\Core\Actions\Admin\Language;

use Modules\Core\Exceptions\Language\CannotDeactivateDefaultLanguageException;
use Modules\Core\Exceptions\Language\LanguageNotImplementedException;
use Modules\Core\Exceptions\Language\LastActiveLanguageException;
use Modules\Core\Models\Language;
use Modules\Core\Schemas\Language\LanguageSchema;

class UpdateLanguageAction
{
    /**
     * Toggle a language's availability. Guards: only locales with shipped
     * locale files can be activated (core.implemented_locales); the store's
     * default language and the last remaining active language can never be
     * deactivated — a store must always speak at least one language.
     */
    public function handle(string $id, array $data): Language
    {
        $language = Language::query()->findOrFail($id);

        $activating = array_key_exists(LanguageSchema::IS_ACTIVE, $data)
            && $data[LanguageSchema::IS_ACTIVE] === true
            && ! $language->{LanguageSchema::IS_ACTIVE};

        if ($activating && ! in_array($language->{LanguageSchema::CODE}, config('core.implemented_locales', []), true)) {
            throw new LanguageNotImplementedException;
        }

        $deactivating = array_key_exists(LanguageSchema::IS_ACTIVE, $data)
            && $data[LanguageSchema::IS_ACTIVE] === false
            && $language->{LanguageSchema::IS_ACTIVE};

        if ($deactivating) {
            if ($language->{LanguageSchema::IS_DEFAULT}) {
                throw new CannotDeactivateDefaultLanguageException;
            }

            $activeCount = Language::query()->where(LanguageSchema::IS_ACTIVE, true)->count();
            if ($activeCount <= 1) {
                throw new LastActiveLanguageException;
            }
        }

        $language->update([
            LanguageSchema::IS_ACTIVE => $data[LanguageSchema::IS_ACTIVE] ?? $language->{LanguageSchema::IS_ACTIVE},
        ]);

        return $language->refresh();
    }
}
