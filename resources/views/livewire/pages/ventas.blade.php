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

    public $quick_subtotal = 0;
    public $production_subtotal = 0;
    public $quick_total = 0;
    public $production_total = 0;
    public $minimum_payment = 0;
    public $is_mixed = false;
    public $pay_full = false;

    public $received_amount = 0;
    public $change_amount = 0;

    public $order_type = 'Rapido';
    public $delivery_date;
    public $hasProductionItems = false;

    public $showProductModal = false;
    public $new_name = '';
    public $new_price = 0;
    public $new_type = 'Producto';
    public $new_requires_production = false;
    public $new_stock = 0;
    public $new_category_id = '';
    public $new_unit_id = '';

    public function mount() {
        $this->delivery_date = now()->addDays(2)->format('Y-m-d');
    }

    public function with() {
        return [
            'products' => Product::where('name', 'like', "%{$this->search}%")
                                ->where('is_active', true)
                                ->where(function ($query) {
                                    $query->where('type', 'Servicio')
                                          ->orWhere(fn($q) => $q->where('type', 'Producto')->where('is_sellable', true));
                                })
                                ->orderBy('id', 'desc')
                                ->limit(8)->get(),
            'clients' => Client::where('is_active', true)->get(),
            'categories' => Category::all(),
            'units' => Unit::all(),
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

    public function saveNewProduct() {
        $this->validate([
            'new_name'        => 'required|min:3',
            'new_price'       => 'required|numeric|min:0',
            'new_type'        => 'required|in:Producto,Servicio',
            'new_category_id' => 'required|exists:categories,id',
            'new_unit_id'     => 'required|exists:units,id',
        ]);

        $product = Product::create([
            'name'                => $this->new_name,
            'sale_price'          => $this->new_price,
            'type'                => $this->new_type,
            'requires_production' => $this->new_requires_production,
            'stock'               => ($this->new_type === 'Producto') ? $this->new_stock : 0,
            'category_id'         => $this->new_category_id,
            'unit_id'             => $this->new_unit_id,
            'is_active'           => true,
            'manage_stock'        => ($this->new_type === 'Producto'),
            'is_sellable'         => ($this->new_type === 'Producto'),
            'estimated_production_time' => $this->new_requires_production ? 1 : null,
        ]);

        $this->addToCart($product);
        $this->showProductModal = false;
        $this->reset(['new_name', 'new_price', 'new_type', 'new_requires_production', 'new_stock', 'new_category_id', 'new_unit_id']);
        $this->success('Item registrado y añadido.', position: 'toast-top toast-center');
    }

    public function removeItem($id) {
        unset($this->cart[$id]);
        $this->refreshCartState();
    }

    public function checkProductionRequirements() {
        $items = collect($this->cart);

        $hasProduction = $items->contains('requires_production', true);
        $hasQuick      = $items->contains('requires_production', false);

        $this->hasProductionItems = $hasProduction;
        $this->is_mixed           = $hasProduction && $hasQuick;

        if ($this->is_mixed) {
            $this->order_type = 'Mixto';
        } elseif ($hasProduction) {
            $this->order_type = 'Produccion';
        } else {
            $this->order_type = 'Rapido';
        }

        if (!$this->is_mixed) {
            $this->pay_full = false;
        }
    }

    public function calculateTotals() {
        $items = collect($this->cart);

        $this->quick_subtotal      = $items->where('requires_production', false)->sum(fn($i) => $i['price'] * $i['quantity']);
        $this->production_subtotal = $items->where('requires_production', true)->sum(fn($i) => $i['price'] * $i['quantity']);

        $this->subtotal         = $this->quick_subtotal + $this->production_subtotal;
        $this->tax              = round($this->subtotal * 0.15, 2);
        $this->total            = round(($this->subtotal + $this->tax) - $this->discount, 2);
        $this->quick_total      = round($this->quick_subtotal * 1.15, 2);
        $this->production_total = round($this->production_subtotal * 1.15, 2);
        $this->minimum_payment  = $this->is_mixed ? $this->quick_total : $this->total;

        $this->received_amount = (float) $this->received_amount;

        if ($this->order_type === 'Rapido' && $this->received_amount == 0 && !$this->is_mixed) {
            $this->received_amount = $this->total;
        }

        if ($this->is_mixed && $this->pay_full) {
            $this->received_amount = $this->total;
        }

        $this->calculateChange();
    }

    public function calculateChange() {
        $this->change_amount = ($this->received_amount > $this->total && in_array($this->order_type, ['Rapido', 'Mixto']))
            ? round($this->received_amount - $this->total, 2)
            : 0;
    }

    public function updatedPayFull($value) {
        $this->received_amount = (float) ($value ? $this->total : $this->minimum_payment);
        $this->calculateChange();
    }

    public function updatedReceivedAmount() {
        $this->received_amount = (float) $this->received_amount;
        if ($this->is_mixed && $this->received_amount != $this->total) {
            $this->pay_full = false;
        }
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

        $finalClientId = $this->client_id
            ?: Client::firstOrCreate(['name' => 'Cliente General'], ['phone' => '00000000'])->id;

        if ($this->order_type === 'Rapido' && $this->received_amount < $this->total) {
            $this->error('Pago insuficiente para factura rápida.', position: 'toast-top toast-center');
            return;
        }

        if ($this->is_mixed && $this->received_amount < $this->minimum_payment) {
            $this->error('Pago insuficiente. Mínimo requerido: C$ ' . number_format($this->minimum_payment, 2), position: 'toast-top toast-center');
            return;
        }

        if (!$this->validateMaterialStock()) {
            return;
        }

        if (!$this->validateProductStock()) {
            return;
        }

        try {
            DB::transaction(function () use ($finalClientId) {
                $actualPayment  = min($this->received_amount, $this->total);
                $pendingBalance = $this->total - $actualPayment;

                $orderStatus = $this->orderStatus();
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
                'is_mixed', 'pay_full',
                'quick_subtotal', 'production_subtotal',
                'quick_total', 'production_total', 'minimum_payment',
            ]);
            $this->calculateTotals();

        } catch (\Exception $e) {
            $this->error('Error: ' . $e->getMessage(), position: 'toast-top toast-center');
        }
    }

    private function cartItemFromProduct(Product $product): array {
        $product->loadMissing(['materiales.unidad', 'unidad']);
        $materialOptions = $this->materialOptionsFor($product);
        $selectedMaterial = $materialOptions[0] ?? null;

        return [
            'id'                  => $product->id,
            'name'                => $product->name,
            'price'               => $product->sale_price,
            'quantity'            => 1,
            'type'                => $product->type,
            'stock'               => (float) $product->stock,
            'unit'                => $product->unidad->name ?? 'Und',
            'requires_production' => (bool) $product->requires_production,
            'measurements'        => '',
            'material'            => $selectedMaterial['name'] ?? '',
            'material_lost'       => 0,
            'material_options'    => $materialOptions,
            'selected_material_id'=> $selectedMaterial['id'] ?? '',
            'material_stock'      => $selectedMaterial['stock'] ?? 0,
            'material_unit'       => $selectedMaterial['unit'] ?? '',
            'material_per_unit'   => $selectedMaterial['quantity'] ?? 1,
        ];
    }

    public function updateCartMaterial($index, $materialId = null): void {
        if (!isset($this->cart[$index])) {
            return;
        }

        if ($materialId !== null) {
            $this->cart[$index]['selected_material_id'] = $materialId;
        }

        $selected = collect($this->cart[$index]['material_options'] ?? [])
            ->firstWhere('id', (int) $this->cart[$index]['selected_material_id']);

        if (!$selected) {
            $this->cart[$index]['material'] = '';
            $this->cart[$index]['material_stock'] = 0;
            $this->cart[$index]['material_unit'] = '';
            $this->cart[$index]['material_per_unit'] = 1;
            return;
        }

        $this->cart[$index]['material'] = $selected['name'];
        $this->cart[$index]['material_stock'] = $selected['stock'];
        $this->cart[$index]['material_unit'] = $selected['unit'];
        $this->cart[$index]['material_per_unit'] = $selected['quantity'];
    }

    private function refreshCartState(): void {
        $this->checkProductionRequirements();
        $this->calculateTotals();
    }

    private function orderStatus(): string {
        return match ($this->order_type) {
            'Rapido'     => 'Entregado',
            'Produccion' => 'Pendiente',
            'Mixto'      => 'Parcial',
            default      => 'Pendiente',
        };
    }

    private function orderData(int $clientId, string $status, float $payment): array {
        return [
            'client_id'               => $clientId,
            'user_id'                 => auth()->id() ?? 1,
            'order_date'              => now(),
            'estimated_delivery_date' => in_array($this->order_type, ['Produccion', 'Mixto']) ? $this->delivery_date : now(),
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
        return [
            'product_id'     => $item['id'],
            'description'    => $item['name'],
            'quantity'       => $item['quantity'],
            'unit_price'     => $item['price'],
            'subtotal'       => $item['price'] * $item['quantity'],
            'measurements'   => $item['measurements'] ?? '',
            'material'       => $item['material'] ?? '',
            'material_lost'  => $item['type'] === 'Servicio' ? (float) ($item['material_lost'] ?? 0) : 0,
        ];
    }

    private function materialOptionsFor(Product $product): array {
        if ($product->type !== 'Servicio') {
            return [];
        }

        return $product->materiales
            ->map(fn($material) => [
                'id'       => $material->id,
                'name'     => $material->name,
                'stock'    => (float) $material->stock,
                'unit'     => $material->unidad->name ?? 'Und',
                'quantity' => (float) $material->pivot->quantity,
            ])
            ->values()
            ->all();
    }

    private function materialConsumption(array $item): array {
        $quantity = max(0, (float) ($item['quantity'] ?? 0));
        $lost = $item['type'] === 'Servicio' ? max(0, (float) ($item['material_lost'] ?? 0)) : 0;
        $perUnit = max(0, (float) ($item['material_per_unit'] ?? 1));
        $used = $quantity * $perUnit;

        return [
            'used' => $used,
            'lost' => $lost,
            'total' => $used + $lost,
        ];
    }

    private function validateMaterialStock(): bool {
        foreach ($this->cart as $item) {
            if ($item['type'] !== 'Servicio' || empty($item['material_options'])) {
                continue;
            }

            if (empty($item['selected_material_id'])) {
                $this->error("Seleccione el material para {$item['name']}.", position: 'toast-top toast-center');
                return false;
            }

            if (!$this->selectedMaterialOption($item)) {
                $this->error("El material seleccionado no pertenece a la receta de {$item['name']}.", position: 'toast-top toast-center');
                return false;
            }

            $material = Product::with('unidad')->find($item['selected_material_id']);
            $consumption = $this->materialConsumption($item);

            if (!$material || $consumption['total'] > (float) $material->stock) {
                $available = $material ? number_format((float) $material->stock, 2) . ' ' . ($material->unidad->name ?? 'Und') : '0';
                $this->error("Material insuficiente para {$item['name']}. Disponible: {$available}.", position: 'toast-top toast-center');
                return false;
            }
        }

        return true;
    }

    private function selectedMaterialOption(array $item): ?array {
        return collect($item['material_options'] ?? [])
            ->firstWhere('id', (int) ($item['selected_material_id'] ?? 0));
    }

    private function validateProductStock(): bool {
        foreach ($this->cart as $item) {
            if ($item['type'] !== 'Producto' || $item['requires_production']) {
                continue;
            }

            $product = Product::find($item['id']);

            if (!$product || (float) $item['quantity'] > (float) $product->stock) {
                $available = $product ? number_format((float) $product->stock, 2) . ' ' . ($product->unidad->name ?? 'Und') : '0';
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

        if (!$this->selectedMaterialOption($item)) {
            throw new \Exception("El material seleccionado no pertenece a la receta de {$item['name']}.");
        }

        $material = Product::with('unidad')->lockForUpdate()->find($item['selected_material_id']);

        if (!$material) {
            return;
        }

        $consumption = $this->materialConsumption($item);

        if ($consumption['total'] <= 0) {
            return;
        }

        if ($consumption['total'] > (float) $material->stock) {
            throw new \Exception("Material insuficiente para {$item['name']}. Disponible: {$material->stock}.");
        }

        OrderItemMaterial::create([
            'order_item_id'             => $orderItemId,
            'invoice_item_id'           => $invoiceItemId,
            'material_id'               => $material->id,
            'material_name'             => $material->name,
            'unit_name'                 => $material->unidad->name ?? 'Und',
            'available_stock_snapshot'  => $material->stock,
            'quantity_per_service'      => $item['material_per_unit'] ?? 1,
            'quantity_used'             => $consumption['used'],
            'material_lost'             => $consumption['lost'],
            'total_consumed'            => $consumption['total'],
        ]);

        $material->decrement('stock', $consumption['total']);
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
            'Mixto'      => $this->pay_full
                ? 'Venta mixta registrada exitosamente. La parte del taller quedó en producción.'
                : 'Venta mixta registrada exitosamente. Pedido en proceso para taller.',
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
                            <th class="px-4 py-3 text-left font-bold">Descripción</th>
                            <th class="px-4 py-3 text-center font-bold">Cant.</th>
                            <th class="px-4 py-3 text-right font-bold">Precio</th>
                            <th class="px-4 py-3 text-right font-bold">Subtotal</th>
                            <th class="px-4 py-3"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse($cart as $index => $item)
                            <tr class="hover:bg-indigo-50/30 transition-colors">
                                <td class="px-4 py-3">
                                    <div class="flex items-center gap-2">
                                        <span class="font-bold text-gray-800">{{ $item['name'] }}</span>
                                        @if($is_mixed)
                                            @if($item['requires_production'])
                                                <span class="text-[8px] font-bold px-1.5 py-0.5 bg-purple-100 text-purple-600 rounded-full">TALLER</span>
                                            @else
                                                <span class="text-[8px] font-bold px-1.5 py-0.5 bg-green-100 text-green-600 rounded-full">INMEDIATO</span>
                                            @endif
                                        @endif
                                    </div>
                                    @if($item['type'] === 'Servicio')
                                        @php
                                            $materialUsed = (float) $item['quantity'] * (float) ($item['material_per_unit'] ?? 1);
                                            $materialLost = (float) ($item['material_lost'] ?? 0);
                                            $materialTotal = $materialUsed + $materialLost;
                                            $materialStock = (float) ($item['material_stock'] ?? 0);
                                            $materialOver = !empty($item['material_options']) && $materialTotal > $materialStock;
                                        @endphp
                                        <div class="flex flex-wrap gap-2 mt-2">
                                            <div class="flex items-center gap-1 bg-gray-50 px-2 py-1 rounded border border-gray-200">
                                                <span class="text-[9px] text-gray-400 uppercase">Medidas:</span>
                                                <input type="text" wire:model="cart.{{$index}}.measurements" class="text-[10px] border-none bg-transparent p-0 w-20 focus:ring-0">
                                            </div>
                                            @if(!empty($item['material_options']))
                                                <div class="flex items-center gap-1 bg-gray-50 px-2 py-1 rounded border border-gray-200">
                                                    <span class="text-[9px] text-gray-400 uppercase">Mat:</span>
                                                    <select wire:model="cart.{{$index}}.selected_material_id" wire:change="updateCartMaterial('{{ $index }}', $event.target.value)" class="text-[10px] border-none bg-transparent p-0 w-28 focus:ring-0">
                                                        @foreach($item['material_options'] as $materialOption)
                                                            <option value="{{ $materialOption['id'] }}">{{ $materialOption['name'] }}</option>
                                                        @endforeach
                                                    </select>
                                                </div>
                                            @else
                                                <div class="flex items-center gap-1 bg-yellow-50 px-2 py-1 rounded border border-yellow-100">
                                                    <span class="text-[9px] text-yellow-600 uppercase font-bold">Sin receta</span>
                                                </div>
                                            @endif
                                            <div class="flex items-center gap-1 bg-red-50 px-2 py-1 rounded border border-red-100">
                                                <span class="text-[9px] text-red-400 uppercase">Perdido:</span>
                                                <input type="number" min="0" step="1" wire:model.live="cart.{{$index}}.material_lost" class="text-[10px] border-none bg-transparent p-0 w-14 focus:ring-0 text-red-600 font-bold">
                                            </div>
                                        </div>
                                        @if(!empty($item['material_options']))
                                            <div class="flex flex-wrap gap-2 mt-2 text-[10px]">
                                                <span class="px-2 py-1 rounded bg-slate-50 text-slate-500 border border-slate-100">
                                                    Disponible: <b class="{{ $materialOver ? 'text-red-600' : 'text-slate-700' }}">{{ number_format($materialStock, 2) }}</b> {{ $item['material_unit'] ?? 'Und' }}
                                                </span>
                                                <span class="px-2 py-1 rounded {{ $materialOver ? 'bg-red-50 text-red-600 border-red-100' : 'bg-green-50 text-green-700 border-green-100' }} border">
                                                    Consume: <b>{{ number_format($materialTotal, 2) }}</b> {{ $item['material_unit'] ?? 'Und' }}
                                                </span>
                                            </div>
                                        @endif
                                    @elseif($item['type'] === 'Producto')
                                        @php $productOver = (float) $item['quantity'] > (float) ($item['stock'] ?? 0); @endphp
                                        <div class="mt-2 text-[10px]">
                                            <span class="px-2 py-1 rounded {{ $productOver ? 'bg-red-50 text-red-600 border-red-100' : 'bg-green-50 text-green-700 border-green-100' }} border">
                                                Disponible: <b>{{ number_format((float) ($item['stock'] ?? 0), 2) }}</b> {{ $item['unit'] ?? 'Und' }}
                                            </span>
                                        </div>
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-center">
                                    <input type="number" wire:model.live="cart.{{$index}}.quantity" wire:change="calculateTotals" class="w-14 border-gray-200 rounded p-1 text-center font-bold text-indigo-600">
                                </td>
                                <td class="px-4 py-3 text-right text-gray-500 italic">C$ {{ number_format($item['price'], 2) }}</td>
                                <td class="px-4 py-3 text-right font-black text-gray-700">C$ {{ number_format($item['price'] * $item['quantity'], 2) }}</td>
                                <td class="px-4 py-3 text-center">
                                    <button wire:click="removeItem('{{ $index }}')" class="text-red-300 hover:text-red-600 transition-colors">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M6 18L18 6M6 6l12 12"></path></svg>
                                    </button>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="px-4 py-10 text-center text-gray-400 font-medium">No hay productos en el carrito</td></tr>
                        @endforelse
                    </tbody>
                </table>
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
                                class="w-full border-gray-200 rounded-lg text-xs font-bold
                                    {{ $is_mixed ? 'bg-orange-50 text-orange-700 border-orange-200' :
                                       ($hasProductionItems ? 'bg-purple-50 text-purple-700 border-purple-200' : 'bg-white') }}">
                            <option value="Rapido">Factura Rápida</option>
                            <option value="Produccion">Orden de Taller</option>
                            @if($is_mixed)
                                <option value="Mixto">Venta Mixta</option>
                            @endif
                        </select>
                    </div>
                    @if(in_array($order_type, ['Produccion', 'Mixto']))
                        <div>
                            <label class="text-[10px] font-bold text-gray-400 uppercase mb-1 block">Entrega Estimada</label>
                            <input type="date" wire:model="delivery_date" class="w-full border-gray-200 rounded-lg text-xs">
                        </div>
                    @endif
                </div>

                @if($is_mixed)
                    <div class="bg-orange-50 border border-orange-200 rounded-xl p-4">
                        <div class="flex items-center gap-2 mb-3">
                            <span class="text-base">🔀</span>
                            <span class="text-xs font-black text-orange-700 uppercase tracking-wide">Venta Mixta Detectada</span>
                        </div>
                        <div class="space-y-2 text-xs">
                            <div class="flex justify-between items-center bg-white rounded-lg px-3 py-2 border border-green-100">
                                <div class="flex items-center gap-1.5">
                                    <span class="w-2 h-2 rounded-full bg-green-500 inline-block"></span>
                                    <span class="text-gray-600 font-medium">Entrega inmediata</span>
                                </div>
                                <span class="font-black text-green-700">C$ {{ number_format($quick_total, 2) }}</span>
                            </div>
                            <div class="flex justify-between items-center bg-white rounded-lg px-3 py-2 border border-purple-100">
                                <div class="flex items-center gap-1.5">
                                    <span class="w-2 h-2 rounded-full bg-purple-500 inline-block"></span>
                                    <span class="text-gray-600 font-medium">Orden de taller</span>
                                </div>
                                <span class="font-black text-purple-700">C$ {{ number_format($production_total, 2) }}</span>
                            </div>
                        </div>
                        <div class="mt-3 pt-3 border-t border-orange-200">
                            <div class="flex justify-between items-center">
                                <span class="text-[10px] font-bold text-orange-600 uppercase">Pago mínimo requerido:</span>
                                <span class="font-black text-orange-800 text-sm">C$ {{ number_format($minimum_payment, 2) }}</span>
                            </div>
                            <p class="text-[9px] text-orange-500 mt-0.5">Cubre los artículos de entrega inmediata.</p>
                        </div>
                        <div class="mt-3 flex items-center justify-between bg-white rounded-lg px-3 py-2.5 border border-orange-200 cursor-pointer"
                             wire:click="$toggle('pay_full')">
                            <div>
                                <p class="text-xs font-bold text-gray-700">Pagar factura completa</p>
                                <p class="text-[9px] text-gray-400">El cliente cancela todo ahora</p>
                            </div>
                            <div class="w-9 h-5 rounded-full transition-colors flex items-center px-0.5 {{ $pay_full ? 'bg-indigo-600 justify-end' : 'bg-gray-200 justify-start' }}">
                                <div class="w-4 h-4 bg-white rounded-full shadow"></div>
                            </div>
                        </div>
                    </div>
                @endif

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
                    @if($is_mixed)
                        <div>
                            <label class="text-[10px] font-black text-orange-600 uppercase mb-2 block">
                                {{ $pay_full ? '💵 Pago total:' : '💵 Abono inicial (mín. C$ ' . number_format($minimum_payment, 2) . '):' }}
                            </label>
                            <div class="relative">
                                <span class="absolute left-3 top-3 text-gray-400 font-bold">C$</span>
                                <input type="number"
                                       wire:model.live="received_amount"
                                       min="0"
                                       max="{{ $total }}"
                                       class="w-full border-gray-200 rounded-lg pl-10 text-2xl font-black text-gray-800 focus:ring-orange-400 {{ $pay_full ? 'bg-gray-50 cursor-not-allowed' : '' }}">
                            </div>
                        </div>
                        @php $pending = max(0, $total - $received_amount); @endphp
                        @if($pending > 0)
                            <div class="flex justify-between items-center bg-purple-50 p-3 rounded-lg border border-purple-100">
                                <span class="text-[10px] font-bold text-purple-600 uppercase">Saldo taller:</span>
                                <span class="text-lg font-black text-purple-700">C$ {{ number_format($pending, 2) }}</span>
                            </div>
                        @endif
                        @if($change_amount > 0)
                            <div class="flex justify-between items-center bg-green-50 p-3 rounded-lg border border-green-100">
                                <span class="text-[10px] font-bold text-green-600 uppercase">Cambio:</span>
                                <span class="text-xl font-black text-green-700">C$ {{ number_format($change_amount, 2) }}</span>
                            </div>
                        @endif
                    @else
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
                    @endif
                </div>

                @php
                    $recv       = (float) $received_amount;
                    $hasStockIssues = collect($cart)->contains(function ($item) {
                        if ($item['type'] === 'Servicio' && !empty($item['material_options'])) {
                            $required = ((float) $item['quantity'] * (float) ($item['material_per_unit'] ?? 1)) + (float) ($item['material_lost'] ?? 0);
                            return empty($item['selected_material_id']) || $required > (float) ($item['material_stock'] ?? 0);
                        }

                        return $item['type'] === 'Producto'
                            && !$item['requires_production']
                            && (float) $item['quantity'] > (float) ($item['stock'] ?? PHP_FLOAT_MAX);
                    });
                    $isDisabled = match(true) {
                        $hasStockIssues       => true,
                        $is_mixed            => $recv < (float) $minimum_payment,
                        $order_type === 'Rapido' => $recv < (float) $total,
                        default              => false,
                    };
                    $btnLabel = match(true) {
                        $is_mixed && $pay_full       => 'PROCESAR VENTA MIXTA (PAGADA)',
                        $is_mixed                    => 'PROCESAR VENTA MIXTA',
                        $order_type === 'Produccion' => 'PROCESAR PEDIDO TALLER',
                        default                      => 'FINALIZAR VENTA',
                    };
                @endphp

                <button wire:click="saveAll"
                        @if($isDisabled) disabled @endif
                        class="w-full py-4 rounded-xl font-black text-white shadow-lg transition-all active:scale-95 flex items-center justify-center gap-2
                               {{ $isDisabled ? 'bg-gray-300 cursor-not-allowed' : ($is_mixed ? 'bg-orange-500 hover:bg-orange-600' : 'bg-indigo-600 hover:bg-indigo-700') }}">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                    {{ $btnLabel }}
                </button>
            </div>
        </div>
    </div>

    <x-modal wire:model="showProductModal" title="Registro de Producto o Servicio" separator>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div class="md:col-span-2">
                <x-input label="Nombre" wire:model="new_name" placeholder="Ej: Lona 13oz" icon="o-pencil" />
            </div>
            <x-select label="Categoría" wire:model="new_category_id" :options="$categories" placeholder="Seleccione..." icon="o-tag" />
            <x-select label="Unidad de Medida" wire:model="new_unit_id" :options="$units" placeholder="Seleccione..." icon="o-scale" />
            <x-input label="Precio de Venta" wire:model="new_price" type="number" icon="o-currency-dollar" />
            <x-select label="Tipo" wire:model.live="new_type" :options="[['id'=>'Producto', 'name'=>'Producto'], ['id'=>'Servicio', 'name'=>'Servicio']]" />
            @if($new_type === 'Producto')
                <x-input label="Stock Inicial" wire:model="new_stock" type="number" icon="o-archive-box" />
            @endif
            <div class="flex items-center gap-4 p-3 bg-purple-50 rounded-lg md:col-span-2 border border-purple-100">
                <x-checkbox label="¿Requiere Taller / Producción?" wire:model="new_requires_production" tight />
            </div>
        </div>
        <x-slot:actions>
            <x-button label="Cancelar" @click="$wire.showProductModal = false" />
            <x-button label="Guardar y Añadir" class="btn-primary" wire:click="saveNewProduct" spinner />
        </x-slot:actions>
    </x-modal>
</div>
