<?php

use Livewire\Volt\Component;
use Livewire\WithPagination;
use Livewire\Attributes\Computed;
use Mary\Traits\Toast;
use Illuminate\Support\Facades\DB;
use App\Models\Devolution;
use App\Models\Invoice;
use App\Models\Product;

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

    #[Computed]
    public function devolutions()
    {
        return Devolution::query()
            ->with(['invoice.client'])
            ->when($this->search, function($q) {
                $q->where('reason', 'like', "%{$this->search}%")
                  ->orWhere('id', $this->search);
            })
            ->orderBy('id', 'desc')
            ->paginate(10);
    }

    #[Computed]
    public function activeInvoices()
    {
        return Invoice::query()
            ->where('status', '!=', 'Anulada')
            ->orderBy('invoice_number', 'desc')
            ->get();
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


        // Crear devolución principal
        $devolutionId = DB::table('devolutions')->insertGetId([

            'invoice_id' => $this->invoice_id,

            'user_id' => auth()->id(),

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
                    ->increment(
                        'stock',
                        $item['qty_to_return']
                    );
            }
        }
    });



    $this->drawerModal = false;


    $this->success(
        'Devolución procesada con éxito. Inventario restaurado.'
    );


    $this->reset([
        'invoice_id',
        'reason',
        'selectedItems'
    ]);
}

    public function with(): array
    {
        return [
            'headers' => [
                ['key' => 'id', 'label' => 'CÓDIGO'],
                ['key' => 'date', 'label' => 'FECHA'],
                ['key' => 'invoice.invoice_number', 'label' => 'N° FACTURA'],
                ['key' => 'reason', 'label' => 'MOTIVO / JUSTIFICACIÓN'],
                ['key' => 'amount_returned', 'label' => 'MONTO REEMBOLSADO'],
            ]
        ];
    }
}; ?>

<div>
    <x-header title="Devoluciones sobre Ventas" subtitle="Historial y registro de notas de crédito e ingresos a inventario">
        <x-slot:middle class="justify-end!">
            <x-input icon="o-magnifying-glass" placeholder="Buscar por motivo o código..." wire:model.live.debounce.500ms="search" clearable />
        </x-slot:middle>
        <x-slot:actions>
            <x-button icon="o-arrow-path-rounded-square" label="Nueva Devolución" class="btn-primary" wire:click="create" />
        </x-slot:actions>
    </x-header>

    <x-card>
        <x-table :headers="$headers" :rows="$this->devolutions" with-pagination>
            @scope('cell_id', $dev)
                <strong>DEV-{{ str_pad($dev->id, 4, '0', STR_PAD_LEFT) }}</strong>
            @endscope

            @scope('cell_date', $dev)
                <span class="text-xs text-gray-500">{{ date('d/m/Y h:i A', strtotime($dev->devolution_date)) }}</span>
            @endscope

            @scope('cell_reason', $dev)
                <div class="font-medium text-gray-700 max-w-xs truncate" title="{{ $dev->reason }}">{{ $dev->reason }}</div>
            @endscope

            @scope('cell_amount_returned', $dev)
                <span class="font-bold text-success">C$ {{ number_format($dev->amount_returned, 2) }}</span>
            @endscope

            <x-slot:empty>
                <div class="text-center p-8 text-gray-400">
                    <x-icon name="o-archive-box-x-mark" class="w-10 h-10 inline mb-2" />
                    <p>No se han registrado devoluciones en el sistema.</p>
                </div>
            </x-slot:empty>
        </x-table>
    </x-card>

    <x-drawer wire:model="drawerModal" title="Registrar Nota de Devolución" right separator with-close-button class="lg:w-2/5">
        <x-form wire:submit="save">

            <x-select
                label="Seleccione la Factura de Origen"
                wire:model.live="invoice_id"
                :options="$this->activeInvoices"
                option-value="id"
                option-label="invoice_number"
                placeholder="Buscar factura..."
                icon="o-document-text"
            />

            <x-textarea
                label="Motivo de la Devolución"
                wire:model="reason"
                placeholder="Ej: Cliente canceló el pedido de las tarjetas o error en diseño de etiquetas..."
                rows="2"
                hint="Mínimo 10 caracteres obligatorios para auditoría."
            />

            @if(count($selectedItems) > 0)
                <div class="divider text-xs font-bold text-gray-400">PRODUCTOS COMPRADOS</div>

                <div class="space-y-3 max-h-80 overflow-y-auto pr-1">
                    @foreach($selectedItems as $index => $item)
                        <div class="bg-base-200 p-3 rounded-lg border border-base-300">
                            <div class="flex justify-between items-start mb-2">
                                <span class="font-bold text-sm text-gray-700">{{ $item['name'] }}</span>
                                <span class="text-xs px-2 py-0.5 bg-base-100 rounded-full text-gray-500 font-medium">
                                    Vendidos: {{ $item['qty_sold'] }}
                                </span>
                            </div>

                            <div class="grid grid-cols-3 gap-3 items-end">
                                <div>
                                    <span class="text-xs text-gray-400 block">Precio Unit.</span>
                                    <span class="text-sm font-semibold">C$ {{ number_format($item['price'], 2) }}</span>
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
                                        class="input-sm text-center font-bold"
                                    />
                                </div>
                                <div class="text-right">
                                    <span class="text-xs text-gray-400 block">Subtotal</span>
                                    <span class="text-sm font-bold text-error">
                                        C$ {{ number_format($item['subtotal'], 2) }}
                                    </span>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>

                <div class="bg-primary/5 border border-primary/20 rounded-lg p-4 flex justify-between items-center mt-4">
                    <span class="font-black text-gray-600 text-sm">TOTAL A REEMBOLSAR:</span>
                    <span class="text-xl font-black text-primary">C$ {{ number_format($this->totalReturned, 2) }}</span>
                </div>
            @endif

            <x-slot:actions>
                <x-button label="Cancelar" @click="$wire.drawerModal = false" class="btn-ghost" />
                <x-button label="Procesar Nota" type="submit" icon="o-check" class="btn-primary" spinner="save" :disabled="count($selectedItems) == 0" />
            </x-slot:actions>
        </x-form>
    </x-drawer>
</div>
