<?php

namespace Webkul\Sales\Repositories;

use Illuminate\Support\Facades\Event;
use Webkul\Core\Eloquent\Repository;
use Webkul\Inventory\Services\StockProcurementService;
use Webkul\Sales\Contracts\ShipmentItem;

class ShipmentItemRepository extends Repository
{
    /**
     * Specify the model class name.
     */
    public function model(): string
    {
        return ShipmentItem::class;
    }

    /**
     * Take the shipped quantity of an item out of its inventory source.
     *
     * @param  array  $data
     * @return void
     */
    public function updateProductInventory($data)
    {
        if (! $data['product']) {
            return;
        }

        if (! $data['product']->manage_stock) {
            return;
        }

        $orderedInventory = $data['product']->ordered_inventories()
            ->where('channel_id', $data['shipment']->order->channel->id)
            ->first();

        if ($orderedInventory) {
            if (($orderedQty = $orderedInventory->qty - $data['qty']) < 0) {
                $orderedQty = 0;
            }

            $orderedInventory->update(['qty' => $orderedQty]);
        }

        $inventory = $data['product']->inventories()
            ->where('vendor_id', $data['vendor_id'])
            ->where('inventory_source_id', $data['shipment']->inventory_source_id)
            ->first();

        if (! $inventory) {
            return;
        }

        $before = (int) $inventory->qty;

        if (($qty = $before - $data['qty']) < 0) {
            $qty = 0;
        }

        $inventory->update(['qty' => $qty]);

        app(StockProcurementService::class)->recordOutgoing(
            $data['product']->id,
            $inventory->inventory_source_id,
            $before - $qty,
            $before,
            $qty,
            'shipment',
            $data['shipment']->id,
        );

        Event::dispatch('catalog.product.update.after', $data['product']);
    }
}
