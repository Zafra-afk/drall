<?php

namespace Webkul\Installer\Console\Commands;

use Illuminate\Console\Command;
use Webkul\Installer\Database\Seeders\Category\SupermarketCategorySeeder;
use Webkul\Installer\Database\Seeders\FrenchProductContentSeeder;
use Webkul\Product\Helpers\Indexers\Flat;

class LocalizeCatalog extends Command
{
    /**
     * The console command signature.
     *
     * @var string
     */
    protected $signature = 'bagisto:catalog:localize-french';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Add French catalog translations and supermarket categories without replacing existing products';

    /**
     * Apply the catalog translations and refresh product flat rows.
     */
    public function handle(
        SupermarketCategorySeeder $categorySeeder,
        FrenchProductContentSeeder $productContentSeeder,
        Flat $flatIndexer,
    ): int {
        $categorySeeder->run();
        $productContentSeeder->run();
        $flatIndexer->reindexFull();

        $this->components->info('French category and demo-product translations have been applied.');

        return self::SUCCESS;
    }
}
