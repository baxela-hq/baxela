<?php

namespace Modules\Catalog\Support;

use Illuminate\Http\Request;
use Modules\Core\Contracts\Gateways\Core\CoreGatewayInterface;

/**
 * Public reads resolve the visitor language from the Accept-Language header
 * (base code, e.g. "fa-IR,fa;q=0.9" -> "fa"), falling back to the store's
 * default language.
 */
trait ResolvesPublicLanguage
{
    protected function resolvePublicLanguageId(?Request $request = null): ?int
    {
        $gateway = app(CoreGatewayInterface::class);

        $header = $request?->header('Accept-Language') ?? request()->header('Accept-Language');

        if (! empty($header)) {
            $code = trim(explode(',', $header)[0]);
            $code = explode(';', $code)[0];
            $code = explode('-', $code)[0];

            if ($code !== '') {
                $languageId = $gateway->getLanguageIdByCode($code);

                if (! is_null($languageId)) {
                    return $languageId;
                }
            }
        }

        $default = $gateway->getDefaultLanguage();

        return is_null($default) ? null : (int) $default->id;
    }
}
