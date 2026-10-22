<?php

namespace Webkul\Installer\Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class FrenchProductContentSeeder extends Seeder
{
    /**
     * Locale-scoped product copy translated in the demo catalog data.
     *
     * @var string[]
     */
    private array $translatedAttributes = [
        'name',
        'short_description',
        'description',
        'meta_title',
        'meta_keywords',
        'meta_description',
    ];

    /**
     * Attribute types and their product value columns.
     *
     * @var array<string, string>
     */
    private array $attributeValueColumns = [
        'text' => 'text_value',
        'textarea' => 'text_value',
    ];

    /**
     * Add French copy for matching demo products without overwriting store data.
     */
    public function run(): void
    {
        $json = file_get_contents(dirname(__DIR__, 2).'/Data/demo-products.json');

        if ($json === false) {
            throw new RuntimeException('The demo product translation data could not be read.');
        }

        $demoProducts = json_decode($json, true, flags: JSON_THROW_ON_ERROR)['products'];
        $productsBySku = DB::table('products')->get(['id', 'sku'])->keyBy('sku');
        $attributes = DB::table('attributes')
            ->whereIn('code', $this->translatedAttributes)
            ->get()
            ->keyBy('code');
        $rows = [];

        foreach ($demoProducts as $demoProduct) {
            $product = $productsBySku->get($demoProduct['sku']);
            $translations = $demoProduct['translations']['fr'] ?? null;

            if (! $product || ! $translations) {
                continue;
            }

            foreach ($this->translatedAttributes as $code) {
                $attribute = $attributes->get($code);
                $value = $translations[$code] ?? null;

                if (! $attribute || ! $attribute->value_per_locale || $value === null) {
                    continue;
                }

                $channel = $attribute->value_per_channel ? 'default' : null;
                $uniqueId = collect([
                    $channel,
                    'fr',
                    $product->id,
                    $attribute->id,
                ])->filter(fn ($part) => $part !== null)->implode('|');

                if (DB::table('product_attribute_values')->where('unique_id', $uniqueId)->exists()) {
                    continue;
                }

                $column = $this->attributeValueColumns[$attribute->type] ?? null;

                if (! $column) {
                    continue;
                }

                $rows[] = [
                    'locale' => 'fr',
                    'channel' => $channel,
                    'text_value' => $column === 'text_value' ? $value : null,
                    'boolean_value' => null,
                    'integer_value' => null,
                    'float_value' => null,
                    'datetime_value' => null,
                    'date_value' => null,
                    'json_value' => null,
                    'product_id' => $product->id,
                    'attribute_id' => $attribute->id,
                    'unique_id' => $uniqueId,
                ];
            }
        }

        foreach (array_chunk($rows, 500) as $chunk) {
            DB::table('product_attribute_values')->insertOrIgnore($chunk);
        }
    }
}
