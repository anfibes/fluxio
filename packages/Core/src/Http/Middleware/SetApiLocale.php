<?php

namespace Fluxio\Core\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Sets the application locale from the Accept-Language header for API requests.
 *
 * Supported locales: en, it. Falls back to en when no match is found.
 * Accepts values like "it", "it-IT", "en-US,en;q=0.9".
 */
class SetApiLocale
{
    private const SUPPORTED = ['en', 'it'];
    private const FALLBACK  = 'en';

    public function handle(Request $request, Closure $next): Response
    {
        $header = $request->header('Accept-Language');
        $locale = self::FALLBACK;

        if (is_string($header) && $header !== '') {
            $first = explode(',', $header)[0] ?? '';
            $primary = strtolower(trim(explode('-', $first)[0] ?? ''));

            if (in_array($primary, self::SUPPORTED, true)) {
                $locale = $primary;
            }
        }

        app()->setLocale($locale);

        return $next($request);
    }
}
