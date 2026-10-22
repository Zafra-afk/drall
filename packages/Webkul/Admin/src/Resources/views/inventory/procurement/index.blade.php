<x-admin::layouts>
    <x-slot:title>Stocks et approvisionnements</x-slot>

    <div class="flex flex-col gap-6">
        <div class="flex items-center justify-between gap-4 max-sm:flex-wrap">
            <h1 class="text-xl font-bold text-gray-800 dark:text-white">Stocks et approvisionnements</h1>
        </div>

        @if ($errors->any())
            <div class="rounded-lg border border-red-300 bg-red-50 p-4 text-red-700">
                <ul class="list-inside list-disc">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <section class="rounded-lg border border-gray-200 bg-white p-6 dark:border-gray-800 dark:bg-gray-900">
            <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
                <h2 class="text-lg font-semibold text-gray-800 dark:text-white">Fiches produit et codes-barres</h2>
                <form action="{{ route('admin.inventory.procurement.index') }}" method="GET" class="flex gap-2">
                    <input class="transparent-input" name="product_search" value="{{ request('product_search') }}" placeholder="Rechercher par SKU ou code-barres">
                    <button class="secondary-button" type="submit">Rechercher</button>
                </form>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="text-gray-600 dark:text-gray-300">
                        <tr><th class="py-2">SKU</th><th>Code-barres</th><th>Unité</th><th>Seuil minimum</th><th>Fournisseur</th><th></th></tr>
                    </thead>
                    <tbody class="text-gray-800 dark:text-gray-200">
                        @forelse ($products as $product)
                            <tr class="border-t border-gray-100 dark:border-gray-800">
                                <td class="py-2">{{ $product->name ?: $product->sku }}</td>
                                <td colspan="5">
                                    @if (bouncer()->hasPermission('catalog.stock_management.manage'))
                                        <form action="{{ route('admin.inventory.procurement.products.update', $product->id) }}" method="POST" class="flex flex-wrap items-center gap-2 py-2">
                                            @csrf
                                            @method('PUT')
                                            <input name="product_id" type="hidden" value="{{ $product->id }}">
                                            <input class="transparent-input w-40" name="barcode" value="{{ $product->barcode }}" maxlength="255" placeholder="Code-barres">
                                            <select class="transparent-input" name="unit" required>
                                                @foreach (['piece' => 'Pièce', 'kilogram' => 'Kilogramme', 'gram' => 'Gramme', 'liter' => 'Litre', 'milliliter' => 'Millilitre', 'pack' => 'Paquet', 'carton' => 'Carton'] as $unit => $label)
                                                    <option value="{{ $unit }}" @selected(($product->unit ?? 'piece') === $unit)>{{ $label }}</option>
                                                @endforeach
                                            </select>
                                            <input class="transparent-input w-24" name="minimum_stock" type="number" min="0" step="1" value="{{ $product->minimum_stock ?? 0 }}" required aria-label="Seuil minimal">
                                            <select class="transparent-input" name="supplier_id">
                                                <option value="">Sans fournisseur associé</option>
                                                @foreach ($suppliers as $supplier)
                                                    <option value="{{ $supplier->id }}" @selected($product->supplier_id === $supplier->id)>{{ $supplier->name }}</option>
                                                @endforeach
                                            </select>
                                            <button class="secondary-button" type="submit">Enregistrer</button>
                                        </form>
                                    @else
                                        <div class="flex flex-wrap gap-4 py-2">
                                            <span>{{ $product->barcode ?: 'Code-barres non défini' }}</span>
                                            <span>{{ $product->unit ?: 'piece' }}</span>
                                            <span>Seuil : {{ $product->minimum_stock ?? 0 }}</span>
                                        </div>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td class="py-2" colspan="6">Aucun produit correspondant.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="mt-4">{{ $products->links() }}</div>
            <p class="mt-3 text-xs text-gray-500">Les quantités de stock suivent la granularité entière du catalogue Drall existant.</p>
        </section>

        <div class="grid gap-6 xl:grid-cols-2">
            <section class="rounded-lg border border-gray-200 bg-white p-6 dark:border-gray-800 dark:bg-gray-900">
                <h2 class="mb-4 text-lg font-semibold text-gray-800 dark:text-white">Ajouter un fournisseur</h2>

                @if (bouncer()->hasPermission('catalog.stock_management.manage'))
                    <form action="{{ route('admin.inventory.procurement.suppliers.store') }}" method="POST" class="grid gap-3">
                        @csrf
                        <input class="transparent-input" name="name" required maxlength="255" placeholder="Nom du fournisseur">
                        <div class="grid gap-3 md:grid-cols-2">
                            <input class="transparent-input" name="phone" maxlength="64" placeholder="Téléphone">
                            <input class="transparent-input" name="email" type="email" maxlength="255" placeholder="Courriel">
                        </div>
                        <textarea class="transparent-input" name="address" rows="2" maxlength="2000" placeholder="Adresse"></textarea>
                        <button class="primary-button w-fit" type="submit">Enregistrer le fournisseur</button>
                    </form>
                @endif

                <div class="mt-5 border-t border-gray-200 pt-4 dark:border-gray-800">
                    <h3 class="mb-2 font-medium text-gray-800 dark:text-white">Fournisseurs enregistrés</h3>
                    <ul class="space-y-1 text-sm text-gray-600 dark:text-gray-300">
                        @forelse ($suppliers as $supplier)
                            <li>{{ $supplier->name }}{{ $supplier->phone ? ' — '.$supplier->phone : '' }}</li>
                        @empty
                            <li>Aucun fournisseur enregistré.</li>
                        @endforelse
                    </ul>
                </div>
            </section>

            <section class="rounded-lg border border-gray-200 bg-white p-6 dark:border-gray-800 dark:bg-gray-900">
                <h2 class="mb-4 text-lg font-semibold text-gray-800 dark:text-white">Créer une commande fournisseur</h2>

                @if (bouncer()->hasPermission('catalog.stock_management.manage'))
                    <form action="{{ route('admin.inventory.procurement.orders.store') }}" method="POST" class="grid gap-3" id="purchase-order-form">
                    @csrf
                    <div class="grid gap-3 md:grid-cols-2">
                        <select class="transparent-input" name="supplier_id" required>
                            <option value="">Choisir un fournisseur</option>
                            @foreach ($suppliers as $supplier)
                                @if ($supplier->is_active)
                                    <option value="{{ $supplier->id }}">{{ $supplier->name }}</option>
                                @endif
                            @endforeach
                        </select>
                        <select class="transparent-input" name="inventory_source_id" required>
                            <option value="">Choisir un dépôt</option>
                            @foreach ($sources as $source)
                                <option value="{{ $source->id }}">{{ $source->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <select class="transparent-input" name="status" required>
                        <option value="draft">Brouillon</option>
                        <option value="ordered">Commandée</option>
                    </select>

                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-sm">
                            <thead class="text-gray-600 dark:text-gray-300">
                                <tr>
                                    <th class="py-2">Produit / SKU</th>
                                    <th class="px-2">Quantité</th>
                                    <th class="px-2">Coût unitaire (MGA)</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody id="purchase-order-items">
                                <tr class="purchase-order-item">
                                    <td class="relative py-2">
                                        <div class="product-picker relative" data-search-url="{{ route('admin.inventory.procurement.products.search') }}">
                                            <input class="transparent-input product-search-input" type="search" placeholder="Rechercher par nom, SKU ou code-barres" autocomplete="off" required>
                                            <input name="items[0][product_id]" type="hidden">
                                            <div class="product-picker-results absolute z-10 hidden max-h-60 w-full overflow-y-auto rounded border bg-white text-gray-800 shadow-lg dark:bg-gray-800 dark:text-white" role="listbox"></div>
                                        </div>
                                    </td>
                                    <td class="px-2"><input class="transparent-input w-24" type="number" min="1" step="1" name="items[0][qty_ordered]" value="1" required></td>
                                    <td class="px-2"><input class="transparent-input w-36" type="number" min="0" step="0.01" name="items[0][unit_cost]" required></td>
                                    <td class="px-2"></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <button class="secondary-button w-fit" id="add-purchase-order-item" type="button">Ajouter un article</button>
                    <textarea class="transparent-input" name="notes" rows="2" maxlength="2000" placeholder="Notes de commande"></textarea>
                    <button class="primary-button w-fit" type="submit">Créer la commande</button>
                    </form>

                    <template id="purchase-order-item-template">
                        <tr class="purchase-order-item">
                            <td class="relative py-2">
                                <div class="product-picker relative" data-search-url="{{ route('admin.inventory.procurement.products.search') }}">
                                    <input class="transparent-input product-search-input" type="search" placeholder="Rechercher par nom, SKU ou code-barres" autocomplete="off" required>
                                    <input data-field="product_id" type="hidden">
                                    <div class="product-picker-results absolute z-10 hidden max-h-60 w-full overflow-y-auto rounded border bg-white text-gray-800 shadow-lg dark:bg-gray-800 dark:text-white" role="listbox"></div>
                                </div>
                            </td>
                            <td class="px-2"><input class="transparent-input w-24" data-field="qty_ordered" type="number" min="1" step="1" value="1" required></td>
                            <td class="px-2"><input class="transparent-input w-36" data-field="unit_cost" type="number" min="0" step="0.01" required></td>
                            <td class="px-2"><button class="secondary-button remove-purchase-order-item" type="button" aria-label="Supprimer l’article">×</button></td>
                        </tr>
                    </template>
                @endif
            </section>
        </div>

        <section class="rounded-lg border border-gray-200 bg-white p-6 dark:border-gray-800 dark:bg-gray-900">
            <h2 class="mb-4 text-lg font-semibold text-gray-800 dark:text-white">Commandes d’approvisionnement</h2>
            <div class="space-y-4">
                @forelse ($orders as $order)
                    <article class="rounded-lg border border-gray-200 p-4 dark:border-gray-700">
                        <div class="mb-3 flex flex-wrap items-center justify-between gap-3">
                            <div>
                                <h3 class="font-semibold text-gray-800 dark:text-white">{{ $order->number }}</h3>
                                <p class="text-sm text-gray-600 dark:text-gray-300">{{ $order->supplier_name }} · {{ $order->source_name }} · {{ $order->status }}</p>
                            </div>
                            @if ($order->status === 'draft')
                                @if (bouncer()->hasPermission('catalog.stock_management.manage'))
                                    <form action="{{ route('admin.inventory.procurement.orders.place', $order->id) }}" method="POST">
                                        @csrf
                                        <button class="primary-button" type="submit">Transmettre la commande</button>
                                    </form>
                                @endif
                            @endif
                            @if (in_array($order->status, ['draft', 'ordered'], true) && bouncer()->hasPermission('catalog.stock_management.manage'))
                                <form action="{{ route('admin.inventory.procurement.orders.cancel', $order->id) }}" method="POST">
                                    @csrf
                                    <button class="secondary-button" type="submit">Annuler la commande</button>
                                </form>
                            @endif
                        </div>

                        <table class="mb-3 w-full text-left text-sm">
                            <thead class="text-gray-600 dark:text-gray-300">
                                <tr><th class="py-2">SKU</th><th>Commandé</th><th>Reçu</th><th>Coût unitaire</th></tr>
                            </thead>
                            <tbody class="text-gray-800 dark:text-gray-200">
                                @foreach ($orderItems->get($order->id, []) as $item)
                                    <tr class="border-t border-gray-100 dark:border-gray-800">
                                        <td class="py-2">{{ $item->sku }}</td>
                                        <td>{{ $item->qty_ordered }}</td>
                                        <td>{{ $item->qty_received }}</td>
                                        <td>{{ number_format((float) $item->unit_cost, 2, ',', ' ') }} MGA</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>

                        @if (in_array($order->status, ['ordered', 'partially_received'], true) && bouncer()->hasPermission('catalog.stock_management.manage'))
                            <form action="{{ route('admin.inventory.procurement.orders.receive', $order->id) }}" method="POST" class="overflow-x-auto">
                                @csrf
                                <table class="mb-3 w-full text-left text-sm">
                                    <thead class="text-gray-600 dark:text-gray-300">
                                        <tr><th class="py-2">Article</th><th>À recevoir</th><th>Lot</th><th>Date de péremption</th></tr>
                                    </thead>
                                    <tbody>
                                        @foreach ($orderItems->get($order->id, []) as $item)
                                            @if ($item->qty_ordered > $item->qty_received)
                                                <tr class="border-t border-gray-100 dark:border-gray-800">
                                                    <td class="py-2">{{ $item->sku }}</td>
                                                    <td>
                                                        <input class="transparent-input w-24" name="receipts[{{ $item->id }}][item_id]" type="hidden" value="{{ $item->id }}">
                                                        <input class="transparent-input w-24" name="receipts[{{ $item->id }}][quantity]" type="number" min="1" max="{{ $item->qty_ordered - $item->qty_received }}">
                                                    </td>
                                                    <td><input class="transparent-input w-36" name="receipts[{{ $item->id }}][batch_number]" maxlength="255"></td>
                                                    <td><input class="transparent-input" name="receipts[{{ $item->id }}][expires_at]" type="date"></td>
                                                </tr>
                                            @endif
                                        @endforeach
                                    </tbody>
                                </table>
                                <button class="primary-button" type="submit">Enregistrer la réception</button>
                            </form>
                        @endif
                    </article>
                @empty
                    <p class="text-sm text-gray-600 dark:text-gray-300">Aucune commande fournisseur.</p>
                @endforelse
            </div>

            <div class="mt-5">{{ $orders->links() }}</div>
        </section>

        <div class="grid gap-6 xl:grid-cols-2">
            <section class="rounded-lg border border-gray-200 bg-white p-6 dark:border-gray-800 dark:bg-gray-900">
                <h2 class="mb-4 text-lg font-semibold text-gray-800 dark:text-white">Ajuster le stock</h2>
                @if (bouncer()->hasPermission('catalog.stock_management.manage'))
                    <form action="{{ route('admin.inventory.procurement.adjustments.store') }}" method="POST" class="grid gap-3">
                    @csrf
                    <div class="product-picker relative" data-search-url="{{ route('admin.inventory.procurement.products.search') }}">
                        <input class="transparent-input product-search-input" type="search" placeholder="Rechercher par nom, SKU ou code-barres" autocomplete="off" required>
                        <input name="product_id" type="hidden">
                        <div class="product-picker-results absolute z-10 hidden max-h-60 w-full overflow-y-auto rounded border bg-white text-gray-800 shadow-lg dark:bg-gray-800 dark:text-white" role="listbox"></div>
                    </div>
                    <select class="transparent-input" name="inventory_source_id" required>
                        <option value="">Choisir un dépôt</option>
                        @foreach ($sources as $source)
                            <option value="{{ $source->id }}">{{ $source->name }}</option>
                        @endforeach
                    </select>
                    <input class="transparent-input" name="quantity_change" type="number" step="1" required placeholder="Variation (ex. 5 ou -2)">
                    <textarea class="transparent-input" name="notes" rows="2" required maxlength="2000" placeholder="Motif de l’ajustement"></textarea>
                    <button class="primary-button w-fit" type="submit">Enregistrer l’ajustement</button>
                    </form>
                @endif
            </section>

            <section class="rounded-lg border border-gray-200 bg-white p-6 dark:border-gray-800 dark:bg-gray-900">
                <h2 class="mb-4 text-lg font-semibold text-gray-800 dark:text-white">Alertes de stock faible</h2>
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm">
                        <thead class="text-gray-600 dark:text-gray-300"><tr><th class="py-2">SKU</th><th>Dépôt</th><th>Disponible</th><th>Seuil</th></tr></thead>
                        <tbody class="text-gray-800 dark:text-gray-200">
                            @forelse ($lowStock as $stock)
                                <tr class="border-t border-gray-100 dark:border-gray-800"><td class="py-2">{{ $stock->sku }}</td><td>{{ $stock->source_name }}</td><td>{{ $stock->qty }}</td><td>{{ $stock->minimum_stock }}</td></tr>
                            @empty
                                <tr><td class="py-2" colspan="4">Aucune alerte définie pour les produits enregistrés.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </section>
        </div>

        <div class="grid gap-6 xl:grid-cols-2">
            <section class="rounded-lg border border-gray-200 bg-white p-6 dark:border-gray-800 dark:bg-gray-900">
                <h2 class="mb-4 text-lg font-semibold text-gray-800 dark:text-white">Lots à échéance proche</h2>
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm">
                        <thead class="text-gray-600 dark:text-gray-300"><tr><th class="py-2">SKU</th><th>Lot</th><th>Expiration</th><th>Quantité reçue</th></tr></thead>
                        <tbody class="text-gray-800 dark:text-gray-200">
                            @forelse ($expiringLots as $lot)
                                <tr class="border-t border-gray-100 dark:border-gray-800"><td class="py-2">{{ $lot->sku }}</td><td>{{ $lot->batch_number }}</td><td>{{ $lot->expires_at }}</td><td>{{ $lot->quantity_remaining }}</td></tr>
                            @empty
                                <tr><td class="py-2" colspan="4">Aucun lot arrivant à échéance dans les 30 jours.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </section>

            <section class="rounded-lg border border-gray-200 bg-white p-6 dark:border-gray-800 dark:bg-gray-900">
                <h2 class="mb-4 text-lg font-semibold text-gray-800 dark:text-white">Historique des mouvements</h2>
                <div class="max-h-96 overflow-auto">
                    <table class="w-full text-left text-sm">
                        <thead class="sticky top-0 bg-white text-gray-600 dark:bg-gray-900 dark:text-gray-300"><tr><th class="py-2">SKU</th><th>Dépôt</th><th>Type</th><th>Variation</th><th>Solde</th><th>Date</th></tr></thead>
                        <tbody class="text-gray-800 dark:text-gray-200">
                            @forelse ($movements as $movement)
                                <tr class="border-t border-gray-100 dark:border-gray-800"><td class="py-2">{{ $movement->sku }}</td><td>{{ $movement->source_name }}</td><td>{{ $movement->type }}</td><td>{{ $movement->quantity_change }}</td><td>{{ $movement->quantity_after }}</td><td>{{ $movement->created_at }}</td></tr>
                            @empty
                                <tr><td class="py-2" colspan="6">Aucun mouvement enregistré.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </section>
        </div>
    </div>

    @if (bouncer()->hasPermission('catalog.stock_management.manage'))
        @pushOnce('scripts')
        <script>
            const purchaseOrderItems = document.getElementById('purchase-order-items');
            let purchaseOrderItemIndex = 1;

            document.getElementById('add-purchase-order-item').addEventListener('click', () => {
                const row = document.getElementById('purchase-order-item-template').content.cloneNode(true);

                row.querySelectorAll('[data-field]').forEach((field) => {
                    field.name = `items[${purchaseOrderItemIndex}][${field.dataset.field}]`;
                });

                purchaseOrderItems.append(row);
                purchaseOrderItemIndex++;
            });

            purchaseOrderItems.addEventListener('click', (event) => {
                if (event.target.classList.contains('remove-purchase-order-item')) {
                    event.target.closest('tr').remove();
                }
            });

            document.addEventListener('input', async (event) => {
                const input = event.target.closest('.product-search-input');

                if (! input) {
                    return;
                }

                const picker = input.closest('.product-picker');
                const hiddenInput = picker.querySelector('input[type="hidden"]');
                const results = picker.querySelector('.product-picker-results');
                const query = input.value.trim();

                hiddenInput.value = '';
                input.setCustomValidity('');
                results.replaceChildren();
                results.classList.add('hidden');

                if (query.length < 2) {
                    return;
                }

                window.clearTimeout(input.searchTimeout);
                input.searchTimeout = window.setTimeout(async () => {
                    try {
                        const url = new URL(picker.dataset.searchUrl, window.location.origin);
                        url.searchParams.set('q', query);

                        const response = await fetch(url, {
                            headers: { Accept: 'application/json' },
                        });

                        if (! response.ok) {
                            throw new Error('Product search failed.');
                        }

                        const products = await response.json();

                        if (input.value.trim() !== query) {
                            return;
                        }

                        if (! products.length) {
                            results.textContent = 'Aucun produit trouvé.';
                            results.classList.remove('hidden');

                            return;
                        }

                        products.forEach((product) => {
                            const option = document.createElement('button');
                            option.className = 'block w-full px-3 py-2 text-left hover:bg-gray-100 dark:hover:bg-gray-700';
                            option.type = 'button';
                            option.dataset.productId = product.id;
                            option.textContent = product.label;
                            results.append(option);
                        });

                        results.classList.remove('hidden');
                    } catch {
                        results.textContent = 'La recherche des produits est indisponible.';
                        results.classList.remove('hidden');
                    }
                }, 250);
            });

            document.addEventListener('click', (event) => {
                const option = event.target.closest('.product-picker-results button');

                if (option) {
                    const picker = option.closest('.product-picker');

                    picker.querySelector('.product-search-input').value = option.textContent;
                    picker.querySelector('input[type="hidden"]').value = option.dataset.productId;
                    picker.querySelector('.product-picker-results').classList.add('hidden');

                    return;
                }

                if (! event.target.closest('.product-picker')) {
                    document.querySelectorAll('.product-picker-results').forEach((results) => results.classList.add('hidden'));
                }
            });

            document.addEventListener('submit', (event) => {
                const missingProduct = [...event.target.querySelectorAll('.product-picker')]
                    .find((picker) => ! picker.querySelector('input[type="hidden"]').value);

                if (missingProduct) {
                    event.preventDefault();

                    const input = missingProduct.querySelector('.product-search-input');

                    input.setCustomValidity('Choisissez un produit dans les résultats.');
                    input.reportValidity();
                }
            });
        </script>
        @endPushOnce
    @endif
</x-admin::layouts>
