<?php

namespace Modules\Core\Contracts\Gateways\Fx;

use Modules\Core\Exceptions\BaseException;

/**
 * FX provider failures carry ONLY sanitized, stable error codes —
 * never the API key, never the raw provider body. The HTTP status
 * distinction matters (review F22): 401 = bad credential, 403 =
 * plan/feature restriction, everything else is a transport/document
 * failure.
 */
class FxProviderException extends BaseException
{
    // Dotted codes resolve to core::errors.* messages (ExceptionHelper).
    public const string AUTH_FAILED = 'core.provider_auth_failed';

    public const string FORBIDDEN = 'core.provider_forbidden';

    public const string HTTP_ERROR = 'core.provider_http_error';

    public const string TIMEOUT = 'core.provider_timeout';

    public const string INVALID_DOCUMENT = 'core.invalid_provider_document';

    public static function forCode(string $errorCode, int $httpStatus = 502): self
    {
        return new self($errorCode, $httpStatus);
    }
}
