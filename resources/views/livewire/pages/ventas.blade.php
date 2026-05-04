<?php

use Livewire\Volt\Component;
use App\Models\{Product, Client, Invoice, InvoiceItem, Order, OrderItem, Production, CashRegister};
use Illuminate\Support\Facades\DB;

new class extends Component {
    public $search = '';
    public $cart = [];
    public $client_id = '';
    public $selectedClient = null;

    // Totales
    public $subtotal = 0;
    public $tax = 0;
    public $total = 0;
    public $discount = 0;

    // Dinero
    public $received_amount = 0;
    public $change_amount = 0;

    // Lógica de Imprenta
    public $order_type = 'Rapido';
    public $delivery_date;
    public $hasProductionItems = false;

    public function mount() {
        $this->delivery_date = now()->addDays(2)->format('Y-m-d');
    }

    public function with() {
        return [
            'products' => Product::where('name', 'like', "%{$this->search}%")
                                ->where('is_active', true)
                                ->limit(8)->get(),
            'clients' => Client::where('is_active', true)->get(),
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
            $this->cart[$id] = [
                'id' => $id,
                'name' => $product->name,
                'price' => $product->sale_price,
                'quantity' => 1,
                'type' => $product->type,
                'requires_production' => (bool)$product->requires_production,
                'measurements' => '',
                'material' => '',
            ];
        }
        $this->checkProductionRequirements();
        $this->calculateTotals();
    }

    public function removeItem($id) {
        unset($this->cart[$id]);
        $this->checkProductionRequirements();
        $this->calculateTotals();
    }

    public function checkProductionRequirements() {
        $this->hasProductionItems = collect($this->cart)->contains('requires_production', true);
        if ($this->hasProductionItems) $this->order_type = 'Produccion';
    }

    public function calculateTotals() {
        $this->subtotal = array_sum(array_map(fn($item) => $item['price'] * $item['quantity'], $this->cart));
        $this->tax = $this->subtotal * 0.15;
        $this->total = ($this->subtotal + $this->tax) - $this->discount;

        if ($this->order_type === 'Rapido' && $this->received_amount == 0) {
            $this->received_amount = $this->total;
        }
        $this->calculateChange();
    }

    public function calculateChange() {
        if ($this->order_type === 'Rapido') {
            $this->change_amount = ($this->received_amount > $this->total) ? ($this->received_amount - $this->total) : 0;
        } else {
            $this->change_amount = 0;
        }
    }

    public function updatedReceivedAmount() { $this->calculateChange(); }
    public function updatedOrderType() { $this->calculateTotals(); }

    public function saveAll() {
        if (empty($this->cart)) {
            session()->flash('error', '⚠️ Carrito vacío');
            return;
        }

        $finalClientId = $this->client_id;
        if (empty($finalClientId)) {
            $anonymous = Client::firstOrCreate(['name' => 'Cliente General'], ['phone' => '00000000']);
            $finalClientId = $anonymous->id;
        }

        if ($this->order_type === 'Rapido' && $this->received_amount < $this->total) {
            session()->flash('error', '❌ Pago insuficiente');
            return;
        }

        try {
            DB::transaction(function () use ($finalClientId) {
                $actualPayment = ($this->order_type === 'Rapido') ? $this->total : min($this->received_amount, $this->total);

                $order = Order::create([
                    'client_id' => $finalClientId,
                    'user_id' => auth()->id() ?? 1,
                    'order_date' => now(),
                    'estimated_delivery_date' => ($this->order_type == 'Produccion') ? $this->delivery_date : now(),
                    'type' => $this->order_type,
                    'status' => ($this->order_type == 'Produccion') ? 'Pendiente' : 'Entregado',
                    'estimated_price' => $this->total,
                    'advance_payment' => $actualPayment,
                ]);

                $invoice = Invoice::create([
                    'invoice_number' => 'FAC-' . strtoupper(substr(uniqid(), 7)),
                    'client_id' => $finalClientId,
                    'order_id' => $order->id,
                    'user_id' => auth()->id() ?? 1,
                    'invoice_date' => now(),
                    'subtotal' => $this->subtotal,
                    'tax' => $this->tax,
                    'total' => $this->total,
                    'status' => ($actualPayment < $this->total) ? 'Credito' : 'Pagada',
                ]);

                foreach ($this->cart as $item) {
                    OrderItem::create([
                        'order_id' => $order->id,
                        'product_id' => $item['id'],
                        'description' => $item['name'],
                        'quantity' => $item['quantity'],
                        'unit_price' => $item['price'],
                        'subtotal' => $item['price'] * $item['quantity'],
                        'measurements' => $item['measurements'] ?? '',
                        'material' => $item['material'] ?? '',
                    ]);

                    InvoiceItem::create([
                        'invoice_id' => $invoice->id,
                        'product_id' => $item['id'],
                        'description' => $item['name'],
                        'quantity' => $item['quantity'],
                        'unit_price' => $item['price'],
                        'subtotal' => $item['price'] * $item['quantity'],
                    ]);

                    if ($item['type'] === 'Producto') {
                        Product::where('id', $item['id'])->decrement('stock', $item['quantity']);
                    }
                }

                if ($this->order_type === 'Produccion') {
                    Production::create(['order_id' => $order->id, 'user_id' => auth()->id() ?? 1, 'status' => 'Pendiente']);
                }

                if ($actualPayment > 0) {
                    $register = CashRegister::where('user_id', auth()->id() ?? 1)->where('status', 'Abierta')->first();
                    if ($register) {
                        $register->increment('cash_sales', $actualPayment);
                        $register->increment('system_balance', $actualPayment);
                    }
                }
            });

            session()->flash('success', '✨ ¡Venta Exitosa!');
            $this->reset(['cart', 'client_id', 'selectedClient', 'received_amount', 'change_amount']);
            $this->calculateTotals();

        } catch (\Exception $e) {
            session()->flash('error', '❌ Error: ' . $e->getMessage());
        }
    }
}; ?>

<div class="p-4 bg-gray-100 min-h-screen">
    <div class="max-w-[1400px] mx-auto grid grid-cols-1 lg:grid-cols-12 gap-4">

        <!-- COLUMNA IZQUIERDA -->
        <div class="lg:col-span-8 space-y-4">

            <!-- Barra de Búsqueda Compacta con Icono -->
            <div class="bg-white p-3 rounded-xl shadow-sm flex items-center gap-3">
                <div class="bg-indigo-50 p-2 rounded-lg">
                    <svg class="w-5 h-5 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
                </div>
                <input wire:model.live="search" type="text"
                       class="w-1/2 border-none focus:ring-0 text-sm"
                       placeholder="Buscar productos o servicios...">
            </div>

            <!-- Catálogo de Productos (Cuadrícula Estilizada) -->
            <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
                @foreach($products as $product)
                    <button wire:click="addToCart({{ $product->id }})"
                            class="bg-white p-4 rounded-xl border border-transparent hover:border-indigo-500 hover:shadow-md transition text-left group relative overflow-hidden">
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
                        <!-- Efecto visual al hover -->
                        <div class="absolute bottom-0 right-0 p-1 opacity-0 group-hover:opacity-100 transition">
                            <svg class="w-4 h-4 text-indigo-400" fill="currentColor" viewBox="0 0 20 20"><path d="M10 3a1 1 0 011 1v5h5a1 1 0 110 2h-5v5a1 1 0 11-2 0v-5H4a1 1 0 110-2h5V4a1 1 0 011-1z"></path></svg>
                        </div>
                    </button>
                @endforeach
            </div>

            <!-- Detalle de Venta (Tabla con Color) -->
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="bg-indigo-600 text-white uppercase text-[10px] tracking-wider">
                            <th class="px-4 py-3 text-left font-bold">Descripción del Item</th>
                            <th class="px-4 py-3 text-center font-bold">Cant.</th>
                            <th class="px-4 py-3 text-right font-bold">Precio</th>
                            <th class="px-4 py-3 text-right font-bold">Subtotal</th>
                            <th class="px-4 py-3"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse($cart as $index => $item)
                            <tr class="hover:bg-indigo-50/30">
                                <td class="px-4 py-3">
                                    <span class="font-bold text-gray-800">{{ $item['name'] }}</span>
                                    @if($item['requires_production'])
                                        <div class="flex gap-2 mt-2">
                                            <div class="flex items-center gap-1 bg-gray-50 px-2 py-1 rounded border border-gray-200">
                                                <span class="text-[9px] text-gray-400 uppercase">Medidas:</span>
                                                <input type="text" wire:model="cart.{{$index}}.measurements" class="text-[10px] border-none bg-transparent p-0 w-20 focus:ring-0">
                                            </div>
                                            <div class="flex items-center gap-1 bg-gray-50 px-2 py-1 rounded border border-gray-200">
                                                <span class="text-[9px] text-gray-400 uppercase">Mat:</span>
                                                <input type="text" wire:model="cart.{{$index}}.material" class="text-[10px] border-none bg-transparent p-0 w-20 focus:ring-0">
                                            </div>
                                        </div>
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-center">
                                    <input type="number" wire:model.live="cart.{{$index}}.quantity" wire:change="calculateTotals"
                                           class="w-12 border-gray-200 rounded p-1 text-center font-bold text-indigo-600">
                                </td>
                                <td class="px-4 py-3 text-right text-gray-500 italic">C$ {{ number_format($item['price'], 2) }}</td>
                                <td class="px-4 py-3 text-right font-black text-gray-700">C$ {{ number_format($item['price'] * $item['quantity'], 2) }}</td>
                                <td class="px-4 py-3 text-center">
                                    <button wire:click="removeItem('{{ $index }}')" class="text-red-300 hover:text-red-600">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M6 18L18 6M6 6l12 12"></path></svg>
                                    </button>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="px-4 py-10 text-center text-gray-400">No hay productos en el carrito</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <!-- COLUMNA DERECHA -->
        <div class="lg:col-span-4">
            <div class="bg-white rounded-xl shadow-lg border border-gray-200 p-5 space-y-5 sticky top-4">

                <!-- Cliente Seleccionado -->
                <div class="bg-gray-50 p-4 rounded-xl border border-gray-100">
                    <label class="text-[10px] font-bold text-gray-400 uppercase block mb-2">Cliente</label>
                    <select wire:model.live="client_id" class="w-full border-gray-200 rounded-lg text-sm mb-3">
                        <option value="">-- Cliente General --</option>
                        @foreach($clients as $client)
                            <option value="{{ $client->id }}">{{ $client->name }}</option>
                        @endforeach
                    </select>
                    @if($selectedClient)
                        <div class="flex items-center gap-3 bg-white p-2 rounded-lg border border-indigo-50 shadow-sm">
                            <div class="bg-indigo-600 text-white p-2 rounded-lg">
                                <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20"><path d="M10 9a3 3 0 100-6 3 3 0 000 6zm-7 9a7 7 0 1114 0H3z"></path></svg>
                            </div>
                            <div class="text-[10px]">
                                <p class="font-bold text-indigo-900">{{ $selectedClient->tax_id ?? 'Público' }}</p>
                                <p class="text-gray-500">{{ $selectedClient->phone ?? '---' }}</p>
                            </div>
                        </div>
                    @endif
                </div>

                <!-- Tipo de Venta y Fecha -->
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="text-[10px] font-bold text-gray-400 uppercase mb-1 block">Modo</label>
                        <select wire:model.live="order_type" @disabled($hasProductionItems)
                                class="w-full border-gray-200 rounded-lg text-xs font-bold {{ $hasProductionItems ? 'bg-purple-50 text-purple-700' : 'bg-white' }}">
                            <option value="Rapido">Factura Rápida</option>
                            <option value="Produccion">Orden Taller</option>
                        </select>
                    </div>
                    @if($order_type == 'Produccion')
                        <div>
                            <label class="text-[10px] font-bold text-gray-400 uppercase mb-1 block">Entrega</label>
                            <input type="date" wire:model="delivery_date" class="w-full border-gray-200 rounded-lg text-xs">
                        </div>
                    @endif
                </div>

                <!-- Resumen Financiero -->
                <div class="bg-indigo-900 rounded-xl p-5 text-white shadow-md relative overflow-hidden">
                    <div class="relative z-10 space-y-1">
                        <div class="flex justify-between text-[10px] opacity-70 uppercase font-bold tracking-widest">
                            <span>Subtotal</span>
                            <span>C$ {{ number_format($subtotal, 2) }}</span>
                        </div>
                        <div class="flex justify-between text-[10px] opacity-70 uppercase font-bold tracking-widest">
                            <span>Impuesto (15%)</span>
                            <span>C$ {{ number_format($tax, 2) }}</span>
                        </div>
                        <div class="flex justify-between items-center pt-2 mt-2 border-t border-white/20 font-black italic">
                            <span class="text-lg">TOTAL</span>
                            <span class="text-3xl">C$ {{ number_format($total, 2) }}</span>
                        </div>
                    </div>
                    <!-- Decoración fondo -->
                    <div class="absolute -right-4 -bottom-4 bg-white/5 w-24 h-24 rounded-full"></div>
                </div>

                <!-- Caja de Pago Dinámica -->
                <div class="bg-white border-2 border-dashed border-gray-200 rounded-xl p-4 space-y-4">
                    <div>
                        <label class="text-[10px] font-black text-indigo-600 uppercase mb-2 block">
                            {{ $order_type === 'Rapido' ? '💵 Paga con:' : '📥 Abono Inicial:' }}
                        </label>
                        <div class="relative">
                            <span class="absolute left-3 top-3 text-gray-400 font-bold">C$</span>
                            <input type="number" wire:model.live="received_amount"
                                   class="w-full border-gray-200 rounded-lg pl-10 text-2xl font-black text-gray-800 focus:ring-indigo-500">
                        </div>
                    </div>

                    @if($order_type === 'Rapido')
                        <div class="flex justify-between items-center bg-green-50 p-3 rounded-lg border border-green-100">
                            <span class="text-[10px] font-bold text-green-600 uppercase">Cambio:</span>
                            <span class="text-xl font-black text-green-700">C$ {{ number_format($change_amount, 2) }}</span>
                        </div>
                    @endif
                </div>

                <!-- Botón de Acción -->
                <button wire:click="saveAll"
                        @if($order_type === 'Rapido' && $received_amount < $total) disabled @endif
                        class="w-full py-4 rounded-xl font-black text-white shadow-lg transition-all active:scale-95 flex items-center justify-center gap-2
                        {{ ($order_type === 'Rapido' && $received_amount < $total) ? 'bg-gray-300' : 'bg-indigo-600 hover:bg-indigo-700 hover:shadow-indigo-200 shadow-md' }}">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                    {{ $order_type == 'Produccion' ? 'PROCESAR PEDIDO TALLER' : 'FINALIZAR VENTA' }}
                </button>

                @if(session()->has('success'))
                    <div class="p-3 bg-green-100 text-green-700 text-xs font-bold rounded-lg text-center border border-green-200 animate-bounce">
                        {{ session('success') }}
                    </div>
                @endif
                @if(session()->has('error'))
                    <div class="p-3 bg-red-100 text-red-700 text-xs font-bold rounded-lg text-center border border-red-200">
                        {{ session('error') }}
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>
