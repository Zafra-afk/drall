<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Webkul\Product\Helpers\Indexers\Flat;

return new class extends Migration
{
    /**
     * Copy each product's url_key into every served locale that is missing one.
     *
     * The url_key product attribute is configured per-locale, but the demo catalog
     * only seeds it under a single locale (historically "en"). After the storefront
     * locale was switched to French, $product->url_key resolved to null, which
     * aborted every product page with a 404 and made the "Add to cart" button on
     * configurable product cards build a "/null" redirect URL. url_key is a slug,
     * so the existing value is simply copied into each locale that is missing one.
     */
    public function up(): void
    {
        $urlKeyAttributeId = DB::table('attributes')->where('code', 'url_key')->value('id');

        if (! $urlKeyAttributeId) {
            return;
        }

        $targetLocales = (array) (config('app.allowed_locales') ?: [config('app.locale')]);

        if (empty($targetLocales)) {
            return;
        }

        $sourceRows = DB::table('product_attribute_values')
            ->where('attribute_id', $urlKeyAttributeId)
            ->whereNotNull('text_value')
            ->get()
            ->mapWithKeys(fn ($row) => [$row->product_id => $row]);

        if ($sourceRows->isEmpty()) {
            return;
        }

        Schema::create('drall_product_url_key_backfill', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('product_id');
            $table->string('locale', 255);
            $table->unsignedInteger('attribute_id');
            $table->string('unique_id', 255)->unique();
        });

        $backfilledProductIds = [];

        foreach ($sourceRows as $productId => $source) {
            foreach ($targetLocales as $locale) {
                if ($source->locale === $locale) {
                    continue;
                }

                $hasLocale = DB::table('product_attribute_values')
                    ->where('attribute_id', $urlKeyAttributeId)
                    ->where('product_id', $productId)
                    ->where('locale', $locale)
                    ->exists();

                if ($hasLocale) {
                    continue;
                }

                $uniqueId = $locale.'|'.$productId.'|'.$urlKeyAttributeId;

                DB::table('product_attribute_values')->insert([
                    'locale' => $locale,
                    'channel' => null,
                    'text_value' => $source->text_value,
                    'boolean_value' => null,
                    'integer_value' => null,
                    'float_value' => null,
                    'datetime_value' => null,
                    'date_value' => null,
                    'json_value' => null,
                    'product_id' => $productId,
                    'attribute_id' => $urlKeyAttributeId,
                    'unique_id' => $uniqueId,
                ]);

                DB::table('drall_product_url_key_backfill')->insert([
                    'product_id' => $productId,
                    'locale' => $locale,
                    'attribute_id' => $urlKeyAttributeId,
                    'unique_id' => $uniqueId,
                ]);

                $backfilledProductIds[] = $productId;
            }
        }

        if (! empty($backfilledProductIds)) {
            $this->reindexFlat();
        }
    }

    /**
     * Remove the backfilled rows and refresh the flat index.
     */
    public function down(): void
    {
        if (! Schema::hasTable('drall_product_url_key_backfill')) {
            return;
        }

        $urlKeyAttributeId = DB::table('attributes')->where('code', 'url_key')->value('id');

        $uniqueIds = DB::table('drall_product_url_key_backfill')->pluck('unique_id');

        if ($urlKeyAttributeId && $uniqueIds->isNotEmpty()) {
            DB::table('product_attribute_values')
                ->where('attribute_id', $urlKeyAttributeId)
                ->whereIn('unique_id', $uniqueIds)
                ->delete();
        }

        Schema::dropIfExists('drall_product_url_key_backfill');

        $this->reindexFlat();
    }

    /**
     * Refresh the product flat so the backfilled slugs reach every reader.
     */
    protected function reindexFlat(): void
    {
        if (class_exists(Flat::class)) {
            app(Flat::class)->reindexFull();
        }
    }
};
