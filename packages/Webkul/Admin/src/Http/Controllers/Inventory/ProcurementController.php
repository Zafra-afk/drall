<?php

namespace Webkul\Admin\Http\Controllers\Inventory;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Webkul\Admin\Http\Controllers\Controller;
use Webkul\Inventory\Services\StockProcurementService;

class ProcurementController extends Controller
{
    /**
     * Create a controller instance.
     */
    public function __construct(protected StockProcurementService $stockProcurementService) {}

    /**
     * Display suppliers, purchase orders, and stock movements.
     */
    public function index(): View
    {
        return view('admin::inventory.procurement.index', $this->stockProcurementService->dashboard());
    }

    /**
     * Search products for a supplier order.
     */
    public function searchProducts(): JsonResponse
    {
        $data = request()->validate([
            'q' => ['required', 'string', 'min:2', 'max:100'],
        ]);

        return response()->json($this->stockProcurementService->searchProducts($data['q']));
    }

    /**
     * Create a supplier.
     */
    public function storeSupplier(): RedirectResponse
    {
        $data = request()->validate([
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:64'],
            'email' => ['nullable', 'email', 'max:255'],
            'address' => ['nullable', 'string', 'max:2000'],
        ]);

        $this->stockProcurementService->createSupplier($data);

        return redirect()->route('admin.inventory.procurement.index')->with('success', 'Fournisseur ajouté.');
    }

    /**
     * Save barcode, unit, and minimum-stock details for a product.
     */
    public function storeProductDetails(int $id): RedirectResponse
    {
        $data = request()->validate([
            'product_id' => ['required', 'integer', 'in:'.$id, 'exists:products,id'],
            'barcode' => ['nullable', 'string', 'max:255', 'unique:product_retail_details,barcode,'.$id.',product_id'],
            'unit' => ['required', 'in:piece,kilogram,gram,liter,milliliter,pack,carton'],
            'minimum_stock' => ['required', 'integer', 'min:0'],
            'supplier_id' => ['nullable', 'integer', 'exists:suppliers,id'],
        ]);

        $this->stockProcurementService->saveProductDetails($id, $data);

        return redirect()->route('admin.inventory.procurement.index')->with('success', 'Fiche de stock mise à jour.');
    }

    /**
     * Create a purchase order.
     */
    public function storeOrder(): RedirectResponse
    {
        $data = request()->validate([
            'supplier_id' => ['required', 'integer', Rule::exists('suppliers', 'id')->where('is_active', 1)],
            'inventory_source_id' => ['required', 'integer', Rule::exists('inventory_sources', 'id')->where('status', 1)],
            'status' => ['required', 'in:draft,ordered'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'integer', 'distinct', 'exists:products,id'],
            'items.*.qty_ordered' => ['required', 'integer', 'min:1'],
            'items.*.unit_cost' => ['required', 'numeric', 'min:0'],
        ]);

        $this->stockProcurementService->createOrder($data, auth()->guard('admin')->id());

        return redirect()->route('admin.inventory.procurement.index')->with('success', 'Commande fournisseur créée.');
    }

    /**
     * Receive quantities from a supplier order.
     */
    public function receive(int $id): RedirectResponse
    {
        $data = request()->validate([
            'receipts' => ['required', 'array', 'min:1'],
            'receipts.*.item_id' => ['required', 'integer'],
            'receipts.*.quantity' => ['nullable', 'integer', 'min:1'],
            'receipts.*.batch_number' => ['nullable', 'string', 'max:255'],
            'receipts.*.expires_at' => ['nullable', 'date'],
        ]);

        $receipts = array_values(array_filter(
            $data['receipts'],
            fn ($receipt) => isset($receipt['quantity'])
        ));

        if (! $receipts) {
            return back()->withErrors(['receipts' => 'Saisissez au moins une quantité à réceptionner.']);
        }

        $this->stockProcurementService->receive($id, $receipts, auth()->guard('admin')->id());

        return redirect()->route('admin.inventory.procurement.index')->with('success', 'Réception enregistrée.');
    }

    /**
     * Cancel an order that has not been received.
     */
    public function cancel(int $id): RedirectResponse
    {
        $this->stockProcurementService->cancel($id);

        return redirect()->route('admin.inventory.procurement.index')->with('success', 'Commande annulée.');
    }

    /**
     * Place a draft supplier order.
     */
    public function place(int $id): RedirectResponse
    {
        $this->stockProcurementService->placeOrder($id);

        return redirect()->route('admin.inventory.procurement.index')->with('success', 'Commande transmise au fournisseur.');
    }

    /**
     * Record a stock correction with a required audit note.
     */
    public function adjust(): RedirectResponse
    {
        $data = request()->validate([
            'product_id' => ['required', 'integer', 'exists:products,id'],
            'inventory_source_id' => ['required', 'integer', 'exists:inventory_sources,id'],
            'quantity_change' => ['required', 'integer', 'not_in:0'],
            'notes' => ['required', 'string', 'max:2000'],
        ]);

        $this->stockProcurementService->adjust(
            $data['product_id'],
            $data['inventory_source_id'],
            $data['quantity_change'],
            $data['notes'],
            auth()->guard('admin')->id(),
        );

        return redirect()->route('admin.inventory.procurement.index')->with('success', 'Ajustement enregistré.');
    }
}
