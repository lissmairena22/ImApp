<?php

use Livewire\Volt\Component;
use App\Models\{Product, Client, Invoice, InvoiceItem, Order, OrderItem, OrderItemMaterial, Production, CashRegister, Category, Unit};
use Illuminate\Support\Facades\DB;
use Mary\Traits\Toast;

new class extends Component {
    use Toast;

    public $search = '';
    public $cart = [];

    public $client_id = '';
    public $selectedClient = null;

    public $subtotal = 0;
    public $tax = 0;
    public $total = 0;
    public $discount = 0;

    public $received_amount = 0;
    public $change_amount = 0;

    public $order_type = 'Rapido';
    public $delivery_date;
    public $hasProductionItems = false;

    public $showProductModal = false;
    public $new_name = '';
    public $new_price = 0;
    public $new_cost_price = 0;
    public $new_type = 'Producto';
    public $new_requires_production = false;
    public $new_stock = 0;
    public $new_min_stock = 0;
    public $new_items_per_unit = 1;
    public $new_is_sellable = true;
    public $new_category_id = '';
    public $new_unit_id = '';

    public $categoryModal = false;
    public $unitModal = false;
    public $newCategoryName = '';
    public $newUnitName = '';

    public function mount() {
        $this->delivery_date = now()->addDays(2)->format('Y-m-d');
    }

    public function with() {
        return [
            'products' => Product::query()
                ->select(['id', 'name', 'type', 'sale_price', 'stock', 'unit_id', 'estimated_production_time', 'is_sellable', 'is_active'])
                ->where('name', 'like', "%{$this->search}%")
                ->where('is_active', true)
                ->where(function ($query) {
                    $query->where('type', 'Servicio')
                          ->orWhere(fn($q) => $q->where('type', 'Producto')->where('is_sellable', true));
                })
                ->orderBy('id', 'desc')
                ->limit(8)->get(),

            'availableMaterials' => Product::query()
                ->select(['id', 'name', 'stock', 'unit_id', 'type', 'is_sellable', 'is_active'])
                ->with('unit:id,name')
                ->where('type', 'Producto')
                ->where('is_sellable', false)
                ->where('is_active', true)
                ->orderBy('name')
                ->get(),

            'clients' => Client::query()->select(['id', 'name'])->where('is_active', true)->orderBy('name')->get(),
            'categories' => Category::query()->select(['id', 'name'])->orderBy('name')->get(),
            'units' => Unit::query()->select(['id', 'name'])->orderBy('name')->get(),
        ];
    }

    public function updatedClientId($value) {
        $this->selectedClient = Client::find($value);
    }

    public function addToCart(Product $product) {
        $id = $product->id;

        if (isset($this->cart[$id])) {
            $this->cart[$id]['quantity']++;
        } else {
            $this->cart[$id] = $this->cartItemFromProduct($product);
        }
        $this->refreshCartState();
    }

    public function saveCategory() {
        $this->validate([
            'newCategoryName' => 'required|string|max:255|unique:categories,name'
        ]);

        $category = Category::create(['name' => $this->newCategoryName]);

        $this->new_category_id = $category->id;
        $this->categoryModal = false;
        $this->newCategoryName = '';
        $this->success('Categoría creada exitosamente', position: 'toast-top toast-center');
    }

    public function saveUnit() {
        $this->validate([
            'newUnitName' => 'required|string|max:255|unique:units,name'
        ]);

        $unit = Unit::create(['name' => $this->newUnitName]);

        $this->new_unit_id = $unit->id;
        $this->unitModal = false;
        $this->newUnitName = '';
        $this->success('Unidad de medida creada exitosamente', position: 'toast-top toast-center');
    }

    public function saveNewProduct() {
        $this->validate([
            'new_name'           => ['required', 'string', 'max:255', 'regex:/^[a-zA-Z0-9\sáéíóúÁÉÍÓÚñÑ\-\/]+$/'],
            'new_category_id'    => 'required|exists:categories,id',
            'new_unit_id'        => 'required|exists:units,id',
            'new_price'          => 'required|numeric|min:0',
            'new_type'           => 'required|in:Producto,Servicio',
            'new_stock'          => $this->new_type === 'Producto' ? 'required|numeric|min:0' : 'nullable',
            'new_min_stock'      => $this->new_type === 'Producto' ? 'required|numeric|min:0' : 'nullable',
            'new_items_per_unit' => $this->new_type === 'Producto' ? 'required|numeric|min:0.01' : 'nullable',
            'new_is_sellable'    => 'boolean',
        ]);

        $product = Product::create([
            'name'                      => $this->new_name,
            'category_id'               => $this->new_category_id,
            'unit_id'                   => $this->new_unit_id,
            'type'                      => $this->new_type,
            'sale_price'                => $this->new_price ?: 0,
            'cost_price'                => $this->new_cost_price ?: 0,
            'stock'                     => $this->new_type === 'Producto' ? ($this->new_stock ?: 0) : 0,
            'min_stock'                 => $this->new_type === 'Producto' ? ($this->new_min_stock ?: 0) : 0,
            'items_per_unit'            => $this->new_type === 'Producto' ? ($this->new_items_per_unit ?: 1) : 1,
            'is_active'                 => true,
            'manage_stock'              => ($this->new_type === 'Producto'),
            'is_sellable'               => $this->new_type === 'Producto' ? $this->new_is_sellable : true,
            'requires_production'       => $this->new_requires_production,
            'estimated_production_time' => $this->new_requires_production ? 1 : null,
        ]);

        if ($this->new_type === 'Producto' && $this->new_items_per_unit > 1) {
            $looseUnit = Unit::firstOrCreate(['name' => 'Unidad']);

            Product::create([
                'name'                      => $this->new_name . ' (Suelto/Unidad)',
                'category_id'               => $this->new_category_id,
                'unit_id'                   => $looseUnit->id,
                'type'                      => 'Producto',
                'sale_price'                => $this->new_price / $this->new_items_per_unit,
                'cost_price'                => ($this->new_cost_price ?: 0) / $this->new_items_per_unit,
                'stock'                     => 0,
                'min_stock'                 => 0,
                'items_per_unit'            => 1,
                'is_active'                 => true,
                'manage_stock'              => true,
                'is_sellable'               => false,
                'parent_id'                 => $product->id,
            ]);
        }

        $this->addToCart($product);
        $this->showProductModal = false;

        $this->reset([
            'new_name', 'new_price', 'new_cost_price', 'new_type', 'new_requires_production',
            'new_stock', 'new_min_stock', 'new_items_per_unit', 'new_category_id', 'new_unit_id', 'new_is_sellable'
        ]);

        $this->success('Item registrado y añadido al carrito.', position: 'toast-top toast-center');
    }

    public function removeItem($id) {
        unset($this->cart[$id]);
        $this->refreshCartState();
    }

    public function checkProductionRequirements() {
        $items = collect($this->cart);

        $hasProduction = $items->contains('requires_production', true);

        $this->hasProductionItems = $hasProduction;

        if ($hasProduction) {
            $this->order_type = 'Produccion';
        } else {
            $this->order_type = 'Rapido';
        }
    }

    public function calculateTotals() {
        $items = collect($this->cart);

        $this->subtotal = $items->sum(fn($i) => $i['price'] * $i['quantity']);
        $this->tax      = round($this->subtotal * 0.15, 2);
        $this->total    = round(($this->subtotal + $this->tax) - $this->discount, 2);

        $this->received_amount = (float) $this->received_amount;

        if ($this->order_type === 'Rapido' && $this->received_amount == 0) {
            $this->received_amount = $this->total;
        }

        $this->calculateChange();
    }

    public function calculateChange() {
        $this->change_amount = ($this->received_amount > $this->total && $this->order_type === 'Rapido')
            ? round($this->received_amount - $this->total, 2)
            : 0;
    }

    public function updatedReceivedAmount() {
        $this->received_amount = (float) $this->received_amount;
        $this->calculateChange();
    }

    public function updatedOrderType() {
        $this->calculateTotals();
    }

    public function saveAll() {
        $this->received_amount = (float) $this->received_amount;

        if (empty($this->cart)) {
            $this->error('Carrito vacío', position: 'toast-top toast-center');
            return;
        }

        $finalClientId = $this->client_id ?: Client::firstOrCreate(['name' => 'Cliente General'], ['phone' => '00000000'])->id;

        if ($this->order_type === 'Rapido' && $this->received_amount < $this->total) {
            $this->error('Pago insuficiente para factura rápida.', position: 'toast-top toast-center');
            return;
        }

        if (!$this->validateMaterialStock()) return;
        if (!$this->validateProductStock()) return;

        try {
            DB::transaction(function () use ($finalClientId) {
                $actualPayment  = min($this->received_amount, $this->total);
                $pendingBalance = $this->total - $actualPayment;

                $orderStatus   = $this->orderStatus();
                $invoiceStatus = $pendingBalance > 0 ? 'Credito' : 'Pagada';

                $order = Order::create($this->orderData($finalClientId, $orderStatus, $actualPayment));
                $invoice = Invoice::create($this->invoiceData($finalClientId, $order->id, $invoiceStatus));

                foreach ($this->cart as $item) {
                    $itemData = $this->itemData($item);

                    $orderItem = OrderItem::create(array_merge(['order_id' => $order->id], $itemData));
                    $invoiceItem = InvoiceItem::create(array_merge(['invoice_id' => $invoice->id], $itemData));

                    $this->consumeDirectProduct($item);
                    $this->consumeServiceMaterial($item, $orderItem->id, $invoiceItem->id);
                }

                if ($this->hasProductionItems) {
                    Production::create([
                        'order_id' => $order->id,
                        'user_id'  => auth()->id() ?? 1,
                        'status'   => 'Pendiente',
                    ]);
                }

                if ($actualPayment > 0) {
                    $register = CashRegister::where('user_id', auth()->id() ?? 1)
                        ->where('status', 'Abierta')
                        ->first();

                    if ($register) {
                        $register->increment('cash_sales', $actualPayment);
                        $register->increment('system_balance', $actualPayment);
                    }
                }
            });

            $this->success($this->successMessage(), position: 'toast-top toast-center');

            $this->reset([
                'cart', 'client_id', 'selectedClient',
                'received_amount', 'change_amount',
                'hasProductionItems', 'order_type', 'discount',
            ]);

            $this->calculateTotals();

        } catch (\Exception $e) {
            $this->error('Error: ' . $e->getMessage(), position: 'toast-top toast-center');
        }
    }

    private function cartItemFromProduct(Product $product): array {
        $product->loadMissing(['unit']);

        return [
            'id'                       => $product->id,
            'name'                     => $product->name,
            'price'                    => $product->sale_price,
            'quantity'                 => 1,
            'type'                     => $product->type,
            'stock'                    => (float) $product->stock,
            'unit'                     => $product->unit->name ?? 'Und',
            'requires_production'      => (bool) $product->requires_production,
            'measurements'             => '',

            'selected_material_id'     => '',
            'material_lost'            => 0,
        ];
    }

    private function refreshCartState(): void {
        $this->checkProductionRequirements();
        $this->calculateTotals();
    }

    private function orderStatus(): string {
        return match ($this->order_type) {
            'Rapido'     => 'Entregado',
            'Produccion' => 'Pendiente',
            default      => 'Pendiente',
        };
    }

    private function orderData(int $clientId, string $status, float $payment): array {
        return [
            'client_id'               => $clientId,
            'user_id'                 => auth()->id() ?? 1,
            'order_date'              => now(),
            'estimated_delivery_date' => $this->order_type === 'Produccion' ? $this->delivery_date : now(),
            'type'                    => $this->order_type,
            'status'                  => $status,
            'estimated_price'         => $this->total,
            'advance_payment'         => $payment,
        ];
    }

    private function invoiceData(int $clientId, int $orderId, string $status): array {
        return [
            'invoice_number' => 'FAC-' . strtoupper(substr(uniqid(), 7)),
            'client_id'      => $clientId,
            'order_id'       => $orderId,
            'user_id'        => auth()->id() ?? 1,
            'invoice_date'   => now(),
            'subtotal'       => $this->subtotal,
            'tax'            => $this->tax,
            'total'          => $this->total,
            'status'         => $status,
        ];
    }

    private function itemData(array $item): array {
        $materialName = '';

        if ($item['type'] === 'Servicio' && !empty($item['selected_material_id'])) {
            $mat = Product::find($item['selected_material_id']);
            $materialName = $mat ? $mat->name : '';
        }

        return [
            'product_id'    => $item['id'],
            'description'   => $item['name'],
            'quantity'      => $item['quantity'],
            'unit_price'    => $item['price'],
            'subtotal'      => $item['price'] * $item['quantity'],
            'measurements'  => $item['measurements'] ?? '',
            'material'      => $materialName,
            'material_lost' => $item['type'] === 'Servicio' ? (float) ($item['material_lost'] ?? 0) : 0,
        ];
    }

    private function validateMaterialStock(): bool {
        foreach ($this->cart as $item) {
            if ($item['type'] === 'Servicio' && !empty($item['selected_material_id'])) {
                $material = Product::with('unit')->find($item['selected_material_id']);

                $needed = (float)$item['quantity'] + (float)$item['material_lost'];

                if (!$material || $needed > (float) $material->stock) {
                    $available = $material ? number_format((float) $material->stock, 2) . ' ' . ($material->unit->name ?? 'Und') : '0';
                    $this->error("Material insuficiente para {$item['name']}. Disponible: {$available}.", position: 'toast-top toast-center');
                    return false;
                }
            }
        }
        return true;
    }

    private function validateProductStock(): bool {
        foreach ($this->cart as $item) {
            if ($item['type'] !== 'Producto' || $item['requires_production']) {
                continue;
            }

            $product = Product::find($item['id']);

            if (!$product || (float) $item['quantity'] > (float) $product->stock) {
                $available = $product ? number_format((float) $product->stock, 2) . ' ' . ($product->unit->name ?? 'Und') : '0';
                $this->error("Stock insuficiente para {$item['name']}. Disponible: {$available}.", position: 'toast-top toast-center');
                return false;
            }
        }
        return true;
    }

    private function consumeServiceMaterial(array $item, int $orderItemId, int $invoiceItemId): void {
        if ($item['type'] !== 'Servicio' || empty($item['selected_material_id'])) {
            return;
        }

        $material = Product::with('unit')->lockForUpdate()->find($item['selected_material_id']);
        if (!$material) return;

        $used = (float)$item['quantity'];
        $lost = (float)$item['material_lost'] ?? 0;
        $total = $used + $lost;

        if ($total > 0) {
            if ($total > (float) $material->stock) {
                throw new \Exception("Material insuficiente para {$item['name']}. Disponible: {$material->stock}.");
            }

            OrderItemMaterial::create([
                'order_item_id'            => $orderItemId,
                'invoice_item_id'          => $invoiceItemId,
                'material_id'              => $material->id,
                'material_name'            => $material->name,
                'unit_name'                => $material->unit->name ?? 'Und',
                'available_stock_snapshot' => $material->stock,
                'quantity_per_service'     => 1, 
                'quantity_used'            => $used,
                'material_lost'            => $lost,
                'total_consumed'           => $total,
            ]);

            $material->decrement('stock', $total);
        }
    }

    private function consumeDirectProduct(array $item): void {
        if ($item['type'] !== 'Producto' || $item['requires_production']) {
            return;
        }

        $product = Product::lockForUpdate()->find($item['id']);

        if (!$product) {
            throw new \Exception("Producto no encontrado: {$item['name']}.");
        }

        if ((float) $item['quantity'] > (float) $product->stock) {
            throw new \Exception("Stock insuficiente para {$item['name']}. Disponible: {$product->stock}.");
        }

        $product->decrement('stock', $item['quantity']);
    }

    private function successMessage(): string {
        return match ($this->order_type) {
            'Produccion' => 'Pedido en proceso. Orden de taller registrada exitosamente.',
            default      => 'Venta registrada exitosamente.',
        };
    }
}; ?>

<div class="p-4 bg-gray-50/50 min-h-screen">
    <div class="max-w-[1400px] mx-auto grid grid-cols-1 lg:grid-cols-12 gap-6">

        {{-- LADO IZQUIERDO: BÚSQUEDA Y CARRITO --}}
        <div class="lg:col-span-8 flex flex-col gap-6">

            {{-- Barra de Búsqueda Superior --}}
            <div class="bg-white p-3 rounded-2xl shadow-sm border border-gray-100 flex items-center justify-between gap-4">
                <div class="flex items-center gap-3 flex-1 px-2">
                    <x-icon name="o-magnifying-glass" class="w-5 h-5 text-gray-400" />
                    <input wire:model.live="search" type="text" class="w-full border-none bg-transparent focus:ring-0 text-base font-medium placeholder:text-gray-300" placeholder="Buscar productos por nombre o código...">
                </div>
                <x-button icon="o-plus" label="NUEVO ITEM" @click="$wire.showProductModal = true" class="btn-success text-white btn-sm rounded-xl font-bold shadow-sm hover:scale-105 transition-transform" />
            </div>

            {{-- Grid de Productos --}}
            <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 gap-4">
                @forelse($products as $product)
                    <button wire:click="addToCart({{ $product->id }})" class="group relative flex flex-col items-start bg-white p-4 rounded-2xl border border-gray-100 shadow-sm hover:shadow-xl hover:border-indigo-300 hover:-translate-y-1 transition-all duration-300 text-left overflow-hidden">

                        {{-- Icono Decorativo de Fondo --}}
                        <div class="absolute -right-4 -bottom-4 opacity-[0.03] group-hover:opacity-10 transition-opacity">
                            <x-icon name="{{ $product->type === 'Producto' ? 'o-cube' : 'o-sparkles' }}" class="w-24 h-24" />
                        </div>

                        <div class="flex flex-wrap gap-1.5 mb-3">
                            <span class="text-[10px] font-black uppercase tracking-wider px-2 py-1 rounded-md {{ $product->type === 'Producto' ? 'bg-emerald-100 text-emerald-700' : 'bg-blue-100 text-blue-700' }}">
                                <x-icon name="{{ $product->type === 'Producto' ? 'o-cube' : 'o-wrench' }}" class="w-3 h-3 inline pb-0.5" />
                                {{ $product->type }}
                            </span>
                            @if($product->requires_production)
                                <span class="text-[10px] font-black uppercase tracking-wider px-2 py-1 bg-purple-100 text-purple-700 rounded-md">
                                    <x-icon name="o-cog" class="w-3 h-3 inline pb-0.5" /> Taller
                                </span>
                            @endif
                        </div>

                        <h4 class="font-bold text-gray-800 text-sm leading-snug h-10 line-clamp-2 w-full">{{ $product->name }}</h4>
                        <p class="text-indigo-600 font-black text-lg mt-auto pt-2">C$ {{ number_format($product->sale_price, 2) }}</p>
                    </button>
                @empty
                    <div class="col-span-full py-10 text-center text-gray-400">
                        <x-icon name="o-magnifying-glass" class="w-10 h-10 mx-auto mb-2 opacity-50" />
                        <p>No se encontraron productos.</p>
                    </div>
                @endforelse
            </div>

            {{-- Tabla del Carrito --}}
            <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden flex-1 flex flex-col">
                <div class="bg-indigo-50/50 px-5 py-3 border-b border-indigo-100 flex justify-between items-center">
                    <h3 class="font-black text-indigo-900 uppercase tracking-widest text-xs flex items-center gap-2">
                        <x-icon name="o-shopping-cart" class="w-4 h-4" /> Detalle de Venta
                    </h3>
                    <span class="badge badge-indigo badge-sm font-bold">{{ count($cart) }} Ítems</span>
                </div>

                <div class="overflow-x-auto p-2">
                    <table class="w-full text-sm">
                        <thead class="text-[10px] font-black text-gray-400 uppercase tracking-wider border-b border-gray-100">
                            <tr>
                                <th class="px-4 py-3 text-left">Descripción</th>
                                <th class="px-4 py-3 text-center w-24">Cant.</th>
                                <th class="px-4 py-3 text-right">Precio</th>
                                <th class="px-4 py-3 w-48">Material Base</th>
                                <th class="px-4 py-3 text-right">Subtotal</th>
                                <th class="px-2 py-3"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-50">
                            @forelse($cart as $index => $item)
                                <tr class="hover:bg-gray-50/50 transition-colors group">
                                    <td class="px-4 py-3">
                                        <div class="font-bold text-gray-800">{{ $item['name'] }}</div>
                                        <div class="text-[10px] text-gray-400 font-medium">{{ $item['type'] }}</div>
                                    </td>
                                    <td class="px-4 py-3 text-center">
                                        <input type="number" wire:model.live="cart.{{$index}}.quantity" wire:change="calculateTotals" class="w-16 border-gray-200 bg-gray-50 rounded-lg p-1.5 text-center font-black text-indigo-600 focus:ring-indigo-500 focus:bg-white transition-colors">
                                    </td>
                                    <td class="px-4 py-3 text-right text-gray-500 font-medium">C$ {{ number_format($item['price'], 2) }}</td>
                                    <td class="px-4 py-3">
                                        @if($item['type'] === 'Servicio')
                                            <select wire:model.live="cart.{{$index}}.selected_material_id" class="text-xs border border-gray-200 bg-gray-50 rounded-lg p-1.5 w-full focus:ring-indigo-500 focus:bg-white transition-colors text-gray-600">
                                                <option value="">-- No requiere --</option>
                                                @foreach($availableMaterials as $mat)
                                                    <option value="{{ $mat->id }}">{{ $mat->name }} ({{ number_format($mat->stock, 0) }})</option>
                                                @endforeach
                                            </select>
                                        @else
                                            <span class="text-[10px] text-gray-300 font-medium italic block text-center">No aplica</span>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3 text-right font-black text-gray-800">C$ {{ number_format($item['price'] * $item['quantity'], 2) }}</td>
                                    <td class="px-2 py-3 text-center">
                                        <button wire:click="removeItem('{{ $index }}')" class="p-2 text-gray-300 hover:text-red-500 hover:bg-red-50 rounded-lg transition-all opacity-0 group-hover:opacity-100">
                                            <x-icon name="o-trash" class="w-4 h-4" />
                                        </button>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="py-16 text-center">
                                        <div class="flex flex-col items-center justify-center text-gray-300 space-y-3">
                                            <div class="p-4 bg-gray-50 rounded-full">
                                                <x-icon name="o-shopping-bag" class="w-8 h-8 text-gray-300" />
                                            </div>
                                            <p class="font-medium text-gray-400">El carrito está vacío</p>
                                        </div>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                {{-- Mermas --}}
                @if(collect($cart)->contains(fn($i) => $i['type'] === 'Servicio' && !empty($i['selected_material_id'])))
                    <div class="bg-orange-50/50 border-t border-orange-100 p-4 mt-auto">
                        <h4 class="text-[10px] font-black text-orange-600 uppercase tracking-widest mb-3 flex items-center gap-2">
                            <x-icon name="o-exclamation-triangle" class="w-4 h-4" /> Control de Mermas
                        </h4>
                        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
                            @foreach($cart as $index => $item)
                                @if($item['type'] === 'Servicio' && !empty($item['selected_material_id']))
                                    @php
                                        $selectedMat = $availableMaterials->firstWhere('id', $item['selected_material_id']);
                                        $matName = $selectedMat ? $selectedMat->name : 'Material...';
                                    @endphp
                                    <div class="bg-white p-2.5 rounded-xl border border-orange-200 flex items-center justify-between gap-3 shadow-sm">
                                        <div class="truncate flex-1">
                                            <div class="text-xs font-bold text-gray-800 truncate">{{ $item['name'] }}</div>
                                            <div class="text-[10px] text-gray-500 truncate">{{ $matName }}</div>
                                        </div>
                                        <div class="w-20 shrink-0 relative tooltip tooltip-top" data-tip="Merma desperdiciada">
                                            <input type="number" step="0.01" wire:model.live="cart.{{$index}}.material_lost" class="w-full text-xs border border-orange-200 rounded-lg p-1.5 text-center font-bold text-orange-600 focus:ring-orange-500 bg-orange-50" placeholder="0.00">
                                        </div>
                                    </div>
                                @endif
                            @endforeach
                        </div>
                    </div>
                @endif
            </div>
        </div>

        {{-- LADO DERECHO: PANEL DE COBRO (TICKET) --}}
        <div class="lg:col-span-4">
            <div class="bg-white rounded-3xl shadow-xl border border-gray-100 overflow-hidden sticky top-4 flex flex-col">

                <div class="p-5 space-y-5 flex-1">
                    {{-- Cliente --}}
                    <div>
                        <label class="text-[10px] font-black text-gray-400 uppercase tracking-widest block mb-2">Cliente Asignado</label>
                        <select wire:model.live="client_id" class="w-full border-gray-200 bg-gray-50 rounded-xl text-sm font-bold text-gray-700 focus:ring-indigo-500 py-3 cursor-pointer">
                            <option value="">-- Público General --</option>
                            @foreach($clients as $client)
                                <option value="{{ $client->id }}">{{ $client->name }}</option>
                            @endforeach
                        </select>
                    </div>

                    {{-- Modo Venta --}}
                    <div class="grid grid-cols-2 gap-3">
                        <div class="col-span-{{ $order_type === 'Produccion' ? '1' : '2' }}">
                            <label class="text-[10px] font-black text-gray-400 uppercase tracking-widest block mb-2">Modalidad</label>
                            <select wire:model.live="order_type"
                                    @disabled($hasProductionItems)
                                    class="w-full border-gray-200 rounded-xl text-xs font-bold py-3 cursor-pointer transition-colors {{ $hasProductionItems ? 'bg-purple-50 text-purple-700 border-purple-200' : 'bg-gray-50 text-gray-700' }}">
                                <option value="Rapido">⚡ Venta Directa</option>
                                <option value="Produccion">⚙️ Orden de Taller</option>
                            </select>
                        </div>
                        @if($order_type === 'Produccion')
                            <div class="col-span-1 animate-fade-in">
                                <label class="text-[10px] font-black text-gray-400 uppercase tracking-widest block mb-2">Entrega</label>
                                <input type="date" wire:model="delivery_date" class="w-full border-gray-200 bg-gray-50 rounded-xl text-xs font-bold text-gray-700 py-3">
                            </div>
                        @endif
                    </div>

                    {{-- Totales (Ticket Oscuro) --}}
                    <div class="bg-[#1e1b4b] rounded-2xl p-6 text-white shadow-inner relative overflow-hidden">
                        {{-- Efecto de brillo sutil --}}
                        <div class="absolute top-0 right-0 w-32 h-32 bg-indigo-500 rounded-full blur-3xl opacity-20 -mr-10 -mt-10"></div>

                        <div class="space-y-2 relative z-10">
                            <div class="flex justify-between text-xs text-indigo-200 font-medium">
                                <span>Subtotal</span>
                                <span>C$ {{ number_format($subtotal, 2) }}</span>
                            </div>
                            <div class="flex justify-between text-xs text-indigo-200 font-medium pb-3 border-b border-indigo-800">
                                <span>IVA (15%)</span>
                                <span>C$ {{ number_format($tax, 2) }}</span>
                            </div>
                            <div class="flex justify-between items-end pt-3">
                                <span class="text-xs font-bold text-indigo-300 uppercase tracking-widest mb-1">Total a Pagar</span>
                                <span class="text-4xl font-black tracking-tight text-white">C$ {{ number_format($total, 2) }}</span>
                            </div>
                        </div>
                    </div>

                    {{-- Entrada de Pago --}}
                    <div>
                        <label class="text-[10px] font-black text-indigo-600 uppercase tracking-widest mb-2 block">
                            {{ $order_type === 'Rapido' ? 'Efectivo Recibido' : 'Anticipo / Abono Inicial' }}
                        </label>
                        <div class="relative flex items-center">
                            <div class="absolute left-4 w-8 h-8 bg-indigo-100 rounded-lg flex items-center justify-center">
                                <x-icon name="o-banknotes" class="w-5 h-5 text-indigo-600" />
                            </div>
                            <input type="number" wire:model.live="received_amount" class="w-full border-2 border-indigo-100 bg-white rounded-2xl pl-14 pr-4 py-4 text-2xl font-black text-gray-800 focus:border-indigo-500 focus:ring-4 focus:ring-indigo-500/20 transition-all shadow-sm">
                        </div>
                    </div>

                    {{-- Cambio --}}
                    @if($order_type === 'Rapido')
                        <div class="flex justify-between items-center bg-emerald-50 px-5 py-4 rounded-2xl border border-emerald-100">
                            <span class="text-[10px] font-black text-emerald-600 uppercase tracking-widest flex items-center gap-2">
                                <x-icon name="o-arrow-uturn-left" class="w-4 h-4" /> Su Cambio
                            </span>
                            <span class="text-2xl font-black text-emerald-600">C$ {{ number_format($change_amount, 2) }}</span>
                        </div>
                    @endif
                </div>

                {{-- Botón Cobrar --}}
                @php
                    $recv = (float) $received_amount;
                    $hasStockIssues = collect($cart)->contains(function ($item) use ($availableMaterials) {
                        if ($item['type'] === 'Servicio' && !empty($item['selected_material_id'])) {
                            $material = $availableMaterials->firstWhere('id', $item['selected_material_id']);
                            $required = (float) $item['quantity'] + (float) ($item['material_lost'] ?? 0);
                            return !$material || $required > (float) $material->stock;
                        }
                        return $item['type'] === 'Producto'
                            && !$item['requires_production']
                            && (float) $item['quantity'] > (float) ($item['stock'] ?? PHP_FLOAT_MAX);
                    });

                    $isDisabled = match(true) {
                        $hasStockIssues => true,
                        $order_type === 'Rapido' => $recv < (float) $total,
                        default => false,
                    };

                    $btnLabel = match(true) {
                        $order_type === 'Produccion' => 'ENVIAR A TALLER',
                        default => 'COBRAR TICKET',
                    };
                @endphp

                <button wire:click="saveAll"
                        @if($isDisabled) disabled @endif
                        class="w-full py-6 font-black text-white text-lg tracking-widest transition-all
                                {{ $isDisabled ? 'bg-gray-200 text-gray-400 cursor-not-allowed' : ($order_type === 'Produccion' ? 'bg-purple-600 hover:bg-purple-700 active:bg-purple-800' : 'bg-indigo-600 hover:bg-indigo-700 active:bg-indigo-800') }}
                                flex items-center justify-center gap-3">
                    <x-icon name="{{ $order_type === 'Produccion' ? 'o-cog' : 'o-check-circle' }}" class="w-6 h-6 {{ $isDisabled ? 'opacity-50' : '' }}" />
                    {{ $btnLabel }}
                </button>
            </div>
        </div>
    </div>

    <x-modal wire:model="showProductModal" title="Registro Rápido de Producto o Servicio" separator class="backdrop-blur">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">

            <div class="md:col-span-2">
                <x-input label="Nombre" wire:model="new_name" placeholder="Ej: Lona 13oz" icon="o-pencil" />
            </div>

            <div class="flex items-end gap-2">
                <div class="flex-1">
                    <x-select label="Categoría" wire:model="new_category_id" :options="$categories" placeholder="Seleccione..." icon="o-tag" />
                </div>
                <x-button icon="o-plus" class="btn-primary btn-square mb-1" wire:click="$set('categoryModal', true)" tooltip="Nueva Categoría" />
            </div>

            <div class="flex items-end gap-2">
                <div class="flex-1">
                    <x-select label="Unidad de Medida" wire:model="new_unit_id" :options="$units" placeholder="Seleccione..." icon="o-scale" />
                </div>
                <x-button icon="o-plus" class="btn-primary btn-square mb-1" wire:click="$set('unitModal', true)" tooltip="Nueva Unidad" />
            </div>

            <x-input label="Precio de Venta" wire:model="new_price" type="number" step="0.01" icon="o-currency-dollar" />
            <x-select label="Tipo" wire:model.live="new_type" :options="[['id'=>'Producto', 'name'=>'Producto'], ['id'=>'Servicio', 'name'=>'Servicio']]" />

            @if($new_type === 'Producto')
                <div class="md:col-span-2 bg-base-200 p-3 rounded-lg border border-gray-100">
                    <x-toggle label="Disponible para Venta Directa" wire:model="new_is_sellable" hint="Apágalo si es un insumo solo de uso interno de las máquinas (Ej: Tinta, Planchas)." right class="toggle-primary"/>
                </div>

                <x-input label="Costo (Opcional)" wire:model="new_cost_price" type="number" step="0.01" icon="o-currency-dollar" />
                <x-input label="Cant. por empaque" wire:model="new_items_per_unit" type="number" step="0.01" icon="o-arrows-pointing-out" hint="1 si no se desglosa" />
                <x-input label="Stock Inicial" wire:model="new_stock" type="number" step="0.01" icon="o-archive-box" />
                <x-input label="Stock Mínimo" wire:model="new_min_stock" type="number" step="0.01" icon="o-bell-alert" />
            @endif

            <div class="flex items-center gap-4 p-3 bg-purple-50 rounded-lg md:col-span-2 border border-purple-100 mt-2">
                <x-checkbox label="¿Requiere Taller / Producción?" wire:model="new_requires_production" tight />
            </div>
        </div>

        <x-slot:actions>
            <x-button label="Cancelar" @click="$wire.showProductModal = false" />
            <x-button label="Guardar y Añadir" class="btn-primary" wire:click="saveNewProduct" spinner />
        </x-slot:actions>
    </x-modal>

    <x-modal wire:model="categoryModal" title="Nueva Categoría" separator>
        <x-form wire:submit="saveCategory">
            <x-input label="Nombre de la Categoría" wire:model="newCategoryName" placeholder="Ej: Sublimación" required />
            <x-slot:actions>
                <x-button label="Cancelar" wire:click="$set('categoryModal', false)" class="btn-ghost" />
                <x-button label="Guardar" type="submit" class="btn-primary" spinner="saveCategory" />
            </x-slot:actions>
        </x-form>
    </x-modal>

    <x-modal wire:model="unitModal" title="Nueva Unidad de Medida" separator>
        <x-form wire:submit="saveUnit">
            <x-input label="Nombre de la Unidad" wire:model="newUnitName" placeholder="Ej: Galón" required />
            <x-slot:actions>
                <x-button label="Cancelar" wire:click="$set('unitModal', false)" class="btn-ghost" />
                <x-button label="Guardar" type="submit" class="btn-primary" spinner="saveUnit" />
            </x-slot:actions>
        </x-form>
    </x-modal>

</div>
