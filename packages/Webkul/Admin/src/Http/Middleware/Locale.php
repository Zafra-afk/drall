<?php

namespace Webkul\Admin\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class Locale
{
    /**
     * Apply the selected supported locale to the admin request.
     */
    public function handle(Request $request, Closure $next): mixed
    {
        $allowedLocales = config('app.allowed_locales', ['fr']);
        $requestedLocale = $request->query('admin_locale');

        if (is_string($requestedLocale) && in_array($requestedLocale, $allowedLocales, true)) {
            $request->session()->put('admin.locale', $requestedLocale);
        }

        $locale = $request->session()->get('admin.locale', config('app.locale'));

        if (! in_array($locale, $allowedLocales, true)) {
            $locale = config('app.locale');
        }

        app()->setLocale($locale);

        return $next($request);
    }
}
