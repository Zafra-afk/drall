<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

/**
 * Fills in images that the database references but that are missing from the
 * public disk (for example after storage was wiped), and gives categories a
 * logo. Pictures are downloaded from Wikipedia (relevant to the product or
 * category name) with a picsum.photos fallback. Existing files are never touched.
 */
class RestoreMedia extends Command
{
    protected $signature = 'drall:restore-media {--limit=500 : Maximum number of images to download per run}';

    protected $description = 'Download images from the internet for products, categories and theme sections that have none';

    private int $downloaded = 0;

    private array $cache = [];

    public function handle(): int
    {
        $disk = Storage::disk('public');
        $limit = (int) $this->option('limit');

        // Products: re-create each missing file at the path stored in product_images.
        $rows = DB::table('product_images as pi')
            ->join('products as p', 'p.id', '=', 'pi.product_id')
            ->leftJoin('product_flat as pf', 'pf.product_id', '=', 'pi.product_id')
            ->groupBy('pi.path', 'p.sku')
            ->select('pi.path', DB::raw('COALESCE(MAX(pf.name), p.sku) as name'))
            ->get();

        foreach ($rows as $row) {
            if ($this->downloaded >= $limit) {
                break;
            }

            if (! $disk->exists($row->path)) {
                $this->store($row->path, $row->name, 800, 800);
            }
        }

        // Categories: set a logo when none exists or its file is missing.
        $categories = DB::table('categories as c')
            ->join('category_translations as ct', 'ct.category_id', '=', 'c.id')
            ->where('c.parent_id', '!=', 0)
            ->whereNotNull('c.parent_id')
            ->where('ct.locale', config('app.locale', 'fr'))
            ->select('c.id', 'c.logo_path', 'ct.name')
            ->get();

        foreach ($categories as $category) {
            if ($this->downloaded >= $limit) {
                break;
            }

            if ($category->logo_path && $disk->exists($category->logo_path)) {
                continue;
            }

            $path = 'category/'.$category->id.'/logo.webp';

            if ($this->store($path, $category->name, 600, 600)) {
                DB::table('categories')->where('id', $category->id)->update(['logo_path' => $path]);
            }
        }

        // Theme sections (carousels, banners): replace missing files in place.
        foreach (DB::table('theme_section_translations')->get(['id', 'options']) as $row) {
            preg_match_all('#themes\\\\?/default\\\\?/sections\\\\?/\d+\\\\?/[\w.-]+\.\w+#', (string) $row->options, $matches);

            foreach (array_unique($matches[0]) as $match) {
                $path = str_replace('\\/', '/', $match);

                if ($this->downloaded >= $limit || $disk->exists($path)) {
                    continue;
                }

                $this->store($path, null, 1400, 600, 'drall-'.crc32($path));
            }
        }

        $this->info("Downloaded {$this->downloaded} image(s).");

        return self::SUCCESS;
    }

    private function store(string $path, ?string $term, int $width, int $height, ?string $seed = null): bool
    {
        $bytes = ($term ? $this->wikipedia($term, $width) : null)
            ?? $this->get("https://picsum.photos/seed/".($seed ?? md5($path))."/{$width}/{$height}");

        if (! $bytes || ! ($image = @imagecreatefromstring($bytes))) {
            $this->warn("No image for {$path}");

            return false;
        }

        ob_start();

        if (strtolower(pathinfo($path, PATHINFO_EXTENSION)) === 'webp') {
            imagepalettetotruecolor($image);
            imagewebp($image, null, 82);
        } else {
            imagejpeg($image, null, 85);
        }

        Storage::disk('public')->put($path, ob_get_clean());
        $this->downloaded++;

        return true;
    }

    private function wikipedia(string $term, int $width): ?string
    {
        $query = trim(preg_replace('/\s+/', ' ', preg_replace('/[^\p{L}\p{N} ]/u', ' ', $term)));
        $query = implode(' ', array_slice(explode(' ', $query), 0, 4));

        $key = $query.$width;

        if (array_key_exists($key, $this->cache)) {
            return $this->cache[$key];
        }

        $bytes = null;

        foreach (['fr', 'en'] as $lang) {
            $response = Http::withUserAgent('DrallBot/1.0 (https://github.com/Zafra-afk/drall)')->timeout(20)->get("https://{$lang}.wikipedia.org/w/api.php", [
                'action' => 'query', 'generator' => 'search', 'gsrsearch' => $query, 'gsrlimit' => 5,
                'prop' => 'pageimages', 'piprop' => 'thumbnail', 'pithumbsize' => $width, 'format' => 'json',
            ]);

            $pages = collect($response->json('query.pages', []))
                ->sortBy('index')
                ->filter(fn ($page) => isset($page['thumbnail']['source'])
                    && preg_match('/\.(jpe?g|png|webp)(\?|$)/i', $page['thumbnail']['source']));

            foreach ($pages as $page) {
                $bytes = $this->get($page['thumbnail']['source']);

                if ($bytes) {
                    break 2;
                }
            }
        }

        return $this->cache[$key] = $bytes;
    }

    private function get(string $url): ?string
    {
        try {
            $response = Http::withUserAgent('DrallBot/1.0 (https://github.com/Zafra-afk/drall)')->timeout(30)->retry(2, 500, throw: false)->get($url);

            return $response->successful() ? $response->body() : null;
        } catch (\Throwable) {
            return null;
        }
    }
}
