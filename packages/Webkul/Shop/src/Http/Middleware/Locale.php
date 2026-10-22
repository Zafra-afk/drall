<?php

namespace Webkul\Shop\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Webkul\Core\Repositories\LocaleRepository;

class Locale
{
    /**
     * Create a middleware instance.
     *
     * @return void
     */
    public function __construct(protected LocaleRepository $localeRepository) {}

    /**
     * Handle an incoming request.
     *
     * @param  Request  $request
     * @return mixed
     */
    public function handle($request, Closure $next)
    {
        $channel = core()->getCurrentChannel();
        $allowedLocales = config('app.allowed_locales', ['fr']);
        $locales = array_values(array_intersect($channel->locales->pluck('code')->all(), $allowedLocales));

        $localeCode = core()->getRequestedLocaleCode('locale', false);

        if (! $localeCode || ! in_array($localeCode, $locales, true)) {
            $localeCode = session()->get('locale');
        }

        if (! $localeCode || ! in_array($localeCode, $locales, true)) {
            $localeCode = $channel->default_locale->code;
        }

        if (! in_array($localeCode, $locales, true)) {
            $localeCode = $locales[0] ?? config('app.locale', 'fr');
        }

        app()->setLocale($localeCode);
        session()->put('locale', $localeCode);
        unset($request['locale']);

        return $next($request);
    }
}
