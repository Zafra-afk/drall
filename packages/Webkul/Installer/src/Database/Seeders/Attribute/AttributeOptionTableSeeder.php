<?php

namespace Webkul\Installer\Database\Seeders\Attribute;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Webkul\Core\Concerns\SyncsPostgresSequences;

class AttributeOptionTableSeeder extends Seeder
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
        DB::table('attribute_options')->delete();

        DB::table('attribute_option_translations')->delete();

        $defaultLocale = $parameters['default_locale'] ?? config('app.locale');

        DB::table('attribute_options')->insert([
            [
                'id' => 1,
                'admin_name' => trans('installer::app.seeders.attribute.attribute-options.red', [], $defaultLocale),
                'sort_order' => 1,
                'attribute_id' => 23,
            ], [
                'id' => 2,
                'admin_name' => trans('installer::app.seeders.attribute.attribute-options.green', [], $defaultLocale),
                'sort_order' => 2,
                'attribute_id' => 23,
            ], [
                'id' => 3,
                'admin_name' => trans('installer::app.seeders.attribute.attribute-options.yellow', [], $defaultLocale),
                'sort_order' => 3,
                'attribute_id' => 23,
            ], [
                'id' => 4,
                'admin_name' => trans('installer::app.seeders.attribute.attribute-options.black', [], $defaultLocale),
                'sort_order' => 4,
                'attribute_id' => 23,
            ], [
                'id' => 5,
                'admin_name' => trans('installer::app.seeders.attribute.attribute-options.white', [], $defaultLocale),
                'sort_order' => 5,
                'attribute_id' => 23,
            ], [
                'id' => 6,
                'admin_name' => trans('installer::app.seeders.attribute.attribute-options.s', [], $defaultLocale),
                'sort_order' => 1,
                'attribute_id' => 24,
            ], [
                'id' => 7,
                'admin_name' => trans('installer::app.seeders.attribute.attribute-options.m', [], $defaultLocale),
                'sort_order' => 2,
                'attribute_id' => 24,
            ], [
                'id' => 8,
                'admin_name' => trans('installer::app.seeders.attribute.attribute-options.l', [], $defaultLocale),
                'sort_order' => 3,
                'attribute_id' => 24,
            ], [
                'id' => 9,
                'admin_name' => trans('installer::app.seeders.attribute.attribute-options.xl', [], $defaultLocale),
                'sort_order' => 4,
                'attribute_id' => 24,
            ], [
                'id' => 10,
                'admin_name' => trans('installer::app.seeders.attribute.attribute-options.nike', [], $defaultLocale),
                'sort_order' => 1,
                'attribute_id' => 25,
            ], [
                'id' => 11,
                'admin_name' => trans('installer::app.seeders.attribute.attribute-options.adidas', [], $defaultLocale),
                'sort_order' => 2,
                'attribute_id' => 25,
            ], [
                'id' => 12,
                'admin_name' => trans('installer::app.seeders.attribute.attribute-options.puma', [], $defaultLocale),
                'sort_order' => 3,
                'attribute_id' => 25,
            ], [
                'id' => 13,
                'admin_name' => trans('installer::app.seeders.attribute.attribute-options.apple', [], $defaultLocale),
                'sort_order' => 4,
                'attribute_id' => 25,
            ], [
                'id' => 14,
                'admin_name' => trans('installer::app.seeders.attribute.attribute-options.samsung', [], $defaultLocale),
                'sort_order' => 5,
                'attribute_id' => 25,
            ], [
                'id' => 15,
                'admin_name' => trans('installer::app.seeders.attribute.attribute-options.sony', [], $defaultLocale),
                'sort_order' => 6,
                'attribute_id' => 25,
            ], [
                'id' => 16,
                'admin_name' => trans('installer::app.seeders.attribute.attribute-options.zara', [], $defaultLocale),
                'sort_order' => 7,
                'attribute_id' => 25,
            ], [
                'id' => 17,
                'admin_name' => trans('installer::app.seeders.attribute.attribute-options.generic', [], $defaultLocale),
                'sort_order' => 8,
                'attribute_id' => 25,
            ],
        ]);

        $locales = $parameters['allowed_locales'] ?? [$defaultLocale];

        foreach ($locales as $locale) {
            DB::table('attribute_option_translations')->insert([
                [
                    'locale' => $locale,
                    'label' => trans('installer::app.seeders.attribute.attribute-options.red', [], $locale),
                    'attribute_option_id' => 1,
                ], [
                    'locale' => $locale,
                    'label' => trans('installer::app.seeders.attribute.attribute-options.green', [], $locale),
                    'attribute_option_id' => 2,
                ], [
                    'locale' => $locale,
                    'label' => trans('installer::app.seeders.attribute.attribute-options.yellow', [], $locale),
                    'attribute_option_id' => 3,
                ], [
                    'locale' => $locale,
                    'label' => trans('installer::app.seeders.attribute.attribute-options.black', [], $locale),
                    'attribute_option_id' => 4,
                ], [
                    'locale' => $locale,
                    'label' => trans('installer::app.seeders.attribute.attribute-options.white', [], $locale),
                    'attribute_option_id' => 5,
                ], [
                    'locale' => $locale,
                    'label' => trans('installer::app.seeders.attribute.attribute-options.s', [], $locale),
                    'attribute_option_id' => 6,
                ], [
                    'locale' => $locale,
                    'label' => trans('installer::app.seeders.attribute.attribute-options.m', [], $locale),
                    'attribute_option_id' => 7,
                ], [
                    'locale' => $locale,
                    'label' => trans('installer::app.seeders.attribute.attribute-options.l', [], $locale),
                    'attribute_option_id' => 8,
                ], [
                    'locale' => $locale,
                    'label' => trans('installer::app.seeders.attribute.attribute-options.xl', [], $locale),
                    'attribute_option_id' => 9,
                ], [
                    'locale' => $locale,
                    'label' => trans('installer::app.seeders.attribute.attribute-options.nike', [], $locale),
                    'attribute_option_id' => 10,
                ], [
                    'locale' => $locale,
                    'label' => trans('installer::app.seeders.attribute.attribute-options.adidas', [], $locale),
                    'attribute_option_id' => 11,
                ], [
                    'locale' => $locale,
                    'label' => trans('installer::app.seeders.attribute.attribute-options.puma', [], $locale),
                    'attribute_option_id' => 12,
                ], [
                    'locale' => $locale,
                    'label' => trans('installer::app.seeders.attribute.attribute-options.apple', [], $locale),
                    'attribute_option_id' => 13,
                ], [
                    'locale' => $locale,
                    'label' => trans('installer::app.seeders.attribute.attribute-options.samsung', [], $locale),
                    'attribute_option_id' => 14,
                ], [
                    'locale' => $locale,
                    'label' => trans('installer::app.seeders.attribute.attribute-options.sony', [], $locale),
                    'attribute_option_id' => 15,
                ], [
                    'locale' => $locale,
                    'label' => trans('installer::app.seeders.attribute.attribute-options.zara', [], $locale),
                    'attribute_option_id' => 16,
                ], [
                    'locale' => $locale,
                    'label' => trans('installer::app.seeders.attribute.attribute-options.generic', [], $locale),
                    'attribute_option_id' => 17,
                ],
            ]);
        }

        $this->syncPostgresSequences(['attribute_options']);
    }
}
