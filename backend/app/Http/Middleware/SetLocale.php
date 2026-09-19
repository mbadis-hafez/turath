<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SetLocale
{
    private const SUPPORTED_LOCALES = ['ar', 'en'];

    /**
     * Set the app locale from the Accept-Language header (ar/en, default ar).
     */
    public function handle(Request $request, Closure $next): Response
    {
        $locale = strtolower((string) $request->header('Accept-Language', 'ar'));

        app()->setLocale(in_array($locale, self::SUPPORTED_LOCALES, true) ? $locale : 'ar');

        $response = $next($request);

        $response->headers->set('Content-Language', app()->getLocale());
        $response->headers->set('Vary', 'Accept-Language', false);

        return $response;
    }
}
