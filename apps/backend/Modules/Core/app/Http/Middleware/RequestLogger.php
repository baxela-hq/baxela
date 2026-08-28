<?php

namespace Modules\Core\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class RequestLogger
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        return $next($request);
    }

    /**
     * Handle tasks after the response has been sent to the browser.
     * This does NOT slow down the user's experience.
     */
    public function terminate(Request $request, Response $response): void
    {
        // 1. Don't log if you want to exclude certain routes (e.g., health checks)
        if ($request->is('api/health')) {
            return;
        }

        // 2. Filter sensitive data (Passwords, Tokens, etc.)
        $sensitiveKeys = ['password', 'password_confirmation', 'token', 'api_key', 'credit_card'];
        $input = $request->except($sensitiveKeys);

        // 3. Truncate response content to prevent massive logs
        // Only log the first 500 characters, or ignore binary files
        $responseContent = $response->getContent();

        // Check if response is JSON or HTML (avoid logging binary files/images)
        $contentType = $response->headers->get('Content-Type');

        if (str_contains($contentType, 'application/json') || str_contains($contentType, 'text/html')) {
            $responseContent = substr($responseContent, 0, 1000); // Limit length
        } else {
            $responseContent = '[Binary Data]';
        }

        $log = [
            'URI' => $request->getUri(),
            'METHOD' => $request->getMethod(),
            'REQUEST_BODY' => $input,
            'STATUS_CODE' => $response->getStatusCode(),
            'RESPONSE' => $responseContent,
        ];

        // Use a specific channel for logs to avoid cluttering your main laravel.log
        Log::channel('daily')->info('API Request Log', $log);
    }
}
