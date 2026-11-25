<?php

namespace Database\Seeders;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File as Filesystem;
use Illuminate\Support\Facades\Storage;
use Webkul\Category\Models\Category;
use Webkul\Installer\Database\Seeders\Category\SupermarketCategorySeeder;
use Webkul\Product\Contracts\Product;
use Webkul\Product\Repositories\ProductImageRepository;
use Webkul\Product\Repositories\ProductRepository;
use Webkul\Shop\Helpers\CatalogApiCache;

class DrallDemoProductsSeeder extends Seeder
{
    /**
     * Directory containing the package's local demonstration images.
     */
    private const IMAGE_ASSET_PATH = 'packages/Webkul/Installer/src/Resources/assets/images/seeders/products';

    /**
     * Product repository used to create and update catalog records.
     */
    private ProductRepository $productRepository;

    /**
     * Product image repository used to persist demonstration media.
     */
    private ProductImageRepository $productImageRepository;

    /**
     * Catalog cache helper used after rebuilding the product indexes.
     */
    private CatalogApiCache $catalogApiCache;

    /**
     * Create a new Drall demonstration product seeder.
     */
    public function __construct(
        ProductRepository $productRepository,
        ProductImageRepository $productImageRepository,
        CatalogApiCache $catalogApiCache,
    ) {
        $this->productRepository = $productRepository;
        $this->productImageRepository = $productImageRepository;
        $this->catalogApiCache = $catalogApiCache;
    }

    /**
     * Seed Drall's idempotent demonstration catalog.
     *
     * @param  array  $parameters
     */
    public function run($parameters = []): void
    {
        // `db:seed` runs unguarded, which would mass-assign every attribute
        // (name, price, ...) onto the products table. Restore $fillable.
        Model::reguard();

        $parameters = data_get($parameters, 'parameters', $parameters);
        $channel = core()->getDefaultChannel();

        if (! $channel) {
            throw new \RuntimeException('A default channel is required to seed Drall demonstration products.');
        }

        $inventorySourceId = DB::table('channel_inventory_sources')
            ->where('channel_id', $channel->id)
            ->value('inventory_source_id');
        $attributeFamilyId = DB::table('attribute_families')
            ->where('code', 'default')
            ->value('id');

        if (! $inventorySourceId || ! $attributeFamilyId) {
            throw new \RuntimeException('The default inventory source and attribute family are required to seed Drall products.');
        }

        $locale = data_get(
            $parameters,
            'default_locale',
            core()->getDefaultLocaleCodeFromDefaultChannel()
        );
        $categoryIds = $this->ensureCategories($locale);
        $imageSources = $this->imageSources();

        foreach ($this->products() as $index => $definition) {
            $this->seedProduct(
                $definition,
                $categoryIds[$definition['category']],
                (int) $channel->id,
                (int) $inventorySourceId,
                (int) $attributeFamilyId,
                $locale,
                $imageSources[$index % max(count($imageSources), 1)] ?? null,
                $index + 1,
            );
        }

        Artisan::call('indexer:index', [
            '--mode' => ['full'],
            '--quiet' => true,
        ]);

        $this->catalogApiCache->flush();
    }

    /**
     * Return the Drall demonstration product catalog.
     *
     * @return array<int, array<string, mixed>>
     */
    protected function products(): array
    {
        return [
            [
                'sku' => 'drall-demo-smartphone-4g',
                'name' => 'Smartphone Drall 4G 128 Go',
                'url_key' => 'smartphone-drall-4g-128-go',
                'description' => 'Smartphone 4G fiable avec écran lumineux, 128 Go de stockage et double appareil photo.',
                'price' => 899000,
                'weight' => 0.19,
                'qty' => 18,
                'category' => 'mobile',
                'featured' => true,
            ],
            [
                'sku' => 'drall-demo-ecouteurs-bluetooth',
                'name' => 'Écouteurs Bluetooth Autonomie 24 h',
                'url_key' => 'ecouteurs-bluetooth-autonomie-24-h',
                'description' => 'Écouteurs sans fil compacts avec boîtier de recharge et jusqu’à 24 heures d’autonomie.',
                'price' => 125000,
                'weight' => 0.08,
                'qty' => 32,
                'category' => 'audio',
                'featured' => true,
            ],
            [
                'sku' => 'drall-demo-chargeur-usbc',
                'name' => 'Chargeur rapide USB-C 33 W',
                'url_key' => 'chargeur-rapide-usbc-33w',
                'description' => 'Chargeur secteur USB-C 33 W pour smartphones, tablettes et petits appareils compatibles.',
                'price' => 65000,
                'weight' => 0.16,
                'qty' => 40,
                'category' => 'mobile',
                'featured' => false,
            ],
            [
                'sku' => 'drall-demo-batterie-externe',
                'name' => 'Batterie externe 20 000 mAh',
                'url_key' => 'batterie-externe-20000-mah',
                'description' => 'Batterie externe haute capacité avec deux ports USB et indicateur de charge.',
                'price' => 149000,
                'weight' => 0.42,
                'qty' => 25,
                'category' => 'mobile',
                'featured' => false,
            ],
            [
                'sku' => 'drall-demo-ordinateur-portable',
                'name' => 'Ordinateur portable bureautique 15,6 pouces',
                'url_key' => 'ordinateur-portable-bureautique-156-pouces',
                'description' => 'Ordinateur portable polyvalent pour la bureautique, les études et les usages quotidiens.',
                'price' => 1899000,
                'weight' => 1.75,
                'qty' => 8,
                'category' => 'laptops',
                'featured' => true,
            ],
            [
                'sku' => 'drall-demo-tablette-educative',
                'name' => 'Tablette éducative 10 pouces 64 Go',
                'url_key' => 'tablette-educative-10-pouces-64-go',
                'description' => 'Tablette légère de 10 pouces adaptée à l’apprentissage, à la lecture et au divertissement.',
                'price' => 649000,
                'weight' => 0.48,
                'qty' => 14,
                'category' => 'laptops',
                'featured' => true,
            ],
            [
                'sku' => 'drall-demo-panier-raphia',
                'name' => 'Panier artisanal en raphia naturel',
                'url_key' => 'panier-artisanal-raphia-naturel',
                'description' => 'Panier tressé à la main en raphia naturel, pratique pour le rangement et les courses.',
                'price' => 95000,
                'weight' => 0.65,
                'qty' => 20,
                'category' => 'local-products',
                'featured' => false,
            ],
            [
                'sku' => 'drall-demo-miel-litchi',
                'name' => 'Miel de litchi naturel 500 g',
                'url_key' => 'miel-de-litchi-naturel-500g',
                'description' => 'Miel de litchi naturel, récolté localement et conditionné en pot de 500 grammes.',
                'price' => 45000,
                'weight' => 0.75,
                'qty' => 24,
                'category' => 'local-products',
                'featured' => false,
            ],
            [
                'sku' => 'drall-demo-riz-rouge',
                'name' => 'Riz rouge de Madagascar 5 kg',
                'url_key' => 'riz-rouge-de-madagascar-5kg',
                'description' => 'Riz rouge de Madagascar aux grains savoureux, conditionné en sac de 5 kg.',
                'price' => 32000,
                'weight' => 5.0,
                'qty' => 60,
                'category' => 'rice-cereals',
                'featured' => true,
            ],
            [
                'sku' => 'drall-demo-huile-tournesol',
                'name' => 'Huile de tournesol 1 litre',
                'url_key' => 'huile-de-tournesol-1-litre',
                'description' => 'Huile de tournesol polyvalente pour la cuisson et les préparations quotidiennes.',
                'price' => 10500,
                'weight' => 0.95,
                'qty' => 50,
                'category' => 'oils-condiments',
                'featured' => false,
            ],
            [
                'sku' => 'drall-demo-poivre-madagascar',
                'name' => 'Poivre noir de Madagascar 100 g',
                'url_key' => 'poivre-noir-de-madagascar-100g',
                'description' => 'Poivre noir parfumé de Madagascar, idéal pour relever les plats du quotidien.',
                'price' => 18000,
                'weight' => 0.12,
                'qty' => 35,
                'category' => 'spices-legumes',
                'featured' => false,
            ],
            [
                'sku' => 'drall-demo-jus-mangue',
                'name' => 'Jus de mangue naturel 1 litre',
                'url_key' => 'jus-de-mangue-naturel-1-litre',
                'description' => 'Jus de mangue naturel au goût fruité, conditionné en bouteille d’un litre.',
                'price' => 12000,
                'weight' => 1.1,
                'qty' => 45,
                'category' => 'juice',
                'featured' => true,
            ],
            [
                'sku' => 'drall-demo-eau-minerale',
                'name' => 'Eau minérale naturelle 1,5 litre',
                'url_key' => 'eau-minerale-naturelle-15-litre',
                'description' => 'Eau minérale naturelle en bouteille de 1,5 litre pour la maison et les déplacements.',
                'price' => 2500,
                'weight' => 1.55,
                'qty' => 100,
                'category' => 'water',
                'featured' => false,
            ],
            [
                'sku' => 'drall-demo-poulet-fermier',
                'name' => 'Poulet fermier entier',
                'url_key' => 'poulet-fermier-entier',
                'description' => 'Poulet fermier entier, préparé avec soin pour les repas en famille.',
                'price' => 28000,
                'weight' => 1.6,
                'qty' => 25,
                'category' => 'poultry',
                'featured' => true,
            ],
            [
                'sku' => 'drall-demo-filet-poisson',
                'name' => 'Filet de poisson frais 500 g',
                'url_key' => 'filet-de-poisson-frais-500g',
                'description' => 'Filet de poisson frais de 500 grammes, prêt à cuisiner.',
                'price' => 24000,
                'weight' => 0.5,
                'qty' => 18,
                'category' => 'fish-seafood',
                'featured' => false,
            ],
            [
                'sku' => 'drall-demo-pain-complet',
                'name' => 'Pain complet artisanal',
                'url_key' => 'pain-complet-artisanal',
                'description' => 'Pain complet artisanal à la mie généreuse, idéal pour le petit déjeuner.',
                'price' => 5000,
                'weight' => 0.45,
                'qty' => 40,
                'category' => 'bread',
                'featured' => true,
            ],
            [
                'sku' => 'drall-demo-croissant-beurre',
                'name' => 'Croissant pur beurre',
                'url_key' => 'croissant-pur-beurre',
                'description' => 'Croissant pur beurre croustillant, préparé pour les petits déjeuners gourmands.',
                'price' => 2500,
                'weight' => 0.08,
                'qty' => 70,
                'category' => 'pastries',
                'featured' => false,
            ],
            [
                'sku' => 'drall-demo-savon-coco',
                'name' => 'Savon naturel à la noix de coco',
                'url_key' => 'savon-naturel-noix-de-coco',
                'description' => 'Savon naturel à la noix de coco pour une routine quotidienne douce et parfumée.',
                'price' => 7000,
                'weight' => 0.1,
                'qty' => 55,
                'category' => 'soap',
                'featured' => false,
            ],
            [
                'sku' => 'drall-demo-lessive-parfumee',
                'name' => 'Lessive parfumée 2 kg',
                'url_key' => 'lessive-parfumee-2kg',
                'description' => 'Lessive parfumée en format familial de 2 kg pour le linge du quotidien.',
                'price' => 18500,
                'weight' => 2.1,
                'qty' => 30,
                'category' => 'laundry',
                'featured' => false,
            ],
            [
                'sku' => 'drall-demo-cahier-scolaire',
                'name' => 'Cahier scolaire 200 pages',
                'url_key' => 'cahier-scolaire-200-pages',
                'description' => 'Cahier scolaire de 200 pages pour les cours, les notes et les exercices.',
                'price' => 12000,
                'weight' => 0.55,
                'qty' => 80,
                'category' => 'notebooks',
                'featured' => true,
            ],
        ];
    }

    /**
     * Return package image paths in a deterministic order.
     *
     * @return array<int, string>
     */
    protected function imageSources(): array
    {
        $directory = base_path(self::IMAGE_ASSET_PATH);

        if (! Filesystem::isDirectory($directory)) {
            return [];
        }

        $paths = array_map(
            fn ($file) => $file->getPathname(),
            Filesystem::allFiles($directory)
        );

        sort($paths);

        return $paths;
    }

    /**
     * Find or create the categories required by the demonstration catalog.
     *
     * @return array<string, int>
     */
    private function ensureCategories(string $locale): array
    {
        app(SupermarketCategorySeeder::class)->run();

        $rootCategoryId = core()->getDefaultChannel()?->root_category_id;

        if (! $rootCategoryId) {
            throw new \RuntimeException('A root category is required to seed Drall demonstration products.');
        }

        $categoryLocale = DB::table('locales')->where('code', 'fr')->exists() ? 'fr' : $locale;
        $position = (int) Category::query()->where('parent_id', $rootCategoryId)->max('position');
        $categoryIds = [];

        foreach ($this->categoryDefinitions() as $key => $definition) {
            $translation = DB::table('category_translations')
                ->whereIn('slug', [$definition['slug'], ...$definition['aliases']])
                ->orWhereIn('name', $definition['names'])
                ->first();

            if ($translation) {
                $categoryIds[$key] = (int) $translation->category_id;

                continue;
            }

            $category = new Category([
                'position' => ++$position,
                'status' => true,
                'display_mode' => 'products_and_description',
                'parent_id' => $rootCategoryId,
            ]);

            $category->translateOrNew($categoryLocale)->fill([
                'name' => $definition['name'],
                'slug' => $definition['slug'],
                'description' => '',
                'meta_title' => $definition['name'],
                'meta_description' => '',
                'meta_keywords' => '',
            ]);
            $category->save();

            $categoryIds[$key] = (int) $category->id;
        }

        return $categoryIds;
    }

    /**
     * Return the category definitions used by the demonstration products.
     *
     * @return array<string, array<string, mixed>>
     */
    private function categoryDefinitions(): array
    {
        return [
            'mobile' => [
                'name' => 'Téléphones Mobiles et Accessoires',
                'slug' => 'telephones-mobiles-et-accessoires',
                'aliases' => ['mobile-phones-accessories', 'telephones-et-accessoires'],
                'names' => ['Téléphones Mobiles et Accessoires', 'Téléphones et accessoires', 'Mobile Phones & Accessories'],
            ],
            'laptops' => [
                'name' => 'Ordinateurs Portables et Tablettes',
                'slug' => 'ordinateurs-portables-et-tablettes',
                'aliases' => ['laptops-tablets'],
                'names' => ['Ordinateurs Portables et Tablettes', 'Ordinateurs portables et tablettes', 'Laptops & Tablets'],
            ],
            'audio' => [
                'name' => 'Appareils Audio',
                'slug' => 'appareils-audio',
                'aliases' => ['audio-devices'],
                'names' => ['Appareils Audio', 'Appareils audio', 'Audio Devices'],
            ],
            'local-products' => [
                'name' => 'Produits locaux',
                'slug' => 'produits-locaux',
                'aliases' => ['local-products'],
                'names' => ['Produits locaux', 'Local Products'],
            ],
            'rice-cereals' => [
                'name' => 'Riz et céréales',
                'slug' => 'riz-cereales',
                'aliases' => ['rice-cereals'],
                'names' => ['Riz et céréales', 'Rice & Cereals'],
            ],
            'oils-condiments' => [
                'name' => 'Huiles et condiments',
                'slug' => 'huiles-condiments',
                'aliases' => ['oils-condiments'],
                'names' => ['Huiles et condiments', 'Oils & Condiments'],
            ],
            'spices-legumes' => [
                'name' => 'Épices et légumineuses',
                'slug' => 'epices-legumineuses',
                'aliases' => ['spices-legumes'],
                'names' => ['Épices et légumineuses', 'Spices & Legumes'],
            ],
            'juice' => [
                'name' => 'Jus',
                'slug' => 'jus',
                'aliases' => ['juice'],
                'names' => ['Jus', 'Juice'],
            ],
            'water' => [
                'name' => 'Eau',
                'slug' => 'eau',
                'aliases' => ['water'],
                'names' => ['Eau', 'Water'],
            ],
            'poultry' => [
                'name' => 'Volailles',
                'slug' => 'volailles',
                'aliases' => ['poultry'],
                'names' => ['Volailles', 'Poultry'],
            ],
            'fish-seafood' => [
                'name' => 'Poissons et fruits de mer',
                'slug' => 'poissons-fruits-de-mer',
                'aliases' => ['fish-seafood'],
                'names' => ['Poissons et fruits de mer', 'Fish & Seafood'],
            ],
            'bread' => [
                'name' => 'Pains',
                'slug' => 'pains',
                'aliases' => ['bread'],
                'names' => ['Pains', 'Bread'],
            ],
            'pastries' => [
                'name' => 'Viennoiseries',
                'slug' => 'viennoiseries',
                'aliases' => ['pastries'],
                'names' => ['Viennoiseries', 'Pastries'],
            ],
            'soap' => [
                'name' => 'Savons',
                'slug' => 'savons',
                'aliases' => ['soap'],
                'names' => ['Savons', 'Soap'],
            ],
            'laundry' => [
                'name' => 'Lessive',
                'slug' => 'lessive',
                'aliases' => ['laundry'],
                'names' => ['Lessive', 'Laundry'],
            ],
            'notebooks' => [
                'name' => 'Cahiers',
                'slug' => 'cahiers',
                'aliases' => ['notebooks'],
                'names' => ['Cahiers', 'Notebooks'],
            ],
        ];
    }

    /**
     * Create or update one demonstration product and its local image.
     */
    private function seedProduct(
        array $definition,
        int $categoryId,
        int $channelId,
        int $inventorySourceId,
        int $attributeFamilyId,
        string $locale,
        ?string $imageSource,
        int $imagePosition,
    ): void {
        $product = $this->productRepository->findOneByField('sku', $definition['sku']);

        if (! $product) {
            $product = $this->productRepository->create([
                'type' => 'simple',
                'attribute_family_id' => $attributeFamilyId,
                'sku' => $definition['sku'],
            ]);
        }

        $attributes = $this->productAttributes($definition, $categoryId, $channelId, $inventorySourceId, $locale);
        $imageIds = $product->images()
            ->pluck('id')
            ->mapWithKeys(fn ($imageId) => [$imageId => $imageId])
            ->all();

        if ($imageIds) {
            $attributes['images'] = ['files' => $imageIds];
        }

        $product = $this->productRepository->update($attributes, $product->id);

        $this->seedImage($product, $imageSource, $locale, $definition['name'], $imagePosition);
    }

    /**
     * Build the EAV and relationship data for one demonstration product.
     *
     * @return array<string, mixed>
     */
    private function productAttributes(
        array $definition,
        int $categoryId,
        int $channelId,
        int $inventorySourceId,
        string $locale,
    ): array {
        return [
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
            'featured' => $definition['featured'],
            'guest_checkout' => true,
            'manage_stock' => true,
            'channel' => core()->getDefaultChannelCode(),
            'locale' => $locale,
            'channels' => [$channelId],
            'categories' => [$categoryId],
            'inventories' => [$inventorySourceId => $definition['qty']],
        ];
    }

    /**
     * Copy one package image to the public product media disk when needed.
     */
    private function seedImage(
        Product $product,
        ?string $imageSource,
        string $locale,
        string $name,
        int $imagePosition,
    ): void {
        if (
            ! $imageSource
            || ! Filesystem::exists($imageSource)
            || $product->images()->exists()
        ) {
            return;
        }

        $extension = pathinfo($imageSource, PATHINFO_EXTENSION) ?: 'webp';
        $path = 'products/'.$product->id.'/drall-demo-'.$imagePosition.'.'.$extension;

        if (! Storage::put($path, Filesystem::get($imageSource))) {
            throw new \RuntimeException('Unable to store the demonstration image for product '.$product->sku.'.');
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
}
