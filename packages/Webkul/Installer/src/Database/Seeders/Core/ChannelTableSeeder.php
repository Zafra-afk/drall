<?php

namespace Webkul\Installer\Database\Seeders\Core;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Webkul\Core\Concerns\SyncsPostgresSequences;

class ChannelTableSeeder extends Seeder
{
    use SyncsPostgresSequences;

    /**
     * Seed the application's database.
     *
     * @param  array  $parameters
     * @return void
     */
    public function run($parameters = [])
    {
        DB::table('channels')->delete();

        DB::table('channel_translations')->delete();

        DB::table('channel_currencies')->delete();

        DB::table('channel_locales')->delete();

        DB::table('channel_inventory_sources')->delete();

        $defaultLocale = $parameters['default_locale'] ?? config('app.locale');
        $defaultCurrency = $parameters['default_currency'] ?? config('app.currency');
        $allowedLocales = array_values(array_unique([
            $defaultLocale,
            ...($parameters['allowed_locales'] ?? []),
        ]));
        $allowedCurrencies = array_values(array_unique([
            $defaultCurrency,
            ...($parameters['allowed_currencies'] ?? []),
        ]));
        $defaultLocaleId = DB::table('locales')->where('code', $defaultLocale)->value('id');
        $defaultCurrencyId = DB::table('currencies')->where('code', $defaultCurrency)->value('id');

        if (! $defaultLocaleId || ! $defaultCurrencyId) {
            throw new \RuntimeException('The default channel locale or currency has not been seeded.');
        }

        DB::table('channels')->insert([
            [
                'id' => 1,
                'code' => 'default',
                'theme' => 'default',
                'hostname' => config('app.url'),
                'root_category_id' => 1,
                'default_locale_id' => $defaultLocaleId,
                'base_currency_id' => $defaultCurrencyId,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        foreach ($allowedLocales as $locale) {
            DB::table('channel_translations')->insert([
                [
                    'channel_id' => 1,
                    'locale' => $locale,
                    'name' => trans('installer::app.seeders.core.channels.name', [], $locale),
                    'home_seo' => json_encode([
                        'meta_title' => trans('installer::app.seeders.core.channels.meta-title', [], $locale),
                        'meta_description' => trans('installer::app.seeders.core.channels.meta-description', [], $locale),
                        'meta_keywords' => trans('installer::app.seeders.core.channels.meta-keywords', [], $locale),
                    ]),
                ],
            ]);
        }

        $currencyIds = DB::table('currencies')->whereIn('code', $allowedCurrencies)->pluck('id');

        foreach ($currencyIds as $currencyId) {
            DB::table('channel_currencies')->insert([
                [
                    'channel_id' => 1,
                    'currency_id' => $currencyId,
                ],
            ]);
        }

        $localeIds = DB::table('locales')->whereIn('code', $allowedLocales)->pluck('id');

        foreach ($localeIds as $localeId) {
            DB::table('channel_locales')->insert([
                [
                    'channel_id' => 1,
                    'locale_id' => $localeId,
                ],
            ]);
        }

        DB::table('channel_inventory_sources')->insert([
            'channel_id' => 1,
            'inventory_source_id' => 1,
        ]);

        $this->syncPostgresSequences(['channels']);
    }
}
