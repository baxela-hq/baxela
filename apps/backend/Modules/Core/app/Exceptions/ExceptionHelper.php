<?php

namespace Modules\Core\Exceptions;

class ExceptionHelper
{
    public static function format_exception_response(BaseException $e): array
    {
        return [
            'message' => self::resolve_exception_message($e),
            'code' => $e->code,
            'errors' => $e->meta['errors'] ?? (object) [],
            'meta' => self::filter_exception_meta($e->meta),
        ];
    }

    public static function resolve_exception_message(BaseException $e): string
    {
        // 🔥 Hide unsafe errors (500)
        if (! $e->isSafe) {
            return __('core::errors.internal_server_error');
        }

        $key = self::resolve_translation_key($e->code);

        $translated = __($key);

        if ($translated !== $key) {
            return $translated;
        }

        // fallback → return code itself
        return $e->code;
    }

    public static function resolve_translation_key(string $code): string
    {
        // http.404 → core::errors.404
        if (str_starts_with($code, 'http.')) {
            return 'core::errors.'.str_replace('http.', '', $code);
        }

        // cart.checkout.empty → cart::errors.checkout.empty
        if (str_contains($code, '.')) {
            [$module, $rest] = explode('.', $code, 2);

            return "{$module}::errors.{$rest}";
        }

        return $code;
    }

    public static function filter_exception_meta(array $meta): array
    {
        unset($meta['errors']);

        return $meta;
    }
}
