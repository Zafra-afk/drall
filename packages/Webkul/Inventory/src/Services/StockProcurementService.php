<?php

namespace Webkul\Inventory\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Webkul\Product\Models\ProductInventory;

class StockProcurementService
{
    /**
     * Load the stock-management lists for the administrator dashboard.
     */
    public function dashboard(): array
    {
        $orders = DB::table('purchase_orders')
            ->join('suppliers', 'suppliers.id', '=', 'purchase_orders.supplier_id')
            ->join('inventory_sources', 'inventory_sources.id', '=', 'purchase_orders.inventory_source_id')
            ->select('purchase_orders.*', 'suppliers.name as supplier_name', 'inventory_sources.name as source_name')
            ->orderByDesc('purchase_orders.id')
            ->paginate(20);

        return [
            'suppliers' => DB::table('suppliers')->orderBy('name')->get(),
            'orders' => $orders,
            'orderItems' => DB::table('purchase_order_items')
                ->join('products', 'products.id', '=', 'purchase_order_items.product_id')
                ->whereIn('purchase_order_id', $orders->pluck('id'))
                ->select('purchase_order_items.*', 'products.sku')
                ->orderBy('purchase_order_items.id')
                ->get()
                ->groupBy('purchase_order_id'),
            'movements' => DB::table('stock_movements')
                ->join('products', 'products.id', '=', 'stock_movements.product_id')
                ->join('inventory_sources', 'inventory_sources.id', '=', 'stock_movements.inventory_source_id')
                ->select('stock_movements.*', 'products.sku', 'inventory_sources.name as source_name')
                ->orderByDesc('stock_movements.id')
                ->limit(50)
                ->get(),
            'products' => DB::table('products')
                ->leftJoin('product_retail_details', 'product_retail_details.product_id', '=', 'products.id')
                ->leftJoin('product_flat', function ($join) {
                    $join->on('product_flat.product_id', '=', 'products.id')
                        ->where('product_flat.channel', core()->getDefaultChannelCode())
                        ->where('product_flat.locale', app()->getLocale());
                })
                ->when(request('product_search'), function ($query, $search) {
                    $query->where(function ($query) use ($search) {
                        $query->where('products.sku', db_grammar()->caseInsensitiveLike(), '%'.$search.'%')
                            ->orWhere('product_retail_details.barcode', db_grammar()->caseInsensitiveLike(), '%'.$search.'%')
                            ->orWhere('product_flat.name', db_grammar()->caseInsensitiveLike(), '%'.$search.'%');
                    });
                })
                ->select(
                    'products.id',
                    'products.sku',
                    'product_flat.name',
                    'product_retail_details.barcode',
                    'product_retail_details.unit',
                    'product_retail_details.minimum_stock',
                    'product_retail_details.supplier_id',
                )
                ->orderBy('products.sku')
                ->paginate(50)
                ->withQueryString(),
            'sources' => DB::table('inventory_sources')->where('status', 1)->orderBy('name')->get(['id', 'name']),
            'lowStock' => DB::table('product_inventories')
                ->join('product_retail_details', 'product_retail_details.product_id', '=', 'product_inventories.product_id')
                ->join('products', 'products.id', '=', 'product_inventories.product_id')
                ->join('inventory_sources', 'inventory_sources.id', '=', 'product_inventories.inventory_source_id')
                ->whereColumn('product_inventories.qty', '<=', 'product_retail_details.minimum_stock')
                ->select('products.sku', 'product_inventories.qty', 'product_retail_details.minimum_stock', 'inventory_sources.name as source_name')
                ->orderBy('product_inventories.qty')
                ->limit(50)
                ->get(),
            'expiringLots' => DB::table('stock_movements')
                ->join('products', 'products.id', '=', 'stock_movements.product_id')
                ->whereNotNull('stock_movements.expires_at')
                ->whereDate('stock_movements.expires_at', '<=', now()->addDays(30))
                ->select('products.sku', 'stock_movements.product_id', 'stock_movements.inventory_source_id', 'stock_movements.batch_number', 'stock_movements.expires_at')
                ->selectRaw('SUM('.DB::getTablePrefix().'stock_movements.quantity_change) as quantity_remaining')
                ->groupBy('products.sku', 'stock_movements.product_id', 'stock_movements.inventory_source_id', 'stock_movements.batch_number', 'stock_movements.expires_at')
                ->havingRaw('SUM('.DB::getTablePrefix().'stock_movements.quantity_change) > 0')
                ->orderBy('stock_movements.expires_at')
                ->limit(50)
                ->get(),
        ];
    }

    /**
     * Search products for supplier orders.
     */
    public function searchProducts(string $search): array
    {
        return DB::table('products')
            ->leftJoin('product_retail_details', 'product_retail_details.product_id', '=', 'products.id')
            ->leftJoin('product_flat', function ($join) {
                $join->on('product_flat.product_id', '=', 'products.id')
                    ->where('product_flat.channel', core()->getDefaultChannelCode())
                    ->where('product_flat.locale', app()->getLocale());
            })
            ->where(function ($query) use ($search) {
                $query->where('products.sku', db_grammar()->caseInsensitiveLike(), '%'.$search.'%')
                    ->orWhere('product_retail_details.barcode', db_grammar()->caseInsensitiveLike(), '%'.$search.'%')
                    ->orWhere('product_flat.name', db_grammar()->caseInsensitiveLike(), '%'.$search.'%');
            })
            ->select('products.id', 'products.sku', 'product_flat.name')
            ->orderBy('products.sku')
            ->limit(20)
            ->get()
            ->map(fn ($product) => [
                'id' => $product->id,
                'label' => $product->name
                    ? $product->sku.' — '.$product->name
                    : $product->sku,
            ])
            ->all();
    }

    /**
     * Create a supplier record.
     */
    public function createSupplier(array $data): int
    {
        return (int) DB::table('suppliers')->insertGetId([
            'name' => $data['name'],
            'phone' => $data['phone'] ?? null,
            'email' => $data['email'] ?? null,
            'address' => $data['address'] ?? null,
            'is_active' => $data['is_active'] ?? true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * Save optional supermarket attributes for a product.
     */
    public function saveProductDetails(int $productId, array $data): void
    {
        DB::table('product_retail_details')->updateOrInsert(
            ['product_id' => $productId],
            [
                'barcode' => $data['barcode'] ?? null,
                'unit' => $data['unit'],
                'minimum_stock' => $data['minimum_stock'],
                'supplier_id' => $data['supplier_id'] ?? null,
                'updated_at' => now(),
                'created_at' => now(),
            ]
        );
    }

    /**
     * Create an ordered supplier purchase.
     */
    public function createOrder(array $data, ?int $adminId): int
    {
        return DB::transaction(function () use ($data, $adminId) {
            $orderId = DB::table('purchase_orders')->insertGetId([
                'number' => 'DR-PO-'.now()->format('YmdHis').'-'.Str::upper(Str::random(5)),
                'supplier_id' => $data['supplier_id'],
                'inventory_source_id' => $data['inventory_source_id'],
                'status' => $data['status'] ?? 'draft',
                'ordered_at' => ($data['status'] ?? 'draft') === 'ordered' ? now() : null,
                'notes' => $data['notes'] ?? null,
                'created_by' => $adminId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            foreach ($data['items'] as $item) {
                DB::table('purchase_order_items')->insert([
                    'purchase_order_id' => $orderId,
                    'product_id' => $item['product_id'],
                    'qty_ordered' => $item['qty_ordered'],
                    'unit_cost' => $item['unit_cost'],
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                DB::table('product_retail_details')->updateOrInsert(
                    ['product_id' => $item['product_id']],
                    [
                        'supplier_id' => $data['supplier_id'],
                        'purchase_price' => $item['unit_cost'],
                        'updated_at' => now(),
                        'created_at' => now(),
                    ]
                );
            }

            return (int) $orderId;
        });
    }

    /**
     * Receive full or partial quantities against an ordered purchase.
     */
    public function receive(int $orderId, array $receipts, ?int $adminId): void
    {
        DB::transaction(function () use ($orderId, $receipts, $adminId) {
            $order = DB::table('purchase_orders')->where('id', $orderId)->lockForUpdate()->first();

            if (! $order || ! in_array($order->status, ['ordered', 'partially_received'], true)) {
                throw ValidationException::withMessages([
                    'purchase_order' => 'Cette commande ne peut pas être réceptionnée.',
                ]);
            }

            foreach ($receipts as $receipt) {
                $item = DB::table('purchase_order_items')
                    ->where('id', $receipt['item_id'])
                    ->where('purchase_order_id', $orderId)
                    ->lockForUpdate()
                    ->first();

                if (! $item) {
                    throw ValidationException::withMessages([
                        'receipts' => 'Un article ne fait pas partie de cette commande.',
                    ]);
                }

                $quantity = (int) $receipt['quantity'];
                $remaining = $item->qty_ordered - $item->qty_received;

                if ($quantity < 1 || $quantity > $remaining) {
                    throw ValidationException::withMessages([
                        'receipts' => 'La quantité réceptionnée doit être positive et ne pas dépasser le reste à recevoir.',
                    ]);
                }

                $inventory = ProductInventory::query()
                    ->where('product_id', $item->product_id)
                    ->where('inventory_source_id', $order->inventory_source_id)
                    ->where('vendor_id', 0)
                    ->lockForUpdate()
                    ->first();

                if (! $inventory) {
                    DB::table('product_inventories')->insertOrIgnore([
                        'product_id' => $item->product_id,
                        'inventory_source_id' => $order->inventory_source_id,
                        'vendor_id' => 0,
                        'qty' => 0,
                    ]);

                    $inventory = ProductInventory::query()
                        ->where('product_id', $item->product_id)
                        ->where('inventory_source_id', $order->inventory_source_id)
                        ->where('vendor_id', 0)
                        ->lockForUpdate()
                        ->firstOrFail();
                }

                $before = (int) $inventory->qty;
                $after = $before + $quantity;

                $inventory->update(['qty' => $after]);

                DB::table('purchase_order_items')
                    ->where('id', $item->id)
                    ->update([
                        'qty_received' => $item->qty_received + $quantity,
                        'updated_at' => now(),
                    ]);

                DB::table('stock_movements')->insert([
                    'product_id' => $item->product_id,
                    'inventory_source_id' => $order->inventory_source_id,
                    'type' => 'purchase_receipt',
                    'quantity_change' => $quantity,
                    'quantity_before' => $before,
                    'quantity_after' => $after,
                    'reference_type' => 'purchase_order',
                    'reference_id' => $orderId,
                    'batch_number' => $receipt['batch_number'] ?? null,
                    'expires_at' => $receipt['expires_at'] ?? null,
                    'notes' => null,
                    'created_by' => $adminId,
                    'created_at' => now(),
                ]);
            }

            $remainingCount = DB::table('purchase_order_items')
                ->where('purchase_order_id', $orderId)
                ->whereColumn('qty_received', '<', 'qty_ordered')
                ->count();

            DB::table('purchase_orders')
                ->where('id', $orderId)
                ->update([
                    'status' => $remainingCount ? 'partially_received' : 'received',
                    'updated_at' => now(),
                ]);
        });
    }

    /**
     * Place a draft supplier order.
     */
    public function placeOrder(int $orderId): void
    {
        DB::transaction(function () use ($orderId) {
            $order = DB::table('purchase_orders')->where('id', $orderId)->lockForUpdate()->first();

            if (! $order || $order->status !== 'draft') {
                throw ValidationException::withMessages([
                    'purchase_order' => 'Seule une commande brouillon peut être commandée.',
                ]);
            }

            DB::table('purchase_orders')
                ->where('id', $orderId)
                ->update(['status' => 'ordered', 'ordered_at' => now(), 'updated_at' => now()]);
        });
    }

    /**
     * Cancel an order that has not had any units received.
     */
    public function cancel(int $orderId): void
    {
        DB::transaction(function () use ($orderId) {
            $order = DB::table('purchase_orders')->where('id', $orderId)->lockForUpdate()->first();

            if (
                ! $order
                || ! in_array($order->status, ['draft', 'ordered'], true)
                || DB::table('purchase_order_items')->where('purchase_order_id', $orderId)->where('qty_received', '>', 0)->exists()
            ) {
                throw ValidationException::withMessages([
                    'purchase_order' => 'Seule une commande non réceptionnée peut être annulée.',
                ]);
            }

            DB::table('purchase_orders')
                ->where('id', $orderId)
                ->update(['status' => 'cancelled', 'updated_at' => now()]);
        });
    }

    /**
     * Apply a traceable non-negative stock adjustment.
     */
    public function adjust(int $productId, int $sourceId, int $quantityChange, string $notes, ?int $adminId): void
    {
        if ($quantityChange === 0) {
            throw ValidationException::withMessages([
                'quantity_change' => 'La quantité de l’ajustement ne peut pas être nulle.',
            ]);
        }

        DB::transaction(function () use ($productId, $sourceId, $quantityChange, $notes, $adminId) {
            $inventory = ProductInventory::query()
                ->where('product_id', $productId)
                ->where('inventory_source_id', $sourceId)
                ->where('vendor_id', 0)
                ->lockForUpdate()
                ->first();

            if (! $inventory) {
                if ($quantityChange < 0) {
                    throw ValidationException::withMessages([
                        'quantity_change' => 'Le stock ne peut pas devenir négatif.',
                    ]);
                }

                DB::table('product_inventories')->insertOrIgnore([
                    'product_id' => $productId,
                    'inventory_source_id' => $sourceId,
                    'vendor_id' => 0,
                    'qty' => 0,
                ]);

                $inventory = ProductInventory::query()
                    ->where('product_id', $productId)
                    ->where('inventory_source_id', $sourceId)
                    ->where('vendor_id', 0)
                    ->lockForUpdate()
                    ->firstOrFail();
            }

            $before = (int) $inventory->qty;
            $after = $before + $quantityChange;

            if ($after < 0) {
                throw ValidationException::withMessages([
                    'quantity_change' => 'Le stock ne peut pas devenir négatif.',
                ]);
            }

            $inventory->update(['qty' => $after]);

            DB::table('stock_movements')->insert([
                'product_id' => $productId,
                'inventory_source_id' => $sourceId,
                'type' => 'adjustment',
                'quantity_change' => $quantityChange,
                'quantity_before' => $before,
                'quantity_after' => $after,
                'notes' => $notes,
                'created_by' => $adminId,
                'created_at' => now(),
            ]);
        });
    }

    /**
     * Record stock leaving a source, consuming tracked batches by earliest expiry first.
     */
    public function recordOutgoing(
        int $productId,
        int $sourceId,
        int $quantity,
        int $quantityBefore,
        int $quantityAfter,
        string $referenceType,
        ?int $referenceId,
    ): void {
        $batches = DB::table('stock_movements')
            ->select('batch_number', 'expires_at')
            ->selectRaw('SUM(quantity_change) as quantity_remaining')
            ->where('product_id', $productId)
            ->where('inventory_source_id', $sourceId)
            ->groupBy('batch_number', 'expires_at')
            ->havingRaw('SUM(quantity_change) > 0')
            ->get()
            ->sortBy(fn ($batch) => $batch->expires_at ?? '9999-12-31')
            ->values();

        $remaining = $quantity;
        $runningQuantity = $quantityBefore;

        foreach ($batches as $batch) {
            if ($remaining < 1) {
                break;
            }

            $batchQuantity = min($remaining, (int) $batch->quantity_remaining);

            $this->insertMovement([
                'product_id' => $productId,
                'inventory_source_id' => $sourceId,
                'type' => 'sale',
                'quantity_change' => -$batchQuantity,
                'quantity_before' => $runningQuantity,
                'quantity_after' => $runningQuantity - $batchQuantity,
                'reference_type' => $referenceType,
                'reference_id' => $referenceId,
                'batch_number' => $batch->batch_number,
                'expires_at' => $batch->expires_at,
                'created_by' => null,
            ]);

            $runningQuantity -= $batchQuantity;
            $remaining -= $batchQuantity;
        }

        if ($remaining > 0) {
            $this->insertMovement([
                'product_id' => $productId,
                'inventory_source_id' => $sourceId,
                'type' => 'sale',
                'quantity_change' => -$remaining,
                'quantity_before' => $runningQuantity,
                'quantity_after' => $quantityAfter,
                'reference_type' => $referenceType,
                'reference_id' => $referenceId,
                'created_by' => null,
            ]);
        }
    }

    /**
     * Record stock returning to an inventory source.
     */
    public function recordIncoming(
        int $productId,
        int $sourceId,
        int $quantity,
        int $quantityBefore,
        int $quantityAfter,
        string $referenceType,
        ?int $referenceId,
        string $movementType = 'refund',
    ): void {
        $this->insertMovement([
            'product_id' => $productId,
            'inventory_source_id' => $sourceId,
            'type' => $movementType,
            'quantity_change' => $quantity,
            'quantity_before' => $quantityBefore,
            'quantity_after' => $quantityAfter,
            'reference_type' => $referenceType,
            'reference_id' => $referenceId,
            'created_by' => null,
        ]);
    }

    /**
     * Insert an auditable stock movement.
     */
    private function insertMovement(array $movement): void
    {
        DB::table('stock_movements')->insert(array_merge([
            'batch_number' => null,
            'expires_at' => null,
            'notes' => null,
            'created_at' => now(),
        ], $movement));
    }
}
