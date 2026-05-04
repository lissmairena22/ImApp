<?php

use Livewire\Volt\Component;
use App\Models\{Invoice, Devolution, Product, CashRegister};
use Illuminate\Support\Facades\DB;

new class extends Component {
    public string $searchInvoice = '';
    public $suggestions = [];
    public $invoice = null;
    public array $items = [];
    public string $reason = '';
    public float $totalToReturn = 0;

    // 1. Sugerencias: Filtramos solo las que están 'Pagada'
    public function updatedSearchInvoice($value)
    {
        $term = trim($value);
        if (strlen($term) < 2) {
            $this->suggestions = [];
            return;
        }

        // Solo mostramos facturas PAGADAS para evitar errores desde la búsqueda
        $this->suggestions = Invoice::where('invoice_number', 'LIKE', "%{$term}%")
            ->where('status', 'Pagada')
            ->limit(5)
            ->get(['id', 'invoice_number', 'total']);
    }

    public function selectInvoice($number)
    {
        $this->searchInvoice = $number;
        $this->suggestions = [];
        $this->findInvoice();
    }

    // 2. Búsqueda con validación estricta de Producción
    public function findInvoice() {
        $this->suggestions = [];
        $term = trim($this->searchInvoice);

        if (empty($term)) {
            session()->flash('error', '⚠️ Ingrese un número de factura.');
            return;
        }

        $this->invoice = null;
        $this->items = [];
        $this->totalToReturn = 0;

        // Buscamos la factura incluyendo la relación 'order' (tabla orders)
        $foundInvoice = Invoice::with(['items.product', 'client', 'order'])
            ->where('invoice_number', $term)
            ->first();

        if (!$foundInvoice) {
            session()->flash('error', '❌ Factura no encontrada.');
            return;
        }

        // VALIDACIÓN 1: Debe estar pagada
        if ($foundInvoice->status !== 'Pagada') {
            session()->flash('error', '❌ No se puede reembolsar: La factura está en estado "' . $foundInvoice->status . '".');
            return;
        }

        // VALIDACIÓN 2: Si tiene orden, no debe estar en producción/pendiente
        // Según tu BD: enum('Pendiente','EnProceso','Terminado','Entregado','Cancelado')
        if ($foundInvoice->order_id) {
            $statusProhibidos = ['Pendiente', 'EnProceso'];
            if (in_array($foundInvoice->order->status, $statusProhibidos)) {
                session()->flash('error', '❌ No se puede reembolsar: El pedido asociado está "' . $foundInvoice->order->status . '". Debe estar terminado.');
                return;
            }
        }

        $this->invoice = $foundInvoice;

        foreach ($this->invoice->items as $item) {
            $this->items[$item->id] = [
                'id' => $item->id,
                'product_id' => $item->product_id,
                'description' => $item->description,
                'max_qty' => (int)$item->quantity,
                'qty_to_return' => 0,
                'unit_price' => (float)$item->unit_price,
                'type' => $item->product->type ?? 'Servicio',
            ];
        }
    }

    public function updatedItems() {
        $this->calculateTotal();
    }

    private function calculateTotal() {
        $this->totalToReturn = collect($this->items)->sum(function($item) {
            $qty = (int)$item['qty_to_return'];
            if($qty > $item['max_qty']) return 0;
            return $qty * $item['unit_price'];
        });
    }

    public function processDevolution() {
        if ($this->totalToReturn <= 0 || empty($this->reason)) {
            session()->flash('error', '⚠️ Indique una cantidad válida y el motivo.');
            return;
        }

        // RE-VALIDACIÓN DE SEGURIDAD ANTES DE PROCESAR
        $this->invoice->refresh();
        if($this->invoice->status !== 'Pagada'){
            session()->flash('error', '❌ La factura ya no es apta para devolución.');
            return;
        }

        try {
            DB::transaction(function () {
                Devolution::create([
                    'invoice_id' => $this->invoice->id,
                    'user_id' => auth()->id() ?? 1,
                    'devolution_date' => now(),
                    'reason' => $this->reason,
                    'amount_returned' => $this->totalToReturn,
                ]);

                foreach ($this->items as $item) {
                    if ($item['qty_to_return'] > 0) {
                        if ($item['type'] === 'Producto' && !empty($item['product_id'])) {
                            Product::where('id', $item['product_id'])->increment('stock', $item['qty_to_return']);
                        }
                    }
                }

                $openRegister = CashRegister::where('user_id', auth()->id() ?? 1)
                    ->where('status', 'Abierta')
                    ->first();

                if ($openRegister) {
                    $openRegister->decrement('system_balance', $this->totalToReturn);
                    $openRegister->increment('cash_out', $this->totalToReturn);

                    // También registrar el movimiento en cash_movements para el historial
                    DB::table('cash_movements')->insert([
                        'cash_register_id' => $openRegister->id,
                        'user_id' => auth()->id() ?? 1,
                        'type' => 'Egreso',
                        'concept' => 'Devolución Factura: ' . $this->invoice->invoice_number,
                        'amount' => $this->totalToReturn,
                        'movement_date' => now(),
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }

                // Si la devolución es total, anulamos la factura
                if ($this->totalToReturn >= (float)$this->invoice->total) {
                    $this->invoice->update(['status' => 'Anulada']);
                }
            });

            session()->flash('success', '✨ Devolución procesada con éxito.');
            $this->reset(['invoice', 'items', 'searchInvoice', 'totalToReturn', 'reason', 'suggestions']);

        } catch (\Exception $e) {
            session()->flash('error', '❌ Error: ' . $e->getMessage());
        }
    }
}; ?>

<div class="p-6 bg-gray-100 min-h-screen font-sans">
    <div class="max-w-6xl mx-auto space-y-6">

        {{-- Alertas --}}
        @if (session()->has('success'))
            <div class="p-4 bg-emerald-500 text-white rounded-xl shadow-lg font-bold flex items-center animate-bounce">
                <svg class="w-6 h-6 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                {{ session('success') }}
            </div>
        @endif

        @if (session()->has('error'))
            <div class="p-4 bg-rose-500 text-white rounded-xl shadow-lg font-bold flex items-center">
                <svg class="w-6 h-6 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                {{ session('error') }}
            </div>
        @endif

        {{-- Buscador Principal --}}
        <div class="bg-white p-8 rounded-2xl shadow-sm border border-gray-200">
            <div class="mb-6">
                <h2 class="text-2xl font-black text-gray-800 uppercase tracking-tight">Módulo de Devoluciones</h2>
                <p class="text-gray-500 text-sm">Busca una factura para realizar una devolución parcial o total.</p>
            </div>

            <div class="relative">
                <div class="flex gap-3 p-2 bg-gray-50 rounded-2xl border border-gray-200 focus-within:border-indigo-500 transition-all">
                    <div class="flex items-center pl-4">
                        <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                    </div>
                    <input type="text"
                           wire:model.live.debounce.300ms="searchInvoice"
                           wire:keydown.enter="findInvoice"
                           class="flex-1 bg-transparent border-none focus:ring-0 text-lg font-medium"
                           placeholder="Escriba el número de factura (Ej: FAC-102)...">
                    <button wire:click="findInvoice"
                            class="bg-indigo-600 text-white px-8 py-3 rounded-xl font-bold hover:bg-indigo-700 transition transform active:scale-95 shadow-md">
                        BUSCAR
                    </button>
                </div>

                {{-- Dropdown de Sugerencias --}}
                @if(!empty($suggestions))
                    <div class="absolute z-50 w-full mt-2 bg-white border border-gray-200 rounded-xl shadow-2xl overflow-hidden border-t-4 border-t-indigo-500">
                        @foreach($suggestions as $suggestion)
                            <button wire:click="selectInvoice('{{ $suggestion->invoice_number }}')"
                                    class="w-full text-left px-6 py-4 hover:bg-indigo-50 transition flex justify-between items-center border-b last:border-none">
                                <div>
                                    <span class="font-black text-gray-800">{{ $suggestion->invoice_number }}</span>
                                    <p class="text-[10px] text-gray-400 uppercase font-bold">Clic para seleccionar</p>
                                </div>
                                <span class="text-sm font-black text-indigo-600">C$ {{ number_format($suggestion->total, 2) }}</span>
                            </button>
                        @endforeach
                    </div>
                @endif
            </div>
        </div>

        {{-- Resultados de Factura --}}
        @if($invoice)
            <div class="grid grid-cols-1 lg:grid-cols-12 gap-8 animate-in fade-in slide-in-from-bottom-4 duration-500">

                {{-- Tabla de Items --}}
                <div class="lg:col-span-8 bg-white rounded-2xl shadow-sm border border-gray-200 overflow-hidden">
                    <div class="p-6 border-b bg-gray-50/50 flex justify-between items-center">
                        <div>
                            <p class="text-xs font-bold text-gray-400 uppercase">Factura seleccionada</p>
                            <h3 class="text-lg font-black text-indigo-900">{{ $invoice->invoice_number }}</h3>
                        </div>
                        <div class="text-right">
                            <p class="text-xs font-bold text-gray-400 uppercase">Cliente</p>
                            <p class="font-bold text-gray-700">{{ $invoice->client->name ?? 'Consumidor Final' }}</p>
                        </div>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="w-full text-left">
                            <thead class="text-[10px] uppercase text-gray-400 font-black border-b bg-white">
                                <tr>
                                    <th class="px-6 py-4">Descripción</th>
                                    <th class="px-6 py-4 text-center">Cant. Original</th>
                                    <th class="px-6 py-4 text-center">A Devolver</th>
                                    <th class="px-6 py-4 text-right">Subtotal</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                @foreach($items as $id => $item)
                                    <tr class="hover:bg-gray-50/50 transition">
                                        <td class="px-6 py-5">
                                            <p class="font-bold text-gray-800">{{ $item['description'] }}</p>
                                            <span class="inline-block px-2 py-0.5 rounded text-[10px] font-bold {{ $item['type'] == 'Producto' ? 'bg-emerald-100 text-emerald-700' : 'bg-amber-100 text-amber-700' }}">
                                                {{ strtoupper($item['type']) }}
                                            </span>
                                        </td>
                                        <td class="px-6 py-5 text-center font-medium text-gray-500">{{ $item['max_qty'] }}</td>
                                        <td class="px-6 py-5 text-center">
                                            <input type="number"
                                                   wire:model.live="items.{{ $id }}.qty_to_return"
                                                   max="{{ $item['max_qty'] }}" min="0"
                                                   class="w-20 border-gray-200 rounded-lg text-center font-bold text-indigo-600 focus:ring-indigo-500">
                                        </td>
                                        <td class="px-6 py-5 text-right font-black text-gray-700">
                                            C$ {{ number_format($item['qty_to_return'] * $item['unit_price'], 2) }}
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>

                {{-- Resumen y Confirmación --}}
                <div class="lg:col-span-4 space-y-6">
                    <div class="bg-white p-6 rounded-2xl shadow-sm border border-gray-200 sticky top-6">
                        <h3 class="font-black text-gray-800 uppercase text-sm mb-4 border-b pb-2">Finalizar Devolución</h3>

                        <div class="space-y-4">
                            <div>
                                <label class="block text-[10px] font-bold text-gray-400 uppercase mb-1">Motivo</label>
                                <textarea wire:model="reason" rows="3"
                                          class="w-full border-gray-200 rounded-xl text-sm focus:ring-indigo-500 focus:border-indigo-500"
                                          placeholder="Ej: Producto dañado..."></textarea>
                            </div>

                            <div class="p-4 bg-rose-50 rounded-xl border border-rose-100 text-center">
                                <span class="block text-xs font-bold text-rose-700 uppercase mb-1">Monto Total a Reembolsar</span>
                                <span class="text-3xl font-black text-rose-600 tracking-tighter">C$ {{ number_format($totalToReturn, 2) }}</span>
                            </div>

                            <button wire:click="processDevolution"
                                    wire:loading.attr="disabled"
                                    class="w-full bg-gray-900 hover:bg-black text-white font-black py-4 rounded-xl shadow-lg transition transform active:scale-95 disabled:opacity-50">
                                <span wire:loading.remove italic>CONFIRMAR PROCESO</span>
                                <span wire:loading class="animate-pulse">CARGANDO...</span>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        @endif
    </div>
</div>
