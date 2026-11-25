<?php

namespace App\Console\Commands;

use Database\Seeders\DrallDemoProductsSeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File as Filesystem;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Non-destructive media repair.
 *
 * Restores demo product images whose files are missing from the storage disk
 * and reports every other database-referenced file that is missing. It never
 * deletes or rewrites database rows and never fails the caller.
 */
class RepairMedia extends Command
{
    protected $signature = 'drall:repair-media';

    protected $description = 'Restore missing demo product images and report other missing media files';

    public function handle(): int
    {
        try {
            $this->restoreDemoProductImages();
            $this->reportMissing('categories', ['logo_path', 'banner_path']);
            $this->reportMissing('channels', ['logo', 'favicon']);
        } catch (Throwable $e) {
            $this->warn('Media repair skipped: '.$e->getMessage());
        }

        return self::SUCCESS;
    }

    private function restoreDemoProductImages(): void
    {
        if (! Schema::hasTable('product_images') || ! Schema::hasTable('products')) {
            return;
        }

        $seeder = app(DrallDemoProductsSeeder::class);

        $call = function (string $method) use ($seeder) {
            $reflection = new \ReflectionMethod($seeder, $method);
            $reflection->setAccessible(true);

            return $reflection->invoke($seeder);
        };

        $sources = $call('imageSources');
        $indexBySku = [];

        foreach ($call('products') as $index => $definition) {
            $indexBySku[$definition['sku']] = $index;
        }

        if (! $sources) {
            $this->warn('No demo image assets found; nothing to restore.');

            return;
        }

        $restored = 0;
        $unknown = 0;

        $rows = DB::table('product_images')
            ->join('products', 'products.id', '=', 'product_images.product_id')
            ->select('product_images.path', 'products.sku')
            ->get();

        foreach ($rows as $row) {
            if (! $row->path || Storage::exists($row->path)) {
                continue;
            }

            if (! isset($indexBySku[$row->sku])) {
                $unknown++;

                continue;
            }

            $source = $sources[$indexBySku[$row->sku] % count($sources)];

            if (Filesystem::exists($source) && Storage::put($row->path, Filesystem::get($source))) {
                $restored++;
            }
        }

        $this->info("Product images restored: {$restored}; missing non-demo product images: {$unknown}");
    }

    private function reportMissing(string $table, array $columns): void
    {
        foreach ($columns as $column) {
            if (! Schema::hasColumn($table, $column)) {
                continue;
            }

            $missing = DB::table($table)
                ->whereNotNull($column)
                ->where($column, '!=', '')
                ->pluck($column)
                ->filter(fn ($path) => ! Storage::exists($path))
                ->count();

            if ($missing > 0) {
                $this->warn("{$table}.{$column}: {$missing} referenced file(s) missing from storage");
            }
        }
    }
}
