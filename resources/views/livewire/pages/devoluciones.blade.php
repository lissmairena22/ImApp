<?php

use Livewire\Volt\Component;
use App\Models\{Invoice, Devolution, DevolutionItem, Product, CashRegister};
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

        if (strlen($term) < 2) {
            $this->suggestions = [];
            return;
        }

        $this->suggestions = Invoice::where('invoice_number', 'LIKE', "%{$term}%")
            ->where('status', 'Pagada')
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

        $foundInvoice = Invoice::with([
                'items.product.unidad',
                'items.materialConsumptions.material',
                'items.devolutionItems',
                'client',
                'order',
            ])
            ->where('invoice_number', $term)
            ->first();

        if (!$foundInvoice) {
            $this->error('Factura no encontrada.', position: 'toast-top toast-center');
            return;
        }

        if ($foundInvoice->status !== 'Pagada') {
            $this->error('No se puede reembolsar: la factura está en estado "' . $foundInvoice->status . '".', position: 'toast-top toast-center');
            return;
        }

        if ($foundInvoice->order_id && $foundInvoice->order?->status === 'EnProceso') {
            $this->error('No se puede reembolsar desde aquí: el pedido está en producción.', position: 'toast-top toast-center');
            return;
        }

        $this->invoice = $foundInvoice;
        $this->invoiceFactor = $this->invoiceFactorFor($foundInvoice);
        $this->canRestoreServiceMaterials = $foundInvoice->order_id
            && in_array($foundInvoice->order?->status, ['Pendiente', 'Parcial']);

        foreach ($this->invoice->items as $item) {
            $returnedQty = (float) $item->devolutionItems->sum('quantity');
            $remainingQty = max(0, (float) $item->quantity - $returnedQty);

            if ($remainingQty <= 0) {
                continue;
            }

            $type = $item->product->type ?? 'Servicio';

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
                'unit' => $item->product->unidad->name ?? 'Und',
                'return_to_stock' => $type === 'Producto',
                'restore_materials' => $type === 'Servicio' && $this->canRestoreServiceMaterials,
                'is_returnable' => $isReturnable,
                'materials' => $item->materialConsumptions
                    ->map(fn($material) => [
                        'name' => $material->material_name,
                        'unit' => $material->unit_name ?? 'Und',
                        'total_consumed' => (float) $material->total_consumed,
                    ])
                    ->values()
                    ->all(),
            ];
        }

        if (empty($this->items)) {
            $this->invoice = null;
            $this->error('Esta factura ya no tiene unidades disponibles para devolver.', position: 'toast-top toast-center');
        }
    }

    public function updatedItems(): void
    {
        $this->calculateTotal();
    }

    private function calculateTotal(): void
    {
        $this->totalToReturn = round(collect($this->items)->sum(function ($item) {
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
        $this->calculateTotal();

        if ($this->totalToReturn <= 0 || trim($this->reason) === '') {
            $this->error('Indique una cantidad válida a devolver y el motivo.', position: 'toast-top toast-center');
            return;
        }

        if (!$this->quantitiesAreValid()) {
            return;
        }

        $this->invoice->refresh();

        if ($this->invoice->status !== 'Pagada') {
            $this->error('La factura ya no es apta para devolución.', position: 'toast-top toast-center');
            return;
        }

        try {
            DB::transaction(function () {
                $devolution = Devolution::create([
                    'invoice_id' => $this->invoice->id,
                    'user_id' => auth()->id() ?? 1,
                    'devolution_date' => now(),
                    'reason' => $this->reason,
                    'amount_returned' => $this->totalToReturn,
                ]);

                foreach ($this->items as $item) {
                    if (!$item['is_returnable']) {
                        continue;
                    }

                    $qty = (float) $item['qty_to_return'];

                    if ($qty <= 0) {
                        continue;
                    }

                    $returnedToStock = $this->restoreProductStock($item, $qty);
                    $materialsRestored = $this->restoreServiceMaterials($item, $qty);

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

                $this->registerCashOut();

                if ($this->isFullReturn()) {
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

    private function invoiceFactorFor(Invoice $invoice): float
    {
        $subtotal = max((float) $invoice->subtotal, 0.01);
        return round((float) $invoice->total / $subtotal, 6);
    }

    private function quantitiesAreValid(): bool
    {
        foreach ($this->items as $item) {
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

    private function restoreProductStock(array $item, float $qty): bool
    {
        if ($item['type'] !== 'Producto' || empty($item['product_id']) || empty($item['return_to_stock'])) {
            return false;
        }

        Product::where('id', $item['product_id'])->lockForUpdate()->increment('stock', $qty);
        return true;
    }

    private function restoreServiceMaterials(array $item, float $qty): bool
    {
        if ($item['type'] !== 'Servicio' || empty($item['restore_materials'])) {
            return false;
        }

        $invoiceItem = $this->invoice->items->firstWhere('id', $item['id']);

        if (!$invoiceItem || (float) $invoiceItem->quantity <= 0) {
            return false;
        }

        $ratio = $qty / (float) $invoiceItem->quantity;
        $restored = false;

        foreach ($invoiceItem->materialConsumptions as $materialUse) {
            if (!$materialUse->material_id) {
                continue;
            }

            $restoreQty = round((float) $materialUse->total_consumed * $ratio, 2);

            if ($restoreQty > 0) {
                Product::where('id', $materialUse->material_id)->lockForUpdate()->increment('stock', $restoreQty);
                $restored = true;
            }
        }

        return $restored;
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
            // Si el item no es retornable, asumimos que siempre "quedará" en la factura
            if (!$item['is_returnable']) {
                return (float) $item['max_qty'];
            }
            return max(0, (float) $item['max_qty'] - (float) $item['qty_to_return']);
        });

        return $remainingAfterReturn <= 0;
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

                                            @if($item['is_returnable'])
                                                @if($item['type'] === 'Producto')
                                                    <label class="mt-3 flex items-center gap-2 text-[11px] font-bold text-emerald-700 cursor-pointer">
                                                        <input type="checkbox" wire:model.live="items.{{ $id }}.return_to_stock" class="rounded border-gray-300 text-emerald-600 focus:ring-emerald-500">
                                                        Reintegrar al inventario
                                                    </label>
                                                @elseif(!empty($item['materials']))
                                                    <div class="mt-3 space-y-1">
                                                        @foreach($item['materials'] as $material)
                                                            <div class="text-[10px] text-gray-500 bg-gray-50 border border-gray-100 rounded px-2 py-1">
                                                                Material usado: <b>{{ $material['name'] }}</b>
                                                                ({{ number_format($material['total_consumed'], 2) }} {{ $material['unit'] }})
                                                            </div>
                                                        @endforeach
                                                    </div>
                                                    <label class="mt-2 flex items-center gap-2 text-[11px] font-bold {{ $canRestoreServiceMaterials ? 'text-indigo-700 cursor-pointer' : 'text-gray-400' }}">
                                                        <input type="checkbox" wire:model.live="items.{{ $id }}.restore_materials" @disabled(!$canRestoreServiceMaterials) class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                                                        Reintegrar materiales no producidos
                                                    </label>
                                                @endif
                                            @else
                                                <p class="text-[10px] text-gray-400 mt-2 italic">Este servicio es de consumo final y no admite reembolsos.</p>
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
                            <div class="p-3 bg-indigo-50 rounded-xl border border-indigo-100 text-xs text-indigo-700 font-medium">
                                El reembolso se calcula proporcionalmente con IVA/descuento de la factura. Los servicios terminados no reintegran materiales, porque ya fueron consumidos por la imprenta.
                            </div>

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
