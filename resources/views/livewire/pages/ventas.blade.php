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

    // Propiedades del Modal de Nuevo Producto
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

    // Propiedades Modales Secundarios
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

            // Solo materiales (no sellables)
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

        // 1. Guardar el producto principal
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

        // 2. Automatización: Crear sueltos si es un empaque con más de 1 unidad
        if ($this->new_type === 'Producto' && $this->new_items_per_unit > 1) {
            $unidadSuela = Unit::firstOrCreate(['name' => 'Unidad']);

            Product::create([
                'name'                      => $this->new_name . ' (Suelto/Unidad)',
                'category_id'               => $this->new_category_id,
                'unit_id'                   => $unidadSuela->id,
                'type'                      => 'Producto',
                'sale_price'                => $this->new_price / $this->new_items_per_unit,
                'cost_price'                => ($this->new_cost_price ?: 0) / $this->new_items_per_unit,
                'stock'                     => 0, // Inicia en cero hasta abrir un paquete
                'min_stock'                 => 0,
                'items_per_unit'            => 1,
                'is_active'                 => true,
                'manage_stock'              => true,
                'is_sellable'               => false, // Para uso interno por defecto
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

            // Campos para el control manual de material (solo para Servicios)
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

                // Nueva lógica: Cantidad del servicio + Merma
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
                'quantity_per_service'     => 1, // Se mantiene en 1 para evitar errores de DB
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

<div class="p-4 bg-gray-100 min-h-screen">
    <div class="max-w-[1400px] mx-auto grid grid-cols-1 lg:grid-cols-12 gap-4">
        <div class="lg:col-span-8 space-y-4">
            <div class="bg-white p-3 rounded-xl shadow-sm flex items-center justify-between gap-3">
                <div class="flex items-center gap-3 flex-1">
                    <div class="bg-indigo-50 p-2 rounded-lg">
                        <svg class="w-5 h-5 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
                    </div>
                    <input wire:model.live="search" type="text" class="w-full border-none focus:ring-0 text-sm" placeholder="Buscar productos o servicios...">
                </div>
                <button @click="$wire.showProductModal = true" class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-lg text-xs font-bold flex items-center gap-2 transition-all shadow-sm">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
                    NUEVO ITEM
                </button>
            </div>

            <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
                @foreach($products as $product)
                    <button wire:click="addToCart({{ $product->id }})" class="bg-white p-4 rounded-xl border border-transparent hover:border-indigo-500 hover:shadow-md transition text-left group relative overflow-hidden">
                        <div class="flex flex-wrap gap-1 mb-2">
                            <span class="text-[9px] font-bold uppercase px-1.5 py-0.5 rounded {{ $product->type === 'Producto' ? 'bg-green-100 text-green-600' : 'bg-blue-100 text-blue-600' }}">
                                {{ $product->type }}
                            </span>
                            @if($product->requires_production)
                                <span class="text-[9px] font-bold uppercase px-1.5 py-0.5 bg-purple-100 text-purple-600 rounded">Taller</span>
                            @endif
                        </div>
                        <h4 class="font-bold text-gray-700 text-sm leading-tight h-8">{{ $product->name }}</h4>
                        <p class="text-indigo-600 font-black mt-2 italic">C$ {{ number_format($product->sale_price, 2) }}</p>
                    </button>
                @endforeach
            </div>

            <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
               <table class="w-full text-sm">
                    <thead>
                        <tr class="bg-indigo-600 text-white uppercase text-[10px] tracking-wider">
                            <th class="px-4 py-3 text-left">Producto / Servicio</th>
                            <th class="px-4 py-3 text-center">Cant.</th>
                            <th class="px-4 py-3 text-right">Precio</th>
                            <th class="px-4 py-3 w-64">Material a Usar</th>
                            <th class="px-4 py-3 text-right">Subtotal</th>
                            <th class="px-4 py-3"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse($cart as $index => $item)
                            <tr class="hover:bg-indigo-50/30">
                                <td class="px-4 py-3">
                                    <div class="font-bold text-gray-800">{{ $item['name'] }}</div>
                                    <div class="text-[9px] text-gray-400">{{ $item['type'] }}</div>
                                </td>

                                <td class="px-4 py-3 text-center">
                                    <input type="number" wire:model.live="cart.{{$index}}.quantity" wire:change="calculateTotals" class="w-14 border-gray-200 rounded p-1 text-center font-bold text-indigo-600">
                                </td>

                                <td class="px-4 py-3 text-right text-gray-500">C$ {{ number_format($item['price'], 2) }}</td>

                                <td class="px-4 py-3">
                                    @if($item['type'] === 'Servicio')
                                        <div class="flex flex-col gap-2">
                                            <select wire:model.live="cart.{{$index}}.selected_material_id" class="text-[10px] border border-gray-200 rounded p-1 w-full focus:ring-indigo-500">
                                                <option value="">-- Seleccionar material --</option>
                                                @foreach($availableMaterials as $mat)
                                                    <option value="{{ $mat->id }}">{{ $mat->name }} (Disp: {{ number_format($mat->stock, 2) }})</option>
                                                @endforeach
                                            </select>
                                        </div>
                                    @else
                                        <span class="text-[10px] text-gray-300 italic">No aplica</span>
                                    @endif
                                </td>

                                <td class="px-4 py-3 text-right font-black text-gray-700">C$ {{ number_format($item['price'] * $item['quantity'], 2) }}</td>

                                <td class="px-4 py-3 text-center">
                                    <button wire:click="removeItem('{{ $index }}')" class="text-red-400 hover:text-red-600">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M6 18L18 6M6 6l12 12"></path></svg>
                                    </button>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="px-4 py-10 text-center text-gray-400">Carrito vacío</td></tr>
                        @endforelse
                    </tbody>
                </table>

                @if(collect($cart)->contains(fn($i) => $i['type'] === 'Servicio' && !empty($i['selected_material_id'])))
                    <div class="bg-red-50 border-t border-red-100 p-4">
                        <h4 class="text-[11px] font-black text-red-600 uppercase mb-3 flex items-center gap-2">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path></svg>
                            Registro de Mermas (Material Perdido)
                        </h4>
                        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
                            @foreach($cart as $index => $item)
                                @if($item['type'] === 'Servicio' && !empty($item['selected_material_id']))
                                    @php
                                        $selectedMat = $availableMaterials->firstWhere('id', $item['selected_material_id']);
                                        $matName = $selectedMat ? $selectedMat->name : 'Material no encontrado';
                                    @endphp
                                    <div class="bg-white p-2 rounded-lg border border-red-200 flex items-center justify-between gap-3 shadow-sm">
                                        <div class="truncate flex-1">
                                            <div class="text-xs font-bold text-gray-800 truncate" title="{{ $item['name'] }}">{{ $item['name'] }}</div>
                                            <div class="text-[9px] text-gray-500 truncate" title="{{ $matName }}">{{ $matName }}</div>
                                        </div>
                                        <div class="w-24 shrink-0 relative">
                                            <input type="number" step="0.01" wire:model.live="cart.{{$index}}.material_lost" class="w-full text-xs border border-red-200 rounded p-1 text-center font-bold text-red-600 focus:ring-red-500 bg-red-50/50" placeholder="0.00">
                                        </div>
                                    </div>
                                @endif
                            @endforeach
                        </div>
                    </div>
                @endif
            </div>
        </div>

        <div class="lg:col-span-4">
            <div class="bg-white rounded-xl shadow-lg border border-gray-200 p-5 space-y-5 sticky top-4">
                <div class="bg-gray-50 p-4 rounded-xl border border-gray-100">
                    <label class="text-[10px] font-bold text-gray-400 uppercase block mb-2">Cliente</label>
                    <select wire:model.live="client_id" class="w-full border-gray-200 rounded-lg text-sm mb-3 focus:ring-indigo-500">
                        <option value="">-- Cliente General --</option>
                        @foreach($clients as $client)
                            <option value="{{ $client->id }}">{{ $client->name }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="text-[10px] font-bold text-gray-400 uppercase mb-1 block">Modo de Venta</label>
                        <select wire:model.live="order_type"
                                @disabled($hasProductionItems)
                                class="w-full border-gray-200 rounded-lg text-xs font-bold {{ $hasProductionItems ? 'bg-purple-50 text-purple-700 border-purple-200' : 'bg-white' }}">
                            <option value="Rapido">Factura Rápida</option>
                            <option value="Produccion">Orden de Taller</option>
                        </select>
                    </div>
                    @if($order_type === 'Produccion')
                        <div>
                            <label class="text-[10px] font-bold text-gray-400 uppercase mb-1 block">Entrega Estimada</label>
                            <input type="date" wire:model="delivery_date" class="w-full border-gray-200 rounded-lg text-xs">
                        </div>
                    @endif
                </div>

                <div class="bg-indigo-900 rounded-xl p-5 text-white shadow-md">
                    <div class="space-y-1">
                        <div class="flex justify-between text-[10px] opacity-70 uppercase font-bold tracking-widest">
                            <span>Subtotal</span>
                            <span>C$ {{ number_format($subtotal, 2) }}</span>
                        </div>
                        <div class="flex justify-between text-[10px] opacity-70 uppercase font-bold tracking-widest">
                            <span>IVA (15%)</span>
                            <span>C$ {{ number_format($tax, 2) }}</span>
                        </div>
                        <div class="flex justify-between items-center pt-2 mt-2 border-t border-white/20 font-black italic">
                            <span class="text-lg">TOTAL</span>
                            <span class="text-3xl">C$ {{ number_format($total, 2) }}</span>
                        </div>
                    </div>
                </div>

                <div class="bg-white border-2 border-dashed border-gray-200 rounded-xl p-4 space-y-4">
                    <div>
                        <label class="text-[10px] font-black text-indigo-600 uppercase mb-2 block">
                            {{ $order_type === 'Rapido' ? '💵 Paga con:' : '📥 Abono / Pago Inicial:' }}
                        </label>
                        <div class="relative">
                            <span class="absolute left-3 top-3 text-gray-400 font-bold">C$</span>
                            <input type="number" wire:model.live="received_amount" class="w-full border-gray-200 rounded-lg pl-10 text-2xl font-black text-gray-800 focus:ring-indigo-500">
                        </div>
                    </div>

                    @if($order_type === 'Rapido')
                        <div class="flex justify-between items-center bg-green-50 p-3 rounded-lg border border-green-100">
                            <span class="text-[10px] font-bold text-green-600 uppercase">Cambio:</span>
                            <span class="text-xl font-black text-green-700">C$ {{ number_format($change_amount, 2) }}</span>
                        </div>
                    @endif
                </div>

                @php
                    $recv = (float) $received_amount;
                    $hasStockIssues = collect($cart)->contains(function ($item) use ($availableMaterials) {

                        // Verifica errores en servicios considerando Cantidad + Merma
                        if ($item['type'] === 'Servicio' && !empty($item['selected_material_id'])) {
                            $material = $availableMaterials->firstWhere('id', $item['selected_material_id']);
                            $required = (float) $item['quantity'] + (float) ($item['material_lost'] ?? 0);
                            return !$material || $required > (float) $material->stock;
                        }

                        // Verifica errores en productos directos
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
                        $order_type === 'Produccion' => 'PROCESAR PEDIDO TALLER',
                        default => 'FINALIZAR VENTA',
                    };
                @endphp

                <button wire:click="saveAll"
                        @if($isDisabled) disabled @endif
                        class="w-full py-4 rounded-xl font-black text-white shadow-lg transition-all active:scale-95 flex items-center justify-center gap-2
                                {{ $isDisabled ? 'bg-gray-300 cursor-not-allowed' : ($order_type === 'Produccion' ? 'bg-purple-600 hover:bg-purple-700' : 'bg-indigo-600 hover:bg-indigo-700') }}">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
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
