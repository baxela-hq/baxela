<?php

namespace Modules\Core\Exceptions;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

class ExceptionMapper
{
    public function map(Throwable $e): BaseException
    {
        return match (true) {

            // ✅ Already mapped
            $e instanceof BaseException => $e,

            // 🔹 Validation
            $e instanceof ValidationException => new BaseException(
                code: 'http.422',
                httpStatus: 422,
                meta: ['errors' => $e->errors()]
            ),

            // 🔹 Auth
            $e instanceof AuthenticationException => new BaseException(
                code: 'http.401',
                httpStatus: 401
            ),

            // 🔹 Forbidden
            $e instanceof AuthorizationException => new BaseException(
                code: 'http.403',
                httpStatus: 403
            ),

            // 🔹 Not Found (Eloquent)
            $e instanceof ModelNotFoundException => new BaseException(
                code: 'http.404',
                httpStatus: 404
            ),

            // 🔹 Other HTTP exceptions
            $e instanceof HttpExceptionInterface => new BaseException(
                code: 'http.'.$e->getStatusCode(),
                httpStatus: $e->getStatusCode()
            ),

            // 🔥 Fallback
            default => new BaseException(
                code: 'http.500',
                httpStatus: 500,
                isSafe: false
            ),
        };
    }
}
