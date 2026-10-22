<?php

namespace Webkul\Installer\Database\Seeders\Category;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Webkul\Category\Models\Category;

class SupermarketCategorySeeder extends Seeder
{
    /**
     * Supermarket departments and their subcategories.
     *
     * @var array<string, array<string, string>>
     */
    private array $departments = [
        'Fruits et légumes' => [
            'fruits' => 'Fruits',
            'legumes' => 'Légumes',
            'produits-locaux' => 'Produits locaux',
        ],
        'Boucherie et poissonnerie' => [
            'viandes' => 'Viandes',
            'volailles' => 'Volailles',
            'poissons-fruits-de-mer' => 'Poissons et fruits de mer',
        ],
        'Boulangerie et pâtisserie' => [
            'pains' => 'Pains',
            'viennoiseries' => 'Viennoiseries',
            'gateaux' => 'Gâteaux',
        ],
        'Épicerie' => [
            'riz-cereales' => 'Riz et céréales',
            'pates-farine' => 'Pâtes et farine',
            'huiles-condiments' => 'Huiles et condiments',
            'sucre-sel' => 'Sucre et sel',
            'conserves' => 'Conserves',
            'epices-legumineuses' => 'Épices et légumineuses',
        ],
        'Produits laitiers' => [
            'lait' => 'Lait',
            'yaourts' => 'Yaourts',
            'fromages' => 'Fromages',
            'beurre' => 'Beurre',
        ],
        'Boissons' => [
            'eau' => 'Eau',
            'jus' => 'Jus',
            'boissons-gazeuses' => 'Boissons gazeuses',
            'autres-boissons' => 'Autres boissons',
        ],
        'Hygiène et beauté' => [
            'savons' => 'Savons',
            'shampoings' => 'Shampoings',
            'dentifrices' => 'Dentifrices',
            'soins-corporels' => 'Soins corporels',
        ],
        'Entretien de la maison' => [
            'lessive' => 'Lessive',
            'detergents' => 'Détergents',
            'produits-nettoyage' => 'Produits de nettoyage',
        ],
        'Maison et cuisine' => [
            'vaisselle' => 'Vaisselle',
            'ustensiles' => 'Ustensiles',
            'rangement' => 'Rangement',
        ],
        'Fournitures scolaires et de bureau' => [
            'cahiers' => 'Cahiers',
            'stylos' => 'Stylos',
            'papier' => 'Papier',
        ],
        'Promotions et produits saisonniers' => [
            'promotions' => 'Promotions',
            'produits-saisonniers' => 'Produits saisonniers',
        ],
    ];

    /**
     * French translations for the pre-existing demonstration categories.
     *
     * @var array<string, string>
     */
    private array $categoryLabels = [
        'Root' => 'Boutique',
        'Mens' => 'Hommes',
        'Kids' => 'Enfants',
        'Womens' => 'Femmes',
        'Formal Wear' => 'Vêtements de cérémonie',
        'Casual Wear' => 'Vêtements décontractés',
        'Active Wear' => 'Vêtements de sport',
        'Footwear' => 'Chaussures',
        'Girls Clothing' => 'Vêtements pour filles',
        'Boys Clothing' => 'Vêtements pour garçons',
        'Girls Footwear' => 'Chaussures pour filles',
        'Boys Footwear' => 'Chaussures pour garçons',
        'Wellness' => 'Bien-être',
        'Downloadable Yoga Tutorial' => 'Tutoriel de yoga téléchargeable',
        'E-Books' => 'Livres numériques',
        'Movie Pass' => 'Pass cinéma',
        'Bookings' => 'Réservations',
        'Appointment Booking' => 'Réservation de rendez-vous',
        'Event Booking' => 'Réservation d’événements',
        'Community Hall Bookings' => 'Réservation de salle communale',
        'Table Booking' => 'Réservation de table',
        'Rental Booking' => 'Réservation de location',
        'Electronics' => 'Électronique',
        'Mobile Phones & Accessories' => 'Téléphones et accessoires',
        'Laptops & Tablets' => 'Ordinateurs portables et tablettes',
        'Audio Devices' => 'Appareils audio',
        'Smart Home & Automation' => 'Maison connectée et domotique',
        'Household' => 'Maison',
        'Kitchen Appliances' => 'Électroménager de cuisine',
        'Cookware & Dining' => 'Ustensiles et arts de la table',
        'Furniture & Decor' => 'Meubles et décoration',
        'Cleaning Supplies' => 'Produits d’entretien',
        'Books & Stationery' => 'Livres et papeterie',
        'Fiction & Non-Fiction Books' => 'Livres de fiction et documentaires',
        'Educational & Academic' => 'Livres scolaires et universitaires',
        'Office Supplies' => 'Fournitures de bureau',
        'Art & Craft Materials' => 'Matériel d’art et loisirs créatifs',
    ];

    /**
     * Add the supermarket category tree below the default channel root.
     */
    public function run(): void
    {
        $this->translateExistingCategories();

        $rootCategoryId = core()->getDefaultChannel()?->root_category_id;

        if (! $rootCategoryId) {
            throw new \RuntimeException('A default channel root category is required to seed supermarket categories.');
        }

        $position = (int) Category::query()
            ->where('parent_id', $rootCategoryId)
            ->max('position');

        foreach ($this->departments as $department => $subcategories) {
            $parent = $this->findOrCreateCategory(
                $department,
                str($department)->ascii()->slug('-')->toString(),
                (int) $rootCategoryId,
                ++$position,
            );

            foreach ($subcategories as $slug => $name) {
                $this->findOrCreateCategory($name, $slug, $parent->id, 1);
            }
        }
    }

    /**
     * Find an existing French category or create it under the requested parent.
     */
    private function findOrCreateCategory(string $name, string $slug, int $parentId, int $position): Category
    {
        $category = Category::query()
            ->where('parent_id', $parentId)
            ->whereHas('translations', fn ($query) => $query
                ->where('locale', 'fr')
                ->where('slug', $slug))
            ->first();

        if ($category) {
            return $category;
        }

        $category = new Category([
            'position' => $position,
            'status' => true,
            'display_mode' => 'products_and_description',
            'parent_id' => $parentId,
        ]);

        $category->translateOrNew('fr')->fill([
            'name' => $name,
            'slug' => $slug,
            'description' => '',
            'meta_title' => '',
            'meta_description' => '',
            'meta_keywords' => '',
        ]);

        $category->save();

        return $category;
    }

    /**
     * Add French translations without replacing the existing category content.
     */
    private function translateExistingCategories(): void
    {
        $localeId = DB::table('locales')->where('code', 'fr')->value('id');

        foreach (DB::table('category_translations')->where('locale', 'en')->get() as $translation) {
            if (! isset($this->categoryLabels[$translation->name])) {
                continue;
            }

            $existing = DB::table('category_translations')
                ->where('category_id', $translation->category_id)
                ->where('locale', 'fr')
                ->first();

            if ($existing) {
                continue;
            }

            $name = $this->categoryLabels[$translation->name];
            $category = DB::table('categories')->where('id', $translation->category_id)->first();

            if (! $category) {
                continue;
            }

            $ancestorSlugs = DB::table('categories')
                ->where('_lft', '<', $category->_lft)
                ->where('_rgt', '>', $category->_rgt)
                ->orderBy('_lft')
                ->pluck('id')
                ->map(fn ($id) => DB::table('category_translations')
                    ->where('category_id', $id)
                    ->where('locale', 'fr')
                    ->value('slug'))
                ->filter(fn ($slug, $index) => $index > 0 && $slug)
                ->all();

            DB::table('category_translations')->insert([
                'category_id' => $translation->category_id,
                'name' => $name,
                'slug' => $translation->category_id === core()->getDefaultChannel()?->root_category_id
                    ? 'root'
                    : Str::slug($name),
                'url_path' => implode('/', [...$ancestorSlugs, ...($translation->category_id === core()->getDefaultChannel()?->root_category_id ? [] : [Str::slug($name)])]),
                'description' => $translation->description,
                'meta_title' => $name,
                'meta_description' => $translation->meta_description,
                'meta_keywords' => $translation->meta_keywords,
                'locale_id' => $localeId,
                'locale' => 'fr',
            ]);
        }
    }
}
