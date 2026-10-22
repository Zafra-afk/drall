<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Webkul\Inventory\Services\StockProcurementService;

use function Pest\Laravel\get;

// ============================================================================
// Inventory Procurement
// ============================================================================

it('should create and partially receive purchase orders with auditable lot stock', function () {
    $product = $this->createSimpleProduct();
    $sourceId = DB::table('inventory_sources')->value('id');
    $inventory = DB::table('product_inventories')
        ->where('product_id', $product->id)
        ->where('inventory_source_id', $sourceId)
        ->where('vendor_id', 0)
        ->first();
    $before = (int) $inventory->qty;
    $service = app(StockProcurementService::class);
    $supplierId = $service->createSupplier(['name' => 'Fournisseur Test']);

    $orderId = $service->createOrder([
        'supplier_id' => $supplierId,
        'inventory_source_id' => $sourceId,
        'status' => 'ordered',
        'items' => [
            [
                'product_id' => $product->id,
                'qty_ordered' => 6,
                'unit_cost' => 1200,
            ],
        ],
    ], null);

    $itemId = DB::table('purchase_order_items')->where('purchase_order_id', $orderId)->value('id');

    $service->receive($orderId, [
        [
            'item_id' => $itemId,
            'quantity' => 2,
            'batch_number' => 'LOT-TEST-1',
            'expires_at' => now()->addMonths(3)->toDateString(),
        ],
    ], null);

    expect(DB::table('purchase_orders')->where('id', $orderId)->value('status'))->toBe('partially_received')
        ->and((int) DB::table('product_inventories')->where('product_id', $product->id)->where('inventory_source_id', $sourceId)->where('vendor_id', 0)->value('qty'))->toBe($before + 2);

    $service->receive($orderId, [
        [
            'item_id' => $itemId,
            'quantity' => 4,
            'batch_number' => 'LOT-TEST-1',
            'expires_at' => now()->addMonths(3)->toDateString(),
        ],
    ], null);

    $after = $before + 6;

    expect(DB::table('purchase_orders')->where('id', $orderId)->value('status'))->toBe('received')
        ->and((int) DB::table('product_inventories')->where('product_id', $product->id)->where('inventory_source_id', $sourceId)->where('vendor_id', 0)->value('qty'))->toBe($after);

    $service->recordOutgoing($product->id, $sourceId, 3, $after, $after - 3, 'invoice', 1);

    expect((int) DB::table('stock_movements')
        ->where('product_id', $product->id)
        ->where('inventory_source_id', $sourceId)
        ->where('batch_number', 'LOT-TEST-1')
        ->sum('quantity_change'))->toBe(3);
});

it('should reject receipts beyond the remaining order quantity without changing stock', function () {
    $product = $this->createSimpleProduct();
    $sourceId = DB::table('inventory_sources')->value('id');
    $service = app(StockProcurementService::class);
    $supplierId = $service->createSupplier(['name' => 'Fournisseur Limite']);
    $orderId = $service->createOrder([
        'supplier_id' => $supplierId,
        'inventory_source_id' => $sourceId,
        'status' => 'ordered',
        'items' => [
            [
                'product_id' => $product->id,
                'qty_ordered' => 1,
                'unit_cost' => 100,
            ],
        ],
    ], null);
    $itemId = DB::table('purchase_order_items')->where('purchase_order_id', $orderId)->value('id');
    $before = (int) DB::table('product_inventories')
        ->where('product_id', $product->id)
        ->where('inventory_source_id', $sourceId)
        ->where('vendor_id', 0)
        ->value('qty');

    expect(fn () => $service->receive($orderId, [
        ['item_id' => $itemId, 'quantity' => 2],
    ], null))->toThrow(ValidationException::class);

    expect((int) DB::table('product_inventories')
        ->where('product_id', $product->id)
        ->where('inventory_source_id', $sourceId)
        ->where('vendor_id', 0)
        ->value('qty'))->toBe($before)
        ->and(DB::table('stock_movements')->where('reference_id', $orderId)->count())->toBe(0);
});

it('should place draft orders before they can be received', function () {
    $product = $this->createSimpleProduct();
    $sourceId = DB::table('inventory_sources')->value('id');
    $service = app(StockProcurementService::class);
    $supplierId = $service->createSupplier(['name' => 'Fournisseur Brouillon']);
    $orderId = $service->createOrder([
        'supplier_id' => $supplierId,
        'inventory_source_id' => $sourceId,
        'items' => [
            [
                'product_id' => $product->id,
                'qty_ordered' => 2,
                'unit_cost' => 300,
            ],
        ],
    ], null);

    expect(DB::table('purchase_orders')->where('id', $orderId)->value('status'))->toBe('draft');

    $service->placeOrder($orderId);

    expect(DB::table('purchase_orders')->where('id', $orderId)->value('status'))->toBe('ordered');
});

it('should prevent a stock adjustment from making inventory negative', function () {
    $product = $this->createSimpleProduct();
    $sourceId = DB::table('inventory_sources')->value('id');
    $before = (int) DB::table('product_inventories')
        ->where('product_id', $product->id)
        ->where('inventory_source_id', $sourceId)
        ->where('vendor_id', 0)
        ->value('qty');

    expect(fn () => app(StockProcurementService::class)->adjust(
        $product->id,
        $sourceId,
        -$before - 1,
        'Ajustement de contrôle',
        null,
    ))->toThrow(ValidationException::class);

    expect((int) DB::table('product_inventories')
        ->where('product_id', $product->id)
        ->where('inventory_source_id', $sourceId)
        ->where('vendor_id', 0)
        ->value('qty'))->toBe($before);
});

it('should show inventory procurement to authorized administrators only', function () {
    get(route('admin.inventory.procurement.index'))
        ->assertRedirect(route('admin.session.create'));

    $this->loginAsAdmin();

    get(route('admin.inventory.procurement.index'))
        ->assertOk()
        ->assertSeeText('Stocks et approvisionnements')
        ->assertSeeText('Code-barres')
        ->assertSeeText('Commandes d’approvisionnement');
});

it('should search products for procurement orders by their SKU', function () {
    $product = $this->createSimpleProduct();

    $this->loginAsAdmin();

    get(route('admin.inventory.procurement.products.search', ['q' => $product->sku]))
        ->assertOk()
        ->assertJsonFragment([
            'id' => $product->id,
        ]);
});

it('should save optional barcode, unit, and minimum stock details', function () {
    $product = $this->createSimpleProduct();

    $this->loginAsAdmin();

    $this->put(route('admin.inventory.procurement.products.update', $product->id), [
        'product_id' => $product->id,
        'barcode' => '6001234567890',
        'unit' => 'pack',
        'minimum_stock' => 8,
    ])->assertRedirect(route('admin.inventory.procurement.index'));

    $this->assertDatabaseHas('product_retail_details', [
        'product_id' => $product->id,
        'barcode' => '6001234567890',
        'unit' => 'pack',
        'minimum_stock' => 8,
    ]);
});
