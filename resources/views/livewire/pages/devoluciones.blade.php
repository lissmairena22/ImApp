<?php

use Livewire\Volt\Component;
use Livewire\WithPagination;
use Livewire\Attributes\Computed;
use Mary\Traits\Toast;
use Illuminate\Support\Facades\DB;
use App\Models\Devolution;
use App\Models\Invoice;
use App\Models\Product;
use Carbon\Carbon;

new class extends Component {
    use WithPagination, Toast;

    public string $search = '';
    public bool $drawerModal = false;

    public ?int $invoice_id = null;
    public string $reason = '';

    public array $selectedItems = [];

    public function updatedSearch()
    {
        $this->resetPage();
    }

    public function updatedInvoiceId($value)
    {
        $this->selectedItems = [];

        if (!$value) return;

        $invoiceItems = DB::table('invoice_items')
            ->leftJoin('products', 'invoice_items.product_id', '=', 'products.id')
            ->where('invoice_items.invoice_id', $value)
            ->select('invoice_items.*', 'products.name as product_name')
            ->get();

        foreach ($invoiceItems as $item) {
            $this->selectedItems[] = [
                'invoice_item_id' => $item->id,
                'product_id' => $item->product_id,
                'description' => $item->description,
                'name' => $item->product_name ?? 'Producto / Servicio Genérico',
                'qty_sold' => (float) $item->quantity,
                'price' => (float) $item->unit_price,
                'qty_to_return' => 0,
                'subtotal' => 0.00
            ];
        }
    }

    public function calculateSubtotal($index)
    {
        $item = $this->selectedItems[$index];

        if ($item['qty_to_return'] === '' || $item['qty_to_return'] === null) {
            $this->selectedItems[$index]['qty_to_return'] = 0;
            $item['qty_to_return'] = 0;
        }

        if ($item['qty_to_return'] > $item['qty_sold']) {
            $this->selectedItems[$index]['qty_to_return'] = $item['qty_sold'];
            $this->warning("No puedes devolver más de la cantidad vendida ({$item['qty_sold']}).");
            $item['qty_to_return'] = $item['qty_sold'];
        }

        if ($item['qty_to_return'] < 0) {
            $this->selectedItems[$index]['qty_to_return'] = 0;
            $item['qty_to_return'] = 0;
        }

        $qty = (float) $this->selectedItems[$index]['qty_to_return'];
        $price = (float) $this->selectedItems[$index]['price'];

        $this->selectedItems[$index]['subtotal'] = $qty * $price;
    }

    #[Computed]
    public function totalReturned(): float
    {
        return collect($this->selectedItems)->sum('subtotal');
    }

    public function create()
    {
        $this->reset(['invoice_id', 'reason', 'selectedItems']);
        $this->drawerModal = true;
    }

    public function save()
    {
        $this->validate([
            'invoice_id' => 'required|exists:invoices,id',
            'reason' => 'required|string|min:8|max:255',
        ], [
            'reason.min' => 'El motivo debe ser más descriptivo (mínimo 10 letras).',
        ]);

        $totalMoney = $this->totalReturned;

        if ($totalMoney <= 0) {
            $this->error('Debes ingresar al menos una cantidad válida a devolver.');
            return;
        }

        DB::transaction(function () use ($totalMoney) {

            $devolutionId = DB::table('devolutions')->insertGetId([
                'invoice_id' => $this->invoice_id,
                'user_id' => auth()->id() ?? 1,
                'reason' => $this->reason,
                'amount_returned' => $totalMoney,
                'devolution_date' => now(),
                'created_at' => now(),
                'updated_at' => now()
            ]);

            foreach ($this->selectedItems as $item) {
                if ($item['qty_to_return'] > 0) {
                    DB::table('devolution_items')->insert([
                        'devolution_id' => $devolutionId,
                        'invoice_item_id' => $item['invoice_item_id'],
                        'product_id' => $item['product_id'],
                        'description' => $item['description'] ?? $item['name'],
                        'quantity' => $item['qty_to_return'],
                        'unit_price' => $item['price'],
                        'amount_returned' => $item['subtotal'],
                        'returned_to_stock' => 1,
                        'materials_restored' => 1,
                        'created_at' => now(),
                        'updated_at' => now()
                    ]);

                    Product::where('id', $item['product_id'])
                        ->increment('stock', $item['qty_to_return']);
                }
            }
        });

        $this->drawerModal = false;

        $this->success('Devolución procesada con éxito. Inventario restaurado.');

        $this->reset(['invoice_id', 'reason', 'selectedItems']);
    }

    public function with(): array
    {
        $currentMonth = now()->month;
        $currentYear = now()->year;

        return [
            // Estadísticas del mes para el mini-dashboard
            'stats' => [
                'total_amount' => Devolution::whereMonth('created_at', $currentMonth)->whereYear('created_at', $currentYear)->sum('amount_returned'),
                'total_count' => Devolution::whereMonth('created_at', $currentMonth)->whereYear('created_at', $currentYear)->count(),
            ],

            'activeInvoices' => Invoice::query()
                ->where('status', '!=', 'Anulada')
                ->orderBy('invoice_number', 'desc')
                ->get(),

            'devolutions' => Devolution::query()
                ->with(['invoice.client'])
                ->when($this->search, function($q) {
                    $q->where('reason', 'like', "%{$this->search}%")
                      ->orWhereHas('invoice', function($query) {
                          $query->where('invoice_number', 'like', "%{$this->search}%");
                      })
                      ->orWhere('id', 'like', "%{$this->search}%");
                })
                ->orderBy('id', 'desc')
                ->paginate(10),

            'headers' => [
                ['key' => 'id', 'label' => 'CÓDIGO'],
                ['key' => 'devolution_date', 'label' => 'FECHA / HORA'],
                ['key' => 'invoice.invoice_number', 'label' => 'FACTURA ORIGEN'],
                ['key' => 'reason', 'label' => 'JUSTIFICACIÓN'],
                ['key' => 'amount_returned', 'label' => 'REEMBOLSADO'],
            ]
        ];
    }
}; ?>

<div class="p-6">
    <x-header title="Devoluciones y Notas de Crédito" subtitle="Historial de reembolsos e ingresos a inventario">
        <x-slot:middle class="justify-end!">
            <div class="w-full md:w-80">
                <x-input icon="o-magnifying-glass" placeholder="Buscar factura, motivo o código..." wire:model.live.debounce.500ms="search" clearable class="input-sm bg-white" />
            </div>
        </x-slot:middle>
        <x-slot:actions>
            <x-button icon="o-arrow-path-rounded-square" label="Registrar Devolución" class="btn-error text-white shadow-sm hover:scale-105 transition-transform font-bold" wire:click="create" />
        </x-slot:actions>
    </x-header>

    {{-- Mini-Dashboard de Impacto --}}
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
        <x-stat title="Reembolsos del Mes"
                value="C$ {{ number_format($stats['total_amount'], 2) }}"
                icon="o-arrow-trending-down"
                class="bg-white border-l-4 border-error shadow-sm" />

        <x-stat title="Notas de Crédito Emitidas"
                value="{{ $stats['total_count'] }}"
                icon="o-document-minus"
                class="bg-white border-l-4 border-warning shadow-sm" />
    </div>

    <x-card class="bg-white shadow-sm border-gray-100">
        <x-table :headers="$headers" :rows="$devolutions" with-pagination class="table-sm">

            @scope('cell_id', $dev)
                <span class="font-black text-gray-700">DEV-{{ str_pad($dev->id, 4, '0', STR_PAD_LEFT) }}</span>
            @endscope

            @scope('cell_devolution_date', $dev)
                <div class="flex flex-col">
                    <span class="text-sm font-bold text-gray-700">{{ \Carbon\Carbon::parse($dev->devolution_date)->format('d/m/Y') }}</span>
                    <span class="text-[10px] text-gray-400 font-medium">{{ \Carbon\Carbon::parse($dev->devolution_date)->format('h:i A') }}</span>
                </div>
            @endscope

            @scope('cell_invoice.invoice_number', $dev)
                @if($dev->invoice)
                    <div class="flex items-center gap-2">
                        <x-icon name="o-document-text" class="w-4 h-4 text-indigo-400" />
                        <span class="font-bold text-indigo-600 bg-indigo-50 px-2 py-1 rounded-md text-xs">
                            {{ $dev->invoice->invoice_number }}
                        </span>
                    </div>
                @else
                    <span class="text-gray-400 italic text-xs">Desconocida</span>
                @endif
            @endscope

            @scope('cell_reason', $dev)
                <div class="flex items-start gap-2">
                    <x-icon name="o-chat-bubble-bottom-center-text" class="w-4 h-4 text-gray-400 mt-0.5" />
                    <div class="font-medium text-gray-600 text-sm max-w-xs line-clamp-2" title="{{ $dev->reason }}">
                        {{ $dev->reason }}
                    </div>
                </div>
            @endscope

            @scope('cell_amount_returned', $dev)
                <x-badge value="- C$ {{ number_format($dev->amount_returned, 2) }}" class="badge-error text-white font-black badge-sm" />
            @endscope

            <x-slot:empty>
                <div class="text-center py-12 text-gray-400">
                    <x-icon name="o-face-smile" class="w-16 h-16 inline mb-4 text-gray-300" />
                    <h3 class="text-lg font-bold text-gray-500">Todo en orden</h3>
                    <p class="text-sm">No se han registrado devoluciones recientemente.</p>
                </div>
            </x-slot:empty>
        </x-table>
    </x-card>

    {{-- Panel Lateral Rediseñado --}}
    <x-drawer wire:model="drawerModal" title="Procesar Reembolso / Devolución" right separator with-close-button class="lg:w-2/5 backdrop-blur-sm">
        <x-form wire:submit="save">

            <div class="space-y-5 bg-gray-50 p-5 rounded-2xl border border-gray-100 mb-4">
                <x-select
                    label="Factura de Origen"
                    wire:model.live="invoice_id"
                    :options="$activeInvoices"
                    option-value="id"
                    option-label="invoice_number"
                    placeholder="Buscar factura..."
                    icon="o-magnifying-glass"
                    class="bg-white font-bold"
                />

                <x-textarea
                    label="Justificación del Reembolso"
                    wire:model="reason"
                    placeholder="Escriba detalladamente por qué el cliente devuelve el producto..."
                    rows="2"
                    icon="o-pencil-square"
                    class="bg-white"
                    hint="Obligatorio para control de calidad y auditoría."
                />
            </div>

            @if(count($selectedItems) > 0)
                <div class="flex items-center gap-2 mb-3 mt-6">
                    <x-icon name="o-shopping-bag" class="w-5 h-5 text-gray-400" />
                    <h3 class="font-black text-gray-700 text-sm uppercase tracking-widest">Productos de la Factura</h3>
                </div>

                <div class="space-y-3 max-h-[40vh] overflow-y-auto pr-2 scrollbar-thin">
                    @foreach($selectedItems as $index => $item)
                        <div class="bg-white p-4 rounded-xl border border-gray-200 shadow-sm hover:border-error transition-colors">
                            <div class="flex justify-between items-start mb-3 border-b border-gray-100 pb-2">
                                <span class="font-bold text-sm text-gray-800">{{ $item['name'] }}</span>
                                <span class="text-[10px] px-2 py-1 bg-gray-100 rounded-md text-gray-600 font-bold uppercase tracking-wider">
                                    Vendidos: {{ $item['qty_sold'] }}
                                </span>
                            </div>

                            <div class="grid grid-cols-3 gap-4 items-end">
                                <div>
                                    <span class="text-[10px] text-gray-400 uppercase font-bold block mb-1">Precio Unit.</span>
                                    <span class="text-sm font-semibold text-gray-600">C$ {{ number_format($item['price'], 2) }}</span>
                                </div>
                                <div>
                                    <x-input
                                        label="A devolver"
                                        type="number"
                                        wire:model.live="selectedItems.{{ $index }}.qty_to_return"
                                        wire:keyup="calculateSubtotal({{ $index }})"
                                        min="0"
                                        max="{{ $item['qty_sold'] }}"
                                        placeholder="0"
                                        class="input-sm text-center font-black text-error bg-red-50 focus:border-error focus:ring-error"
                                    />
                                </div>
                                <div class="text-right">
                                    <span class="text-[10px] text-gray-400 uppercase font-bold block mb-1">Subtotal</span>
                                    <span class="text-sm font-black text-error">
                                        C$ {{ number_format($item['subtotal'], 2) }}
                                    </span>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>

                {{-- Caja de Total Resaltada --}}
                <div class="bg-error/10 border-2 border-error/20 rounded-2xl p-5 flex justify-between items-center mt-6 shadow-sm">
                    <div>
                        <span class="font-black text-error text-xs uppercase tracking-widest block mb-1">Efectivo a Entregar</span>
                        <span class="text-xs text-error/70 font-medium">Reembolso total al cliente</span>
                    </div>
                    <span class="text-3xl font-black text-error">C$ {{ number_format($this->totalReturned, 2) }}</span>
                </div>
            @else
                @if($invoice_id)
                    <div class="p-8 text-center bg-gray-50 rounded-2xl border border-dashed border-gray-200 mt-6">
                        <x-icon name="o-archive-box" class="w-8 h-8 mx-auto text-gray-300 mb-2" />
                        <p class="text-sm text-gray-400 font-medium">No hay productos en esta factura.</p>
                    </div>
                @endif
            @endif

            <x-slot:actions>
                <x-button label="Cancelar" @click="$wire.drawerModal = false" class="btn-ghost text-gray-500" />
                <x-button label="Procesar Reembolso" type="submit" icon="o-check-circle" class="btn-error text-white font-black shadow-sm" spinner="save" :disabled="$this->totalReturned <= 0" />
            </x-slot:actions>
        </x-form>
    </x-drawer>
</div>
