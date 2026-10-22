<?php

use Webkul\Product\Models\Product;
use Webkul\Shop\Http\Resources\ProductCardResource;

use function Pest\Laravel\getJson;

/**
 * The product card the storefront products api lists for the given product.
 */
function listedProductCard(Product $product): ?array
{
    return collect(getJson(route('shop.api.products.index', ['sort' => 'created_at-desc']))->assertOk()->json('data'))
        ->firstWhere('id', $product->id);
}

// ============================================================================
// Product Card
// ============================================================================

it('should omit the heavy description and gallery fields from the products listing', function () {
    expect(listedProductCard($this->createSimpleProduct()))
        ->not->toHaveKey('description')
        ->not->toHaveKey('images');
});

it('should keep the fields the product card needs in the listing', function () {
    expect(listedProductCard($this->createSimpleProduct()))
        ->toHaveKeys([
            'id',
            'name',
            'url_key',
            'base_image',
            'is_new',
            'is_saleable',
            'can_be_added_to_cart_without_options',
            'is_wishlist',
            'price_html',
            'ratings',
            'reviews',
        ]);
});

it('should mark configurable product cards as requiring options', function () {
    $product = $this->createConfigurableProduct();
    $card = (new ProductCardResource($product))->resolve();

    expect($card['can_be_added_to_cart_without_options'])->toBeFalse();
});

it('should link configurable product cards to a reachable product page', function () {
    $product = $this->createConfigurableProduct();
    $card = (new ProductCardResource($product))->resolve();

    expect($card['url_key'])->toBe($product->url_key);

    get(route('shop.product_or_category.index', $card['url_key']))
        ->assertOk();
});
