<?php

namespace Database\Seeders;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File as Filesystem;
use Illuminate\Support\Facades\Storage;
use Throwable;
use Webkul\Category\Models\Category;
use Webkul\Product\Repositories\ProductImageRepository;
use Webkul\Product\Repositories\ProductRepository;
use Webkul\Shop\Helpers\CatalogApiCache;

/**
 * Idempotent 30-product catalog with its own 10 categories and real photos.
 *
 * Never deletes anything: categories are found by slug and products by SKU,
 * and missing image files are restored from the packaged assets.
 */
class DrallCatalogSeeder extends Seeder
{
    private const IMAGE_ASSET_PATH = 'packages/Webkul/Installer/src/Resources/assets/images/seeders/drall-catalog';

    public function __construct(
        private ProductRepository $productRepository,
        private ProductImageRepository $productImageRepository,
        private CatalogApiCache $catalogApiCache,
    ) {}

    /**
     * Absolute path of the packaged photo for a catalog SKU, or null.
     */
    public static function imageSourceForSku(string $sku): ?string
    {
        $path = base_path(self::IMAGE_ASSET_PATH.'/'.$sku.'.jpg');

        return Filesystem::exists($path) ? $path : null;
    }

    public function run($parameters = []): void
    {
        // `db:seed` runs unguarded, which would mass-assign every attribute
        // (name, price, ...) onto the products table. Restore $fillable.
        Model::reguard();

        $parameters = data_get($parameters, 'parameters', $parameters);
        $channel = core()->getDefaultChannel();

        if (! $channel) {
            throw new \RuntimeException('A default channel is required to seed the Drall catalog.');
        }

        $inventorySourceId = DB::table('channel_inventory_sources')
            ->where('channel_id', $channel->id)
            ->value('inventory_source_id');
        $attributeFamilyId = DB::table('attribute_families')->where('code', 'default')->value('id');

        if (! $inventorySourceId || ! $attributeFamilyId) {
            throw new \RuntimeException('The default inventory source and attribute family are required.');
        }

        $locale = data_get($parameters, 'default_locale', core()->getDefaultLocaleCodeFromDefaultChannel());
        $categoryIds = $this->ensureCategories($locale);

        foreach ($this->products() as $definition) {
            $this->seedProduct(
                $definition,
                $categoryIds[$definition['category']],
                (int) $channel->id,
                (int) $inventorySourceId,
                (int) $attributeFamilyId,
                $locale,
            );
        }

        try {
            Artisan::call('indexer:index', ['--mode' => ['full'], '--quiet' => true]);
            $this->catalogApiCache->flush();
        } catch (Throwable $e) {
            $this->command?->warn('Indexing skipped: '.$e->getMessage());
        }
    }

    /**
     * @return array<string, int>
     */
    private function ensureCategories(string $locale): array
    {
        $rootCategoryId = core()->getDefaultChannel()?->root_category_id;

        if (! $rootCategoryId) {
            throw new \RuntimeException('A root category is required to seed the Drall catalog.');
        }

        $categoryLocale = DB::table('locales')->where('code', 'fr')->exists() ? 'fr' : $locale;
        $position = (int) Category::query()->where('parent_id', $rootCategoryId)->max('position');
        $ids = [];

        foreach ($this->categories() as $key => $name) {
            $slug = 'drall-'.$key;

            $existing = DB::table('category_translations')->where('slug', $slug)->first();

            if ($existing) {
                $ids[$key] = (int) $existing->category_id;

                continue;
            }

            $category = new Category([
                'position' => ++$position,
                'status' => true,
                'display_mode' => 'products_and_description',
                'parent_id' => $rootCategoryId,
            ]);

            $category->translateOrNew($categoryLocale)->fill([
                'name' => $name,
                'slug' => $slug,
                'description' => '',
                'meta_title' => $name,
                'meta_description' => '',
                'meta_keywords' => '',
            ]);
            $category->save();

            $ids[$key] = (int) $category->id;
        }

        return $ids;
    }

    private function seedProduct(
        array $definition,
        int $categoryId,
        int $channelId,
        int $inventorySourceId,
        int $attributeFamilyId,
        string $locale,
    ): void {
        $product = $this->productRepository->findOneByField('sku', $definition['sku']);

        if (! $product) {
            $product = $this->productRepository->create([
                'type' => 'simple',
                'attribute_family_id' => $attributeFamilyId,
                'sku' => $definition['sku'],
            ]);
        }

        $attributes = [
            'sku' => $definition['sku'],
            'product_number' => $definition['sku'],
            'name' => $definition['name'],
            'url_key' => $definition['url_key'],
            'short_description' => $definition['description'],
            'description' => $definition['description'],
            'meta_title' => $definition['name'].' | Drall',
            'meta_keywords' => 'drall, madagascar, '.$definition['category'],
            'meta_description' => $definition['description'],
            'price' => $definition['price'],
            'weight' => $definition['weight'],
            'status' => true,
            'visible_individually' => true,
            'new' => true,
            'featured' => $definition['featured'] ?? false,
            'guest_checkout' => true,
            'manage_stock' => true,
            'channel' => core()->getDefaultChannelCode(),
            'locale' => $locale,
            'channels' => [$channelId],
            'categories' => [$categoryId],
            'inventories' => [$inventorySourceId => $definition['qty']],
        ];

        $imageIds = $product->images()->pluck('id')->mapWithKeys(fn ($id) => [$id => $id])->all();

        if ($imageIds) {
            $attributes['images'] = ['files' => $imageIds];
        }

        $product = $this->productRepository->update($attributes, $product->id);

        $this->seedImage($product, $locale, $definition['name']);
    }

    /**
     * Create the product image, or restore its file when the row exists but
     * the file is missing from the storage disk.
     */
    private function seedImage($product, string $locale, string $name): void
    {
        $source = self::imageSourceForSku($product->sku);

        if (! $source) {
            return;
        }

        $existing = $product->images()->first();

        if ($existing) {
            if (! Storage::exists($existing->path)) {
                Storage::put($existing->path, Filesystem::get($source));
            }

            return;
        }

        $path = 'products/'.$product->id.'/drall-catalog.jpg';

        if (! Storage::put($path, Filesystem::get($source))) {
            throw new \RuntimeException('Unable to store the image for product '.$product->sku.'.');
        }

        $image = $this->productImageRepository->create([
            'type' => 'images',
            'path' => $path,
            'product_id' => $product->id,
            'position' => 1,
        ]);

        $image->translateOrNew($locale)->alt_text = $name;
        $image->save();
    }

    /**
     * @return array<string, string>
     */
    private function categories(): array
    {
        return [
            'fruits-legumes' => 'Fruits et légumes',
            'viandes' => 'Viandes',
            'produits-laitiers' => 'Produits laitiers',
            'cafe-the-cacao' => 'Café, thé et cacao',
            'mode-accessoires' => 'Mode et accessoires',
            'cuisine' => 'Cuisine',
            'beaute' => 'Beauté et soins',
            'sport-loisirs' => 'Sport et loisirs',
            'electromenager' => 'Électroménager',
            'papeterie' => 'Papeterie et bureau',
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function products(): array
    {
        $p = fn (string $s, string $n, string $c, string $d, int $price, float $w, int $q, bool $f = false) => [
            'sku' => 'drall-30-'.$s,
            'name' => $n,
            'url_key' => 'drall-'.$s,
            'category' => $c,
            'description' => $d,
            'price' => $price,
            'weight' => $w,
            'qty' => $q,
            'featured' => $f,
        ];

        return [
            $p('bananes', 'Bananes mûres (1 kg)', 'fruits-legumes', 'Bananes locales mûres à point, riches en énergie.', 4500, 1.0, 120, true),
            $p('tomates', 'Tomates fraîches (1 kg)', 'fruits-legumes', 'Tomates rouges du marché, idéales pour les salades et les sauces.', 5000, 1.0, 100),
            $p('carottes', 'Carottes (1 kg)', 'fruits-legumes', 'Carottes croquantes, cultivées sur les hauts plateaux.', 3500, 1.0, 100),
            $p('steak-boeuf', 'Steak de zébu (500 g)', 'viandes', 'Steak de zébu tendre, à griller ou à poêler.', 22000, 0.5, 40, true),
            $p('saucisses', 'Saucisses grillées (6 pièces)', 'viandes', 'Saucisses assaisonnées prêtes pour le barbecue.', 15000, 0.6, 50),
            $p('cotes-porc', 'Côtes de porc (1 kg)', 'viandes', 'Côtes de porc fraîches, parfaites pour un repas en famille.', 24000, 1.0, 35),
            $p('fromage', 'Fromage affiné', 'produits-laitiers', 'Fromage à pâte onctueuse, affiné en cave.', 18000, 0.3, 45),
            $p('yaourt', 'Yaourt nature (500 g)', 'produits-laitiers', 'Yaourt nature crémeux, sans sucre ajouté.', 3800, 0.5, 90),
            $p('lait', 'Lait frais en bouteille (1 L)', 'produits-laitiers', 'Lait frais entier en bouteille de verre.', 4200, 1.0, 80),
            $p('cafe-grains', 'Café en grains torréfié (500 g)', 'cafe-the-cacao', 'Grains de café arabica torréfiés, arômes intenses.', 28000, 0.5, 60, true),
            $p('the-vert', 'Thé vert en feuilles (100 g)', 'cafe-the-cacao', 'Thé vert en feuilles entières, saveur végétale et fraîche.', 12000, 0.1, 70),
            $p('cacao', 'Cabosses de cacao séchées', 'cafe-the-cacao', 'Cabosses de cacao de Madagascar, pour chocolat artisanal.', 16000, 0.4, 40),
            $p('baskets', 'Baskets blanches', 'mode-accessoires', 'Baskets blanches confortables pour tous les jours.', 120000, 0.8, 30, true),
            $p('lunettes', 'Lunettes de soleil', 'mode-accessoires', 'Lunettes de soleil à verres teintés, protection UV.', 45000, 0.1, 55),
            $p('montre', 'Montre à bracelet métal', 'mode-accessoires', 'Montre à quartz, bracelet en acier et cadran lumineux.', 150000, 0.2, 25),
            $p('casserole', 'Casserole avec couvercle', 'cuisine', 'Casserole en inox avec couvercle et anses robustes.', 55000, 1.2, 40),
            $p('poele', 'Poêle antiadhésive', 'cuisine', 'Poêle à fond épais pour une cuisson uniforme.', 48000, 1.0, 45),
            $p('couteau-cuisine', 'Set de couteaux de cuisine', 'cuisine', 'Couteau de chef et couteau d’office en acier inoxydable.', 65000, 0.5, 35),
            $p('mug', 'Mug en céramique artisanale', 'cuisine', 'Mug fait main en céramique émaillée, pièce unique.', 14000, 0.4, 60),
            $p('parfum', 'Flacon de parfum', 'beaute', 'Parfum aux notes boisées, présenté dans son coffret.', 95000, 0.3, 30),
            $p('rouge-levres', 'Rouge à lèvres', 'beaute', 'Rouge à lèvres longue tenue, teinte rouge vif.', 18000, 0.05, 80, true),
            $p('brosse-cheveux', 'Brosse à cheveux', 'beaute', 'Brosse à poils naturels qui démêle en douceur.', 15000, 0.2, 70),
            $p('velo', 'Vélo de ville', 'sport-loisirs', 'Vélo de ville léger avec cadre robuste, idéal au quotidien.', 650000, 14.0, 10, true),
            $p('sac-a-dos', 'Sac à dos de randonnée', 'sport-loisirs', 'Sac à dos résistant et spacieux pour vos randonnées.', 85000, 1.1, 25),
            $p('blender', 'Blender de cuisine', 'electromenager', 'Blender puissant pour smoothies, soupes et jus.', 120000, 2.0, 20),
            $p('bouilloire', 'Bouilloire électrique', 'electromenager', 'Bouilloire électrique 1,7 L à arrêt automatique.', 70000, 1.0, 30),
            $p('ventilateur', 'Ventilateur de table', 'electromenager', 'Ventilateur silencieux à trois vitesses.', 60000, 1.5, 28),
            $p('stylos', 'Stylo plume et plumes', 'papeterie', 'Stylo plume avec plume dorée pour une écriture fluide.', 25000, 0.1, 60),
            $p('ciseaux', 'Ciseaux de bureau', 'papeterie', 'Ciseaux à lames inox et poignées ergonomiques.', 6000, 0.1, 100),
            $p('agrafeuse', 'Agrafeuse de bureau', 'papeterie', 'Agrafeuse métallique robuste avec agrafes incluses.', 12000, 0.3, 50),
        ];
    }
}
