<?php

use Livewire\Volt\Component;
use App\Models\{Product, Client, Invoice, InvoiceItem, Order, OrderItem, Production, CashRegister, Category, Unit};
use Illuminate\Support\Facades\DB;

new class extends Component {
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
            $this->cart[$id] = [
                'id'                  => $id,
                'name'                => $product->name,
                'price'               => $product->sale_price,
                'quantity'            => 1,
                'type'                => $product->type,
                'requires_production' => (bool) $product->requires_production,
                'measurements'        => '',
                'material'            => '',
            ];
        }
        $this->checkProductionRequirements();
        $this->calculateTotals();
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
        ]);

        $this->addToCart($product);
        $this->showProductModal = false;
        $this->reset(['new_name', 'new_price', 'new_type', 'new_requires_production', 'new_stock', 'new_category_id', 'new_unit_id']);
        session()->flash('success', '✨ Item registrado y añadido.');
    }

    public function removeItem($id) {
        unset($this->cart[$id]);
        $this->checkProductionRequirements();
        $this->calculateTotals();
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
            session()->flash('error', '⚠️ Carrito vacío');
            return;
        }

        $finalClientId = $this->client_id
            ?: Client::firstOrCreate(['name' => 'Cliente General'], ['phone' => '00000000'])->id;

        if ($this->order_type === 'Rapido' && $this->received_amount < $this->total) {
            session()->flash('error', '❌ Pago insuficiente para factura rápida.');
            return;
        }

        if ($this->is_mixed && $this->received_amount < $this->minimum_payment) {
            session()->flash('error', '❌ Pago insuficiente. Mínimo requerido: C$ ' . number_format($this->minimum_payment, 2));
            return;
        }

        try {
            DB::transaction(function () use ($finalClientId) {
                $actualPayment  = min($this->received_amount, $this->total);
                $pendingBalance = $this->total - $actualPayment;

                $orderStatus   = match ($this->order_type) {
                    'Rapido'     => 'Entregado',
                    'Produccion' => 'Pendiente',
                    'Mixto'      => 'Parcial',
                    default      => 'Pendiente',
                };
                $invoiceStatus = $pendingBalance > 0 ? 'Credito' : 'Pagada';

                $order = Order::create([
                    'client_id'               => $finalClientId,
                    'user_id'                 => auth()->id() ?? 1,
                    'order_date'              => now(),
                    'estimated_delivery_date' => in_array($this->order_type, ['Produccion', 'Mixto']) ? $this->delivery_date : now(),
                    'type'                    => $this->order_type,
                    'status'                  => $orderStatus,
                    'estimated_price'         => $this->total,
                    'advance_payment'         => $actualPayment,
                ]);

                $invoice = Invoice::create([
                    'invoice_number' => 'FAC-' . strtoupper(substr(uniqid(), 7)),
                    'client_id'      => $finalClientId,
                    'order_id'       => $order->id,
                    'user_id'        => auth()->id() ?? 1,
                    'invoice_date'   => now(),
                    'subtotal'       => $this->subtotal,
                    'tax'            => $this->tax,
                    'total'          => $this->total,
                    'status'         => $invoiceStatus,
                ]);

                foreach ($this->cart as $item) {
                    $itemData = [
                        'product_id'   => $item['id'],
                        'description'  => $item['name'],
                        'quantity'     => $item['quantity'],
                        'unit_price'   => $item['price'],
                        'subtotal'     => $item['price'] * $item['quantity'],
                        'measurements' => $item['measurements'] ?? '',
                        'material'     => $item['material'] ?? '',
                    ];

                    OrderItem::create(array_merge(['order_id' => $order->id], $itemData));
                    InvoiceItem::create(array_merge(['invoice_id' => $invoice->id], $itemData));

                    if ($item['type'] === 'Producto' && !$item['requires_production']) {
                        Product::where('id', $item['id'])->decrement('stock', $item['quantity']);
                    }
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

            session()->flash('success', match ($this->order_type) {
                'Mixto'      => '✨ ¡Venta mixta procesada! La parte del taller quedó en producción.',
                'Produccion' => '✨ ¡Pedido de taller registrado!',
                default      => '✨ ¡Venta procesada!',
            });

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
            session()->flash('error', '❌ Error: ' . $e->getMessage());
        }
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
                    $isDisabled = match(true) {
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
