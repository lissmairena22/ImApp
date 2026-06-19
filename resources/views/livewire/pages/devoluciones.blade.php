<?php

use Livewire\Volt\Component;
use App\Models\{Invoice, Devolution, DevolutionItem, CashRegister, InventoryOutput, InventoryOutputItem};
use Illuminate\Support\Facades\DB;
use Mary\Traits\Toast;

new class extends Component {
    use Toast;

    public string $searchInvoice = '';
    public $suggestions = [];
    public $invoice = null;
    public array $items = [];
    public string $reason = '';
    public float $totalToReturn = 0;
    public float $invoiceFactor = 1;
    public bool $canRestoreServiceMaterials = false;

    public function updatedSearchInvoice($value): void
    {
        $term = trim($value);
        $this->reset(['invoice', 'items', 'totalToReturn']);
        $this->invoiceFactor = 1;
        $this->canRestoreServiceMaterials = false;

        if (strlen($term) < 2) {
            $this->suggestions = [];
            return;
        }

        $this->suggestions = $this->eligibleInvoiceQuery()
            ->where('invoice_number', 'LIKE', "%{$term}%")
            ->limit(5)
            ->get(['id', 'invoice_number', 'total']);
    }

    public function selectInvoice($number): void
    {
        $this->searchInvoice = $number;
        $this->suggestions = [];
        $this->findInvoice();
    }

    public function findInvoice(): void
    {
        $this->suggestions = [];
        $term = trim($this->searchInvoice);

        if (empty($term)) {
            $this->error('Ingrese un número de factura.', position: 'toast-top toast-center');
            return;
        }

        $this->reset(['invoice', 'items', 'totalToReturn']);
        $this->invoiceFactor = 1;
        $this->canRestoreServiceMaterials = false;

        $foundInvoice = $this->eligibleInvoiceQuery()
            ->with([
                'items.product.unit',
                'items.materialConsumptions.material',
                'items.devolutionItems',
                'client',
                'order',
            ])
            ->where('invoice_number', $term)
            ->first();

        if (!$foundInvoice) {
            $this->error('Factura no encontrada o no apta para devolucion de productos.', position: 'toast-top toast-center');
            return;
        }

        if ($foundInvoice->status !== 'Pagada') {
            $this->error('No se puede reembolsar: la factura está en estado "' . $foundInvoice->status . '".', position: 'toast-top toast-center');
            return;
        }

        if ($foundInvoice->order_id && in_array($foundInvoice->order?->status, ['EnProceso', 'Cancelado'], true)) {
            $this->error('No se puede reembolsar desde aqui: el pedido esta en proceso o cancelado.', position: 'toast-top toast-center');
            return;
        }

        if ($foundInvoice->items->contains(fn($item) => !$this->invoiceItemCanBeReturned($item, $foundInvoice))) {
            $this->error('Esta factura tiene conceptos que no son aptos para devolucion.', position: 'toast-top toast-center');
            return;
        }

        $this->invoice = $foundInvoice;
        $this->invoiceFactor = $this->invoiceFactorFor($foundInvoice);
        $this->canRestoreServiceMaterials = false;

        foreach ($this->invoice->items as $item) {
            if (!$this->invoiceItemCanBeReturned($item, $this->invoice)) {
                continue;
            }

            $type = $item->product->type ?? 'Producto';

            $returnedQty = (float) $item->devolutionItems->sum('quantity');
            $remainingQty = max(0, (float) $item->quantity - $returnedQty);

            if ($remainingQty <= 0) {
                continue;
            }


            // Evaluamos si el producto permite devoluciones (asume true si la columna no existe aún)
            $isReturnable = isset($item->product->is_returnable) ? (bool) $item->product->is_returnable : true;

            $this->items[$item->id] = [
                'id' => $item->id,
                'product_id' => $item->product_id,
                'description' => $item->description,
                'max_qty' => $remainingQty,
                'original_qty' => (float) $item->quantity,
                'already_returned' => $returnedQty,
                'qty_to_return' => 0,
                'unit_price' => (float) $item->unit_price,
                'gross_unit_price' => round((float) $item->unit_price * $this->invoiceFactor, 2),
                'type' => $type,
                'unit' => $item->product->unit->name ?? 'Und',
                'return_to_stock' => false,
                'restore_materials' => false,
                'is_returnable' => $isReturnable,
                'materials' => [],
            ];
        }

        $this->normalizeItems();

        if (empty($this->items)) {
            $this->invoice = null;
            $this->error('Esta factura ya no tiene unidades disponibles para devolver.', position: 'toast-top toast-center');
        }
    }

    public function updatedItems(): void
    {
        $this->normalizeItems();
        $this->calculateTotal();
    }

    private function calculateTotal(): void
    {
        $this->totalToReturn = round(collect($this->items)->sum(function ($item) {
            if (!is_array($item)) {
                return 0;
            }

            $item = $this->normalizeItem($item);

            if (!$item['is_returnable']) {
                return 0;
            }

            $qty = (float) $item['qty_to_return'];

            if ($qty < 0 || $qty > (float) $item['max_qty']) {
                return 0;
            }

            return $qty * (float) $item['gross_unit_price'];
        }), 2);
    }

    public function processDevolution(): void
    {
        if (!$this->invoice) {
            $this->error('Seleccione una factura apta para devolucion.', position: 'toast-top toast-center');
            return;
        }

        $this->normalizeItems();
        $this->calculateTotal();

        if ($this->totalToReturn <= 0 || trim($this->reason) === '') {
            $this->error('Indique una cantidad válida a devolver y el motivo.', position: 'toast-top toast-center');
            return;
        }

        if (!$this->quantitiesAreValid()) {
            return;
        }

        $this->invoice->refresh();
        $this->invoice->load(['order', 'items.product', 'items.devolutionItems']);

        if ($this->invoice->status !== 'Pagada') {
            $this->error('La factura ya no es apta para devolución.', position: 'toast-top toast-center');
            return;
        }

        if ($this->invoice->order_id && in_array($this->invoice->order?->status, ['EnProceso', 'Cancelado'], true)) {
            $this->error('La factura ya no es apta para devolucion por el estado del pedido.', position: 'toast-top toast-center');
            return;
        }

        if ($this->invoice->items->contains(fn($item) => !$this->invoiceItemCanBeReturned($item, $this->invoice))) {
            $this->error('La factura tiene conceptos que no son aptos para devolucion.', position: 'toast-top toast-center');
            return;
        }

        try {
            DB::transaction(function () {
                $isFullReturn = $this->isFullReturn();
                $returnedItems = [];

                $devolution = Devolution::create([
                    'invoice_id' => $this->invoice->id,
                    'user_id' => auth()->id() ?? 1,
                    'devolution_date' => now(),
                    'reason' => $this->reason,
                    'amount_returned' => $this->totalToReturn,
                ]);

                foreach ($this->items as $item) {
                    if (!is_array($item)) {
                        continue;
                    }

                    $item = $this->normalizeItem($item);

                    if (!$item['is_returnable']) {
                        continue;
                    }

                    $qty = (float) $item['qty_to_return'];

                    if ($qty <= 0) {
                        continue;
                    }

                    $returnedItems[] = array_merge($item, ['qty_to_return' => $qty]);
                    $returnedToStock = false;
                    $materialsRestored = false;

                    DevolutionItem::create([
                        'devolution_id' => $devolution->id,
                        'invoice_item_id' => $item['id'],
                        'product_id' => $item['product_id'],
                        'description' => $item['description'],
                        'quantity' => $qty,
                        'unit_price' => $item['unit_price'],
                        'amount_returned' => round($qty * (float) $item['gross_unit_price'], 2),
                        'returned_to_stock' => $returnedToStock,
                        'materials_restored' => $materialsRestored,
                    ]);
                }

                $this->registerInventoryOutput($returnedItems, $isFullReturn);
                $this->registerCashOut();

                if ($isFullReturn) {
                    $this->invoice->update(['status' => 'Anulada']);
                    $this->invoice->order?->update(['status' => 'Cancelado']);
                }
            });

            $this->success('Devolución procesada con éxito.', position: 'toast-top toast-center');
            $this->reset(['invoice', 'items', 'searchInvoice', 'totalToReturn', 'reason', 'suggestions']);
            $this->invoiceFactor = 1;
            $this->canRestoreServiceMaterials = false;
        } catch (\Exception $e) {
            $this->error('Error: ' . $e->getMessage(), position: 'toast-top toast-center');
        }
    }

    private function eligibleInvoiceQuery()
    {
        return Invoice::query()
            ->where('status', 'Pagada')
            ->whereHas('items.product', function ($query) {
                $query->whereIn('type', ['Producto', 'Servicio']);
            })
            ->whereDoesntHave('items', fn($query) => $query->whereDoesntHave('product'))
            ->whereDoesntHave('items.product', function ($query) {
                $query->whereNull('type')
                    ->orWhereNotIn('type', ['Producto', 'Servicio']);
            })
            ->where(function ($query) {
                $query->whereDoesntHave('items.product', fn($itemQuery) => $itemQuery->where('type', 'Servicio'))
                    ->orWhereHas('order', fn($orderQuery) => $orderQuery->where('type', 'Produccion'));
            })
            ->where(function ($query) {
                $query->whereDoesntHave('order')
                    ->orWhereHas('order', fn($orderQuery) => $orderQuery->whereNotIn('status', ['EnProceso', 'Cancelado']));
            });
    }

    private function invoiceItemCanBeReturned($item, Invoice $invoice): bool
    {
        $product = $item->product;

        if (!$product) {
            return false;
        }

        if ($product->type === 'Producto') {
            return true;
        }

        return $product->type === 'Servicio'
            && $invoice->order?->type === 'Produccion'
            && !in_array($invoice->order?->status, ['EnProceso', 'Cancelado'], true);
    }

    private function invoiceFactorFor(Invoice $invoice): float
    {
        $subtotal = max((float) $invoice->subtotal, 0.01);
        return round((float) $invoice->total / $subtotal, 6);
    }

    private function quantitiesAreValid(): bool
    {
        foreach ($this->items as $item) {
            if (!is_array($item)) {
                continue;
            }

            $item = $this->normalizeItem($item);

            if (!$item['is_returnable']) {
                continue;
            }

            $qty = (float) $item['qty_to_return'];

            if ($qty < 0 || $qty > (float) $item['max_qty']) {
                $this->error("Cantidad inválida para {$item['description']}. Máximo: {$item['max_qty']}.", position: 'toast-top toast-center');
                return false;
            }
        }

        return true;
    }

    private function registerInventoryOutput(array $returnedItems, bool $isFullReturn): void
    {
        if (empty($returnedItems)) {
            return;
        }

        $orderId = null;

        if ($isFullReturn && $this->invoice->order_id && !InventoryOutput::where('order_id', $this->invoice->order_id)->exists()) {
            $orderId = $this->invoice->order_id;
        }

        $output = InventoryOutput::create([
            'order_id' => $orderId,
            'user_id' => auth()->id() ?? 1,
            'output_date' => now(),
            'reason' => 'Devolucion sobre venta',
            'notes' => 'Factura: ' . $this->invoice->invoice_number . '. Motivo: ' . $this->reason,
        ]);

        foreach ($returnedItems as $item) {
            InventoryOutputItem::create([
                'inventory_output_id' => $output->id,
                'product_id' => $item['product_id'],
                'description' => $item['description'],
                'source_type' => $item['type'] === 'Servicio' ? 'Servicio devuelto' : 'Producto devuelto',
                'quantity' => (float) $item['qty_to_return'],
                'unit_name' => $item['unit'],
                'material_lost' => 0,
                'affects_stock' => false,
            ]);
        }
    }

    private function registerCashOut(): void
    {
        $openRegister = CashRegister::where('user_id', auth()->id() ?? 1)
            ->where('status', 'Abierta')
            ->first();

        if (!$openRegister) {
            throw new \Exception('No hay caja abierta para registrar la salida del reembolso.');
        }

        if ((float) $openRegister->system_balance < $this->totalToReturn) {
            throw new \Exception('La caja no tiene saldo suficiente para este reembolso.');
        }

        $openRegister->decrement('system_balance', $this->totalToReturn);
        $openRegister->increment('cash_out', $this->totalToReturn);

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

    private function isFullReturn(): bool
    {
        $remainingAfterReturn = collect($this->items)->sum(function ($item) {
            if (!is_array($item)) {
                return 0;
            }

            $item = $this->normalizeItem($item);

            // Si el item no es retornable, asumimos que siempre "quedará" en la factura
            if (!$item['is_returnable']) {
                return (float) $item['max_qty'];
            }
            return max(0, (float) $item['max_qty'] - (float) $item['qty_to_return']);
        });

        return $remainingAfterReturn <= 0;
    }

    private function normalizeItems(): void
    {
        foreach ($this->items as $key => $item) {
            if (!is_array($item)) {
                unset($this->items[$key]);
                continue;
            }

            $this->items[$key] = $this->normalizeItem($item);
        }
    }

    public function normalizeItem(array $item): array
    {
        return array_merge([
            'id' => null,
            'product_id' => null,
            'description' => 'Item',
            'max_qty' => 0,
            'original_qty' => 0,
            'already_returned' => 0,
            'qty_to_return' => 0,
            'unit_price' => 0,
            'gross_unit_price' => 0,
            'type' => 'Producto',
            'unit' => 'Und',
            'return_to_stock' => false,
            'restore_materials' => false,
            'is_returnable' => true,
            'materials' => [],
        ], $item);
    }
}; ?>

<div class="p-6 bg-gray-100 min-h-screen font-sans">
    <div class="max-w-6xl mx-auto space-y-6">
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

        @if($invoice)
            <div class="grid grid-cols-1 lg:grid-cols-12 gap-8 animate-in fade-in slide-in-from-bottom-4 duration-500">
                <div class="lg:col-span-8 bg-white rounded-2xl shadow-sm border border-gray-200 overflow-hidden">
                    <div class="p-6 border-b bg-gray-50/50 flex justify-between items-center">
                        <div>
                            <p class="text-xs font-bold text-gray-400 uppercase">Factura seleccionada</p>
                            <h3 class="text-lg font-black text-indigo-900">{{ $invoice->invoice_number }}</h3>
                            @if($invoice->order)
                                <p class="text-[10px] text-gray-400 uppercase font-bold mt-1">Pedido: {{ $invoice->order->status }}</p>
                            @endif
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
                                    <th class="px-6 py-4 text-center">Disponible</th>
                                    <th class="px-6 py-4 text-center">A devolver</th>
                                    <th class="px-6 py-4 text-right">Reembolso</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                @foreach($items as $id => $item)
                                    @php($item = $this->normalizeItem(is_array($item) ? $item : []))
                                    <tr class="hover:bg-gray-50/50 transition {{ !$item['is_returnable'] ? 'opacity-70 bg-gray-50' : '' }}">
                                        <td class="px-6 py-5">
                                            <p class="font-bold text-gray-800">{{ $item['description'] }}</p>
                                            <div class="flex flex-wrap gap-1 mt-1">
                                                <span class="inline-block px-2 py-0.5 rounded text-[10px] font-bold {{ $item['type'] == 'Producto' ? 'bg-emerald-100 text-emerald-700' : 'bg-amber-100 text-amber-700' }}">
                                                    {{ strtoupper($item['type']) }}
                                                </span>
                                                @if(!$item['is_returnable'])
                                                    <span class="inline-block px-2 py-0.5 rounded text-[10px] font-bold bg-red-100 text-red-700 uppercase">
                                                        No Retornable
                                                    </span>
                                                @endif
                                                @if($item['already_returned'] > 0)
                                                    <span class="inline-block px-2 py-0.5 rounded text-[10px] font-bold bg-slate-100 text-slate-600">
                                                        Devuelto: {{ number_format($item['already_returned'], 2) }}
                                                    </span>
                                                @endif
                                            </div>

                                            @if(!$item['is_returnable'])
                                                <p class="text-[10px] text-gray-400 mt-2 italic">Este concepto no admite reembolsos.</p>
                                            @endif
                                        </td>
                                        <td class="px-6 py-5 text-center font-medium text-gray-500">
                                            {{ number_format($item['max_qty'], 2) }} {{ $item['unit'] }}
                                        </td>
                                        <td class="px-6 py-5 text-center">
                                            @if($item['is_returnable'])
                                                <input type="number"
                                                       wire:model.live="items.{{ $id }}.qty_to_return"
                                                       max="{{ $item['max_qty'] }}" min="0" step="0.01"
                                                       class="w-24 border-gray-200 rounded-lg text-center font-bold text-indigo-600 focus:ring-indigo-500">
                                            @else
                                                <span class="text-gray-300 font-bold">-</span>
                                            @endif
                                        </td>
                                        <td class="px-6 py-5 text-right font-black {{ $item['is_returnable'] ? 'text-gray-700' : 'text-gray-400' }}">
                                            @if($item['is_returnable'])
                                                C$ {{ number_format((float) $item['qty_to_return'] * (float) $item['gross_unit_price'], 2) }}
                                            @else
                                                C$ 0.00
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="lg:col-span-4 space-y-6">
                    <div class="bg-white p-6 rounded-2xl shadow-sm border border-gray-200 sticky top-6">
                        <h3 class="font-black text-gray-800 uppercase text-sm mb-4 border-b pb-2">Finalizar Devolución</h3>

                        <div class="space-y-4">
                            <div>
                                <label class="block text-[10px] font-bold text-gray-400 uppercase mb-1">Motivo</label>
                                <textarea wire:model="reason" rows="3"
                                          class="w-full border-gray-200 rounded-xl text-sm focus:ring-indigo-500 focus:border-indigo-500"
                                          placeholder="Ej: Cliente canceló, error de impresión, producto dañado..."></textarea>
                            </div>

                            <div class="p-4 bg-rose-50 rounded-xl border border-rose-100 text-center">
                                <span class="block text-xs font-bold text-rose-700 uppercase mb-1">Monto total a reembolsar</span>
                                <span class="text-3xl font-black text-rose-600 tracking-tighter">C$ {{ number_format($totalToReturn, 2) }}</span>
                            </div>

                            <button wire:click="processDevolution"
                                    wire:loading.attr="disabled"
                                    class="w-full bg-gray-900 hover:bg-black text-white font-black py-4 rounded-xl shadow-lg transition transform active:scale-95 disabled:opacity-50">
                                <span wire:loading.remove>CONFIRMAR PROCESO</span>
                                <span wire:loading class="animate-pulse">CARGANDO...</span>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        @endif
    </div>
</div>
