<?php

use Livewire\Volt\Component;
use App\Models\CashRegister;
use App\Models\Client;
use App\Models\Devolution;
use App\Models\Invoice;
use App\Models\InventoryOutput;
use App\Models\Order;
use App\Models\Product;
use App\Models\Provider;
use App\Models\Purchase;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;
use Maatwebsite\Excel\Facades\Excel;
use Mary\Traits\Toast;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

new class extends Component
{
    use Toast;

    public string $activeReport = '';
    public string $startDate = '';
    public string $endDate = '';
    public string $productReportMode = 'inventario';
    public bool $reportGenerated = false;
    public float $cashExchangeRate = 36.5;

    public array $operationalReports = [
        [
            'title' => 'Reporte de Compra',
            'description' => 'Consulta y genera el resumen de compras e insumos registrados.',
            'icon' => 'o-truck',
            'color' => 'border-primary',
            'type' => 'compra',
        ],
        [
            'title' => 'Reporte de Venta',
            'description' => 'Genera el consolidado de ventas realizadas en el sistema.',
            'icon' => 'o-shopping-cart',
            'color' => 'border-success',
            'type' => 'venta',
        ],
        [
            'title' => 'Reporte de Pedidos',
            'description' => 'Revisa las ordenes de trabajo y pedidos de produccion.',
            'icon' => 'o-clipboard-document-list',
            'color' => 'border-secondary',
            'type' => 'pedidos',
        ],
        [
            'title' => 'Reporte de Devoluciones',
            'description' => 'Genera el historial de devoluciones realizadas por clientes.',
            'icon' => 'o-arrow-path-rounded-square',
            'color' => 'border-warning',
            'type' => 'devoluciones',
        ],
        [
            'title' => 'Reporte de Salidas de Inventario',
            'description' => 'Consulta las salidas registradas por bajas, mermas o ajustes.',
            'icon' => 'o-archive-box-x-mark',
            'color' => 'border-info',
            'type' => 'salidas de inventario',
        ],
        [
            'title' => 'Reporte de Arqueo de Caja',
            'description' => 'Obtiene el resumen de caja, movimientos y cierre de turno.',
            'icon' => 'o-calculator',
            'color' => 'border-error',
            'type' => 'arqueo de caja',
        ],
    ];

    public array $registryReports = [
        [
            'title' => 'Lista de Productos',
            'description' => 'Catalogo de productos, materiales e inventario disponible.',
            'icon' => 'o-square-3-stack-3d',
            'route' => 'productos',
        ],
        [
            'title' => 'Lista de Clientes',
            'description' => 'Registro completo de clientes activos en la imprenta.',
            'icon' => 'o-user-group',
            'route' => 'clientes',
        ],
        [
            'title' => 'Lista de Usuarios',
            'description' => 'Usuarios del sistema, roles y datos de acceso.',
            'icon' => 'o-users',
            'route' => 'usuarios',
        ],
        [
            'title' => 'Lista de Proveedores',
            'description' => 'Directorio de proveedores e informacion comercial.',
            'icon' => 'o-building-office-2',
            'route' => 'proveedores',
        ],
    ];

    public function generate(string $type): void
    {
        if (in_array($type, ['compra', 'venta', 'pedidos', 'devoluciones', 'salidas de inventario', 'productos', 'clientes', 'usuarios', 'proveedores', 'arqueo de caja'])) {
            $this->activeReport = $type;
            $this->productReportMode = 'inventario';
            $this->reportGenerated = in_array($type, ['productos', 'clientes', 'usuarios', 'proveedores']);
            $this->resetValidation();
            return;
        }

        $this->info('Preparando reporte de ' . ucfirst($type) . '.', position: 'toast-top toast-center');
    }

    public function backToReports(): void
    {
        $this->activeReport = '';
        $this->reportGenerated = false;
        $this->reset(['startDate', 'endDate']);
    }

    public function buildReport(): void
    {
        $rules = [
            'startDate' => ['nullable', 'date'],
            'endDate' => ['nullable', 'date', 'after_or_equal:startDate'],
        ];

        if ($this->activeReport === 'arqueo de caja') {
            $rules['cashExchangeRate'] = ['required', 'numeric', 'min:0.01'];
        }

        $this->validate($rules, [
            'endDate.after_or_equal' => 'La fecha fin debe ser igual o posterior a la fecha inicio.',
            'cashExchangeRate.min' => 'La tasa de cambio debe ser mayor que cero.',
        ]);

        $this->reportGenerated = true;
        $this->success('Reporte generado correctamente.', position: 'toast-top toast-center');
    }

    public function export(string $format)
    {
        if (!$this->reportGenerated) {
            $this->buildReport();
        }

        $rows = $this->reportRows();

        $headers = $this->reportHeaders();
        $filename = str($this->reportTitle())->lower()->replace(' ', '-')->toString() . '-' . now()->format('Ymd-His');

        return match ($format) {
            'pdf' => $this->exportPdf($headers, $rows, $filename),
            'excel' => $this->exportExcel($headers, $rows, $filename),
            'word' => $this->exportWord($headers, $rows, $filename),
            default => null,
        };
    }

    public function with(): array
    {
        return [
            'reportRows' => $this->reportGenerated
                ? $this->reportRows()
                : collect(),
            'reportHeaders' => $this->reportHeaders(),
            'reportTitle' => $this->reportTitle(),
            'reportPeriod' => $this->reportPeriod(),
            'generatedAt' => now()->format('d/m/Y H:i'),
        ];
    }

    private function reportRows(): Collection
    {
        return match ($this->activeReport) {
            'compra' => $this->purchaseRows(),
            'venta' => $this->salesRows(),
            'pedidos' => $this->orderRows(),
            'devoluciones' => $this->devolutionRows(),
            'salidas de inventario' => $this->inventoryOutputRows(),
            'productos' => $this->productRows(),
            'clientes' => $this->clientRows(),
            'usuarios' => $this->userRows(),
            'proveedores' => $this->providerRows(),
            'arqueo de caja' => $this->cashRegisterRows(),
            default => collect(),
        };
    }

    private function reportHeaders(): array
    {
        return match ($this->activeReport) {
            'compra' => $this->purchaseHeaders(),
            'venta' => $this->salesHeaders(),
            'pedidos' => $this->orderHeaders(),
            'devoluciones' => $this->devolutionHeaders(),
            'salidas de inventario' => $this->inventoryOutputHeaders(),
            'productos' => $this->productHeaders(),
            'clientes' => $this->clientHeaders(),
            'usuarios' => $this->userHeaders(),
            'proveedores' => $this->providerHeaders(),
            'arqueo de caja' => $this->cashRegisterHeaders(),
            default => [],
        };
    }

    public function showInventoryReport(): void
    {
        $this->productReportMode = 'inventario';
        $this->reportGenerated = true;
    }

    public function showLowStockReport(): void
    {
        $this->productReportMode = 'agotarse';
        $this->reportGenerated = true;
    }

    private function purchaseRows(): Collection
    {
        $purchases = Purchase::query()
            ->with(['provider', 'items.product.category', 'items.product.unit'])
            ->when($this->startDate, fn($query) => $query->whereDate('purchase_date', '>=', $this->startDate))
            ->when($this->endDate, fn($query) => $query->whereDate('purchase_date', '<=', $this->endDate))
            ->orderBy('purchase_date')
            ->get();

        $rows = $purchases->flatMap(function ($purchase) {
            return $purchase->items->map(function ($item, $index) use ($purchase) {
                return [
                    '#' => $purchase->id . '-' . ($index + 1),
                    'Fecha' => Carbon::parse($purchase->purchase_date)->format('d/m/Y'),
                    'No factura' => $purchase->provider_invoice_number ?? 'Sin factura',
                    'Proveedor' => $purchase->provider->company_name ?? 'Sin proveedor',
                    'Producto' => $item->product->name ?? 'Producto no disponible',
                    'Categoria' => $item->product->category->name ?? 'Sin categoria',
                    'Unidad de medida' => $item->product->unit->name ?? 'Und',
                    'Precio de compra' => 'C$ ' . number_format((float) $item->cost_price, 2),
                    'Cantidad' => number_format((float) $item->quantity, 2),
                    'Total' => 'C$ ' . number_format((float) $item->subtotal, 2),
                ];
            });
        });

        return $this->appendTotalRow(
            $rows,
            $this->purchaseHeaders(),
            'Cantidad',
            'Total',
            'Total Compras',
            (float) $purchases->sum('total')
        );
    }

    private function salesRows(): Collection
    {
        $invoices = Invoice::query()
            ->with(['client', 'items.product.category', 'items.product.unit'])
            ->where('status', '!=', 'Anulada')
            ->when($this->startDate, fn($query) => $query->whereDate('invoice_date', '>=', $this->startDate))
            ->when($this->endDate, fn($query) => $query->whereDate('invoice_date', '<=', $this->endDate))
            ->orderBy('invoice_date')
            ->get();

        $rows = $invoices->flatMap(function ($invoice) {
                return $invoice->items->map(function ($item, $index) use ($invoice) {
                    return [
                        '#' => $invoice->id . '-' . ($index + 1),
                        'Fecha' => Carbon::parse($invoice->invoice_date)->format('d/m/Y'),
                        'No.Factura' => $invoice->invoice_number,
                        'Cliente' => $invoice->client->name ?? 'Cliente General',
                        'Producto' => $item->description,
                        'Categoria' => $item->product->category->name ?? 'Sin categoria',
                        'Unidad/Medida' => $item->product->unit->name ?? 'Und',
                        'Precio de venta' => 'C$ ' . number_format((float) $item->unit_price, 2),
                        'Cantidad' => number_format((float) $item->quantity, 2),
                        'Subtotal' => 'C$ ' . number_format((float) $item->subtotal, 2),
                        'Total' => 'C$ ' . number_format((float) $invoice->total, 2),
                    ];
                });
            });

        return $this->appendTotalRow(
            $rows,
            $this->salesHeaders(),
            'Subtotal',
            'Total',
            'Total Ventas',
            (float) $invoices->sum('total')
        );
    }

    private function orderRows(): Collection
    {
        return Order::query()
            ->with(['client', 'invoice', 'items.product.category', 'items.product.unit'])
            ->when($this->startDate, fn($query) => $query->whereDate('order_date', '>=', $this->startDate))
            ->when($this->endDate, fn($query) => $query->whereDate('order_date', '<=', $this->endDate))
            ->orderBy('order_date')
            ->get()
            ->flatMap(function ($order) {
                return $order->items->map(function ($item, $index) use ($order) {
                    return [
                        '#' => $order->id . '-' . ($index + 1),
                        'Fecha' => Carbon::parse($order->order_date)->format('d/m/Y'),
                        'Orden' => 'ORD-' . str_pad($order->id, 3, '0', STR_PAD_LEFT),
                        'Factura' => $order->invoice->invoice_number ?? 'Sin factura',
                        'Cliente' => $order->client->name ?? 'Sin cliente',
                        'Producto' => $item->description,
                        'Categoria' => $item->product->category->name ?? 'Sin categoria',
                        'Unidad/Medida' => $item->product->unit->name ?? 'Und',
                        'Precio' => 'C$ ' . number_format((float) $item->unit_price, 2),
                        'Cantidad' => number_format((float) $item->quantity, 2),
                        'Subtotal' => 'C$ ' . number_format((float) $item->subtotal, 2),
                        'Total pedido' => 'C$ ' . number_format((float) $order->estimated_price, 2),
                        'Estado' => $order->status,
                    ];
                });
            });
    }

    private function devolutionRows(): Collection
    {
        $devolutions = Devolution::query()
            ->with(['invoice.client', 'items.product.category', 'items.product.unit'])
            ->when($this->startDate, fn($query) => $query->whereDate('devolution_date', '>=', $this->startDate))
            ->when($this->endDate, fn($query) => $query->whereDate('devolution_date', '<=', $this->endDate))
            ->orderBy('devolution_date')
            ->get();

        $rows = $devolutions->flatMap(function ($devolution) {
            return $devolution->items->map(function ($item, $index) use ($devolution) {
                return [
                    '#' => $devolution->id . '-' . ($index + 1),
                    'Fecha' => Carbon::parse($devolution->devolution_date)->format('d/m/Y'),
                    'No factura' => $devolution->invoice->invoice_number ?? 'Sin factura',
                    'Cliente' => $devolution->invoice->client->name ?? 'Sin cliente',
                    'Producto' => $item->description,
                    'Categoria' => $item->product->category->name ?? 'Sin categoria',
                    'Unidad de medida' => $item->product->unit->name ?? 'Und',
                    'Motivo de devolucion' => $devolution->reason,
                    'Precio de venta' => 'C$ ' . number_format((float) $item->unit_price, 2),
                    'Cantidad devuelta' => number_format((float) $item->quantity, 2),
                    'Subtotal' => 'C$ ' . number_format((float) $item->amount_returned, 2),
                    'Total' => 'C$ ' . number_format((float) $devolution->amount_returned, 2),
                ];
            });
        });

        return $this->appendTotalRow(
            $rows,
            $this->devolutionHeaders(),
            'Subtotal',
            'Total',
            'Total devoluciones sobre venta',
            (float) $devolutions->sum('amount_returned')
        );
    }

    private function inventoryOutputRows(): Collection
    {
        return InventoryOutput::query()
            ->with(['items.product.category', 'items.product.unit'])
            ->when($this->startDate, fn($query) => $query->whereDate('output_date', '>=', $this->startDate))
            ->when($this->endDate, fn($query) => $query->whereDate('output_date', '<=', $this->endDate))
            ->orderBy('output_date')
            ->get()
            ->flatMap(function ($output) {
                return $output->items->map(function ($item, $index) use ($output) {
                    return [
                        '#' => $output->id . '-' . ($index + 1),
                        'Fecha de salida' => Carbon::parse($output->output_date)->format('d/m/Y'),
                        'Producto' => $item->description,
                        'Categoria' => $item->product->category->name ?? 'Sin categoria',
                        'Unidad de medida' => $item->unit_name ?: ($item->product->unit->name ?? 'Und'),
                        'Motivo' => $output->reason,
                        'Cantidad retirada' => number_format((float) $item->quantity, 2),
                    ];
                });
            });
    }

    private function productRows(): Collection
    {
        return Product::query()
            ->with(['category', 'unit'])
            ->where('is_active', true)
            ->where('type', 'Producto')
            ->when($this->productReportMode === 'agotarse', fn($query) => $query->whereColumn('stock', '<=', 'min_stock'))
            ->orderBy('name')
            ->get()
            ->map(function ($product, $index) {
                $row = [
                    '#' => $index + 1,
                    'Producto' => $product->name,
                    'Categoria' => $product->category->name ?? 'Sin categoria',
                    'Unidad de medida' => $product->unit->name ?? 'Und',
                    'Stock actual' => number_format((float) $product->stock, 2),
                ];

                if ($this->productReportMode === 'inventario') {
                    $row['Precio'] = 'C$ ' . number_format((float) $product->sale_price, 2);
                    $row['Total'] = 'C$ ' . number_format((float) $product->stock * (float) $product->sale_price, 2);
                }

                return $row;
            });
    }

    private function clientRows(): Collection
    {
        return Client::query()
            ->orderBy('name')
            ->get()
            ->map(fn($client, $index) => [
                '#' => $index + 1,
                'Cliente' => $client->name,
                'DNI/RUC' => $client->dni ?? 'Sin registro',
                'Telefono' => $client->phone ?? 'Sin telefono',
                'Correo' => $client->email ?? 'Sin correo',
                'Direccion' => $client->address ?? 'Sin direccion',
                'Estado' => $client->is_active ? 'Activo' : 'Inactivo',
            ]);
    }

    private function userRows(): Collection
    {
        return User::query()
            ->orderBy('name')
            ->get()
            ->map(fn($user, $index) => [
                '#' => $index + 1,
                'Nombre' => $user->name,
                'Usuario' => $user->username,
                'Rol' => $user->role,
                'Estado' => $user->status,
            ]);
    }

    private function providerRows(): Collection
    {
        return Provider::query()
            ->orderBy('company_name')
            ->get()
            ->map(fn($provider, $index) => [
                '#' => $index + 1,
                'Proveedor' => $provider->company_name,
                'RUC' => $provider->ruc ?? 'Sin registro',
                'Telefono' => $provider->phone ?? 'Sin telefono',
                'Correo' => $provider->email ?? 'Sin correo',
                'Direccion' => $provider->address ?? 'Sin direccion',
                'Estado' => $provider->is_active ? 'Activo' : 'Inactivo',
            ]);
    }

    private function cashRegisterRows(): Collection
    {
        $registers = CashRegister::query()
            ->with('user')
            ->where('status', 'Cerrada')
            ->when($this->startDate, fn($query) => $query->whereDate('closed_at', '>=', $this->startDate))
            ->when($this->endDate, fn($query) => $query->whereDate('closed_at', '<=', $this->endDate))
            ->orderBy('closed_at')
            ->get();

        $rows = $registers->map(function ($register) {
            $cashSales = (float) $register->cash_sales;
            $physicalBalance = (float) $register->physical_balance;
            $difference = (float) $register->difference;
            $surplus = max($difference, 0);
            $shortage = abs(min($difference, 0));

            return [
                'Fecha' => Carbon::parse($register->closed_at)->format('d/m/Y'),
                'Nombre del cajero' => $register->user->name ?? 'Sin cajero',
                'Total vendido C$' => 'C$ ' . number_format($cashSales, 2),
                'TOTAL vendido $' => '$ ' . number_format($this->toUsd($cashSales), 2),
                'Total en caja C$' => 'C$ ' . number_format($physicalBalance, 2),
                'Total en caja $' => '$ ' . number_format($this->toUsd($physicalBalance), 2),
                'Sobrante C$' => 'C$ ' . number_format($surplus, 2),
                'Sobrante $' => '$ ' . number_format($this->toUsd($surplus), 2),
                'Faltante C$' => 'C$ ' . number_format($shortage, 2),
                'Faltante $' => '$ ' . number_format($this->toUsd($shortage), 2),
            ];
        });

        return $this->appendCashTotalsRow($rows, $registers);
    }

    private function reportTitle(): string
    {
        return match ($this->activeReport) {
            'compra' => 'Reporte de Compras',
            'pedidos' => 'Reporte de Pedidos',
            'devoluciones' => 'Reporte de Devoluciones',
            'salidas de inventario' => 'Reporte de Salidas de Inventario',
            'productos' => $this->productReportMode === 'agotarse' ? 'Reporte de Productos Proximos a Agotarse' : 'Reporte de Inventario Actual',
            'clientes' => 'Reporte de Clientes',
            'usuarios' => 'Reporte de Usuarios',
            'proveedores' => 'Reporte de Proveedores',
            'arqueo de caja' => 'Reporte de Arqueo de Caja',
            default => 'Reporte de Ventas',
        };
    }

    private function reportPeriod(): string
    {
        if (!$this->startDate && !$this->endDate) {
            if (in_array($this->activeReport, ['productos', 'clientes', 'usuarios', 'proveedores'])) {
                return $this->activeReport === 'productos' ? 'Inventario actual' : 'Listado actual';
            }

            return 'Historico completo';
        }

        return 'Del ' . ($this->startDate ? date('d/m/Y', strtotime($this->startDate)) : 'inicio')
            . ' al ' . ($this->endDate ? date('d/m/Y', strtotime($this->endDate)) : 'actual');
    }

    private function salesHeaders(): array
    {
        return ['#', 'Fecha', 'No.Factura', 'Cliente', 'Producto', 'Categoria', 'Unidad/Medida', 'Precio de venta', 'Cantidad', 'Subtotal', 'Total'];
    }

    private function purchaseHeaders(): array
    {
        return ['#', 'Fecha', 'No factura', 'Proveedor', 'Producto', 'Categoria', 'Unidad de medida', 'Precio de compra', 'Cantidad', 'Total'];
    }

    private function orderHeaders(): array
    {
        return ['#', 'Fecha', 'Orden', 'Factura', 'Cliente', 'Producto', 'Categoria', 'Unidad/Medida', 'Precio', 'Cantidad', 'Subtotal', 'Total pedido', 'Estado'];
    }

    private function devolutionHeaders(): array
    {
        return ['#', 'Fecha', 'No factura', 'Cliente', 'Producto', 'Categoria', 'Unidad de medida', 'Motivo de devolucion', 'Precio de venta', 'Cantidad devuelta', 'Subtotal', 'Total'];
    }

    private function inventoryOutputHeaders(): array
    {
        return ['#', 'Fecha de salida', 'Producto', 'Categoria', 'Unidad de medida', 'Motivo', 'Cantidad retirada'];
    }

    private function productHeaders(): array
    {
        if ($this->productReportMode === 'agotarse') {
            return ['#', 'Producto', 'Categoria', 'Unidad de medida', 'Stock actual'];
        }

        return ['#', 'Producto', 'Categoria', 'Unidad de medida', 'Stock actual', 'Precio', 'Total'];
    }

    private function clientHeaders(): array
    {
        return ['#', 'Cliente', 'DNI/RUC', 'Telefono', 'Correo', 'Direccion', 'Estado'];
    }

    private function userHeaders(): array
    {
        return ['#', 'Nombre', 'Usuario', 'Rol', 'Estado'];
    }

    private function providerHeaders(): array
    {
        return ['#', 'Proveedor', 'RUC', 'Telefono', 'Correo', 'Direccion', 'Estado'];
    }

    private function cashRegisterHeaders(): array
    {
        return ['Fecha', 'Nombre del cajero', 'Total vendido C$', 'TOTAL vendido $', 'Total en caja C$', 'Total en caja $', 'Sobrante C$', 'Sobrante $', 'Faltante C$', 'Faltante $'];
    }

    private function appendTotalRow(Collection $rows, array $headers, string $labelColumn, string $totalColumn, string $label, float $total): Collection
    {
        if ($rows->isEmpty()) {
            return $rows;
        }

        $totalRow = collect($headers)->mapWithKeys(fn($header) => [$header => ''])->all();
        $totalRow[$labelColumn] = $label;
        $totalRow[$totalColumn] = 'C$ ' . number_format($total, 2);
        $totalRow['_is_total'] = true;

        return $rows->push($totalRow);
    }

    private function appendCashTotalsRow(Collection $rows, Collection $registers): Collection
    {
        if ($rows->isEmpty()) {
            return $rows;
        }

        $cashSales = (float) $registers->sum('cash_sales');
        $physicalBalance = (float) $registers->sum('physical_balance');
        $surplus = (float) $registers->sum(fn($register) => max((float) $register->difference, 0));
        $shortage = (float) $registers->sum(fn($register) => abs(min((float) $register->difference, 0)));

        return $rows->push([
            'Fecha' => 'Totales',
            'Nombre del cajero' => '',
            'Total vendido C$' => 'C$ ' . number_format($cashSales, 2),
            'TOTAL vendido $' => '$ ' . number_format($this->toUsd($cashSales), 2),
            'Total en caja C$' => 'C$ ' . number_format($physicalBalance, 2),
            'Total en caja $' => '$ ' . number_format($this->toUsd($physicalBalance), 2),
            'Sobrante C$' => 'C$ ' . number_format($surplus, 2),
            'Sobrante $' => '$ ' . number_format($this->toUsd($surplus), 2),
            'Faltante C$' => 'C$ ' . number_format($shortage, 2),
            'Faltante $' => '$ ' . number_format($this->toUsd($shortage), 2),
            '_is_total' => true,
        ]);
    }

    private function toUsd(float $amount): float
    {
        return $this->cashExchangeRate > 0 ? $amount / $this->cashExchangeRate : 0;
    }

   private function exportPdf(array $headers, Collection $rows, string $filename)
    {
        if ($rows->count() > 500) {
            $this->error('El PDF tiene demasiadas filas. Filtra por fechas o usa Excel para el historico completo.', position: 'toast-top toast-center');
            return null;
        }

        ini_set('memory_limit', '1024M');

        // 1. Guardamos el PDF generado en una variable en lugar de retornarlo de golpe
        $pdf = Pdf::setOptions([
            'isRemoteEnabled' => false,
            'isHtml5ParserEnabled' => true,
            'isFontSubsettingEnabled' => true,
            'dpi' => 72,
            'defaultFont' => 'DejaVu Sans',
        ])->loadView('livewire.pages.reportes-pdf', [
            'title' => $this->reportTitle(),
            'period' => $this->reportPeriod(),
            'generatedAt' => now()->format('d/m/Y H:i'),
            'headers' => $headers,
            'rows' => $rows,
        ])->setPaper('a4', 'landscape');

        // 2. Usamos el sistema nativo de Laravel para forzar a Livewire a descargar el archivo
        return response()->streamDownload(function () use ($pdf) {
            echo $pdf->output();
        }, $filename . '.pdf');
    }

    private function exportExcel(array $headers, Collection $rows, string $filename)
    {
        $export = new class($headers, $rows, $this->reportTitle(), $this->reportPeriod()) implements FromArray, ShouldAutoSize, WithEvents {
            public function __construct(
                private array $headers,
                private Collection $rows,
                private string $title,
                private string $period
            ) {}

            public function array(): array
            {
                $dataRows = $this->rows
                    ->map(fn($row) => collect($this->headers)->map(fn($header) => $row[$header])->all())
                    ->all();

                return [
                    ['Imprenta Minerva'],
                    ['Matagalpa'],
                    [$this->title],
                    ['Periodo', $this->period],
                    ['Fecha de generacion', now()->format('d/m/Y H:i')],
                    [],
                    $this->headers,
                    ...$dataRows,
                ];
            }

            public function registerEvents(): array
            {
                return [
                    AfterSheet::class => function (AfterSheet $event) {
                        $sheet = $event->sheet->getDelegate();
                        $lastColumn = $sheet->getHighestColumn();
                        $lastRow = $sheet->getHighestRow();
                        $tableHeaderRow = 7;

                        $sheet->mergeCells("A1:{$lastColumn}1");
                        $sheet->mergeCells("A2:{$lastColumn}2");
                        $sheet->mergeCells("A3:{$lastColumn}3");
                        $sheet->mergeCells("B4:{$lastColumn}4");
                        $sheet->mergeCells("B5:{$lastColumn}5");

                        $sheet->getStyle("A1:A3")->applyFromArray([
                            'font' => ['bold' => true, 'size' => 14, 'color' => ['rgb' => '111827']],
                            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
                        ]);

                        $sheet->getStyle("A4:B5")->applyFromArray([
                            'font' => ['bold' => true],
                            'alignment' => ['horizontal' => Alignment::HORIZONTAL_LEFT],
                        ]);

                        $sheet->getStyle("A{$tableHeaderRow}:{$lastColumn}{$tableHeaderRow}")->applyFromArray([
                            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                            'fill' => [
                                'fillType' => Fill::FILL_SOLID,
                                'startColor' => ['rgb' => '2563EB'],
                            ],
                            'alignment' => [
                                'horizontal' => Alignment::HORIZONTAL_CENTER,
                                'vertical' => Alignment::VERTICAL_CENTER,
                            ],
                        ]);

                        $sheet->getStyle("A{$tableHeaderRow}:{$lastColumn}{$lastRow}")->applyFromArray([
                            'borders' => [
                                'allBorders' => [
                                    'borderStyle' => Border::BORDER_THIN,
                                    'color' => ['rgb' => '9CA3AF'],
                                ],
                            ],
                            'alignment' => [
                                'vertical' => Alignment::VERTICAL_CENTER,
                                'wrapText' => true,
                            ],
                        ]);

                        if ($lastRow >= 8) {
                            $sheet->getStyle("A8:{$lastColumn}{$lastRow}")->applyFromArray([
                                'fill' => [
                                    'fillType' => Fill::FILL_SOLID,
                                    'startColor' => ['rgb' => 'F9FAFB'],
                                ],
                            ]);
                        }

                        if ($lastRow > $tableHeaderRow && !empty($this->rows->last()['_is_total'])) {
                            $sheet->getStyle("A{$lastRow}:{$lastColumn}{$lastRow}")->applyFromArray([
                                'font' => ['bold' => true],
                                'fill' => [
                                    'fillType' => Fill::FILL_SOLID,
                                    'startColor' => ['rgb' => 'DBEAFE'],
                                ],
                            ]);
                        }

                        $sheet->freezePane('A8');
                        $sheet->setAutoFilter("A{$tableHeaderRow}:{$lastColumn}{$lastRow}");
                    },
                ];
            }
        };

        return Excel::download($export, $filename . '.xlsx');
    }

    private function exportWord(array $headers, Collection $rows, string $filename)
    {
        $phpWord = new PhpWord();
        $phpWord->addTitleStyle(1, ['bold' => true, 'size' => 16]);

        $section = $phpWord->addSection(['orientation' => 'landscape']);
        $section->addText('Imprenta Minerva', ['bold' => true, 'size' => 16]);
        $section->addText('Matagalpa');
        $section->addText($this->reportTitle(), ['bold' => true, 'size' => 13]);
        $section->addText('Periodo: ' . $this->reportPeriod());
        $section->addText('Fecha de generacion: ' . now()->format('d/m/Y H:i'));
        $section->addTextBreak();

        $table = $section->addTable([
            'borderSize' => 6,
            'borderColor' => '999999',
            'cellMargin' => 80,
        ]);

        $table->addRow();
        foreach ($headers as $header) {
            $table->addCell(1500)->addText($header, ['bold' => true, 'size' => 8]);
        }

        foreach ($rows as $row) {
            $table->addRow();
            $font = !empty($row['_is_total']) ? ['bold' => true, 'size' => 8] : ['size' => 8];

            foreach ($headers as $header) {
                $table->addCell(1500)->addText((string) $row[$header], $font);
            }
        }

        if ($rows->isEmpty()) {
            $table->addRow();
            $table->addCell(6000, ['gridSpan' => count($headers)])->addText('No hay registros para el periodo seleccionado.');
        }

        $tempBase = tempnam(sys_get_temp_dir(), 'reporte-');
        $path = $tempBase . '.docx';
        @unlink($tempBase);

        IOFactory::createWriter($phpWord, 'Word2007')->save($path);

        return response()->download($path, $filename . '.docx')->deleteFileAfterSend(true);
    }
};
?>

<div>
    @if($activeReport)
        <div class="space-y-5">
            <x-header :title="$reportTitle" subtitle="Imprenta Minerva, Matagalpa" separator>
                <x-slot:actions>
                    <x-button label="Volver" icon="o-arrow-left" wire:click="backToReports" class="btn-ghost" />
                </x-slot:actions>
            </x-header>

            <x-card shadow class="bg-base-100">
                @if(in_array($activeReport, ['productos', 'clientes', 'usuarios', 'proveedores']))
                    <div class="flex flex-col xl:flex-row xl:items-end xl:justify-between gap-4">
                        <div class="xl:w-[560px]">
                            @if($activeReport === 'productos')
                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                                    <x-button
                                        label="Inventario actual"
                                        icon="o-archive-box"
                                        wire:click="showInventoryReport"
                                        class="w-full {{ $productReportMode === 'inventario' ? 'btn-primary' : 'btn-outline' }}"
                                    />
                                    <x-button
                                        label="Proximos a agotarse"
                                        icon="o-exclamation-triangle"
                                        wire:click="showLowStockReport"
                                        class="w-full {{ $productReportMode === 'agotarse' ? 'btn-warning' : 'btn-outline' }}"
                                    />
                                </div>
                            @else
                                <x-alert
                                    title="Listado actual"
                                    description="Este reporte no requiere parametros de fecha."
                                    icon="o-information-circle"
                                    class="alert-info py-2"
                                />
                            @endif
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-2 xl:w-[420px]">
                            <x-button label="PDF" icon="o-document-arrow-down" wire:click="export('pdf')" spinner="export" class="btn-outline" />
                            <x-button label="Excel" icon="o-table-cells" wire:click="export('excel')" spinner="export" class="btn-outline btn-success" />
                            <x-button label="Word" icon="o-document-text" wire:click="export('word')" spinner="export" class="btn-outline btn-info" />
                        </div>
                    </div>
                @else
                    <div class="grid grid-cols-1 xl:grid-cols-12 gap-4 items-end">
                        <div class="xl:col-span-3">
                            <x-input label="Fecha inicio:" wire:model="startDate" type="date" icon="o-calendar-days" />
                        </div>
                        <div class="xl:col-span-3">
                            <x-input label="Fecha Fin:" wire:model="endDate" type="date" icon="o-calendar-days" />
                        </div>
                        @if($activeReport === 'arqueo de caja')
                            <div class="xl:col-span-2">
                                <x-input label="Tasa C$/$" wire:model="cashExchangeRate" type="number" step="0.01" icon="o-arrows-right-left" />
                            </div>
                        @endif
                        <div class="{{ $activeReport === 'arqueo de caja' ? 'xl:col-span-1' : 'xl:col-span-2' }}">
                            <x-button label="Generar" icon="o-magnifying-glass" wire:click="buildReport" spinner="buildReport" class="btn-primary w-full" />
                        </div>
                        <div class="{{ $activeReport === 'arqueo de caja' ? 'xl:col-span-3' : 'xl:col-span-4' }}">
                            <div class="grid grid-cols-3 gap-2">
                                <x-button label="PDF" icon="o-document-arrow-down" wire:click="export('pdf')" spinner="export" class="btn-outline" />
                                <x-button label="Excel" icon="o-table-cells" wire:click="export('excel')" spinner="export" class="btn-outline btn-success" />
                                <x-button label="Word" icon="o-document-text" wire:click="export('word')" spinner="export" class="btn-outline btn-info" />
                            </div>
                        </div>
                    </div>
                @endif
                @error('endDate')
                    <p class="text-error text-sm mt-3">{{ $message }}</p>
                @enderror
            </x-card>

            @if($reportGenerated)
                <section id="printable-report" class="bg-white border border-base-300 rounded-lg overflow-hidden">
                    <div class="p-5 border-b border-base-300 flex flex-col md:flex-row md:items-center md:justify-between gap-4">
                        <div class="flex items-center gap-4">
                            <div class="w-16 h-16 rounded-lg border border-base-300 flex items-center justify-center bg-base-100">
                                <x-icon name="o-printer" class="w-10 h-10 text-primary" />
                            </div>
                            <div>
                                <h2 class="font-black text-xl text-base-content">Imprenta Minerva</h2>
                                <p class="text-sm text-gray-500">Matagalpa</p>
                                <p class="text-sm font-semibold mt-1">{{ $reportTitle }}</p>
                            </div>
                        </div>

                        <div class="text-sm md:text-right space-y-1">
                            <p><span class="font-bold">Periodo:</span> {{ $reportPeriod }}</p>
                            <p><span class="font-bold">Fecha de generacion:</span> {{ $generatedAt }}</p>
                        </div>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="table table-zebra table-sm w-full">
                            <thead>
                                <tr>
                                    @foreach($reportHeaders as $header)
                                        <th>{{ $header }}</th>
                                    @endforeach
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($reportRows as $row)
                                    <tr @class(['font-bold bg-primary/10' => !empty($row['_is_total'])])>
                                        @foreach($reportHeaders as $header)
                                            <td>{{ $row[$header] }}</td>
                                        @endforeach
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="{{ count($reportHeaders) }}" class="text-center py-8 text-gray-500">
                                            No hay registros para el periodo seleccionado.
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </section>
            @endif
        </div>

        <style>
            @media print {
                body * {
                    visibility: hidden;
                }

                #printable-report, #printable-report * {
                    visibility: visible;
                }

                #printable-report {
                    position: absolute;
                    left: 0;
                    top: 0;
                    width: 100%;
                    border: 0;
                }
            }
        </style>
    @else
    <x-header title="Reportes del Sistema" subtitle="Generacion de reportes operativos y registros maestros" separator>
        <x-slot:actions>
            <x-button label="Panel de Control" icon="o-home" link="/" class="btn-ghost" />
        </x-slot:actions>
    </x-header>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-6">
        <div class="lg:col-span-2">
            <x-card title="Reportes Operativos" subtitle="Selecciona el modulo que deseas generar" icon="o-document-chart-bar" shadow class="bg-base-100">
                <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4">
                    @foreach($operationalReports as $report)
                        <div class="border-l-4 {{ $report['color'] }} bg-base-200/40 rounded-lg p-4 flex flex-col min-h-52">
                            <div class="flex items-start gap-3">
                                <div class="w-11 h-11 rounded-lg bg-base-100 border border-base-300 flex items-center justify-center shrink-0">
                                    <x-icon :name="$report['icon']" class="w-6 h-6 text-primary" />
                                </div>

                                <div>
                                    <h3 class="font-bold text-base-content leading-tight">{{ $report['title'] }}</h3>
                                    <p class="text-sm text-gray-500 mt-1">{{ $report['description'] }}</p>
                                </div>
                            </div>

                            <div class="mt-auto pt-5">
                                <x-button
                                    label="Generar Reporte"
                                    icon="o-document-arrow-down"
                                    wire:click="generate('{{ $report['type'] }}')"
                                    spinner="generate"
                                    class="btn-primary w-full"
                                />
                            </div>
                        </div>
                    @endforeach
                </div>
            </x-card>
        </div>

        <div>
            <x-card title="Centro de Reportes" subtitle="Accesos rapidos del sistema" icon="o-folder-open" shadow class="bg-base-100 border-t-4 border-primary h-full">
                <div class="space-y-4">
                    <div class="stats shadow-sm bg-base-200/60 w-full">
                        <div class="stat">
                            <div class="stat-title text-xs uppercase font-bold text-gray-500">Reportes operativos</div>
                            <div class="stat-value text-3xl text-primary">{{ count($operationalReports) }}</div>
                        </div>
                    </div>

                    <div class="stats shadow-sm bg-base-200/60 w-full">
                        <div class="stat">
                            <div class="stat-title text-xs uppercase font-bold text-gray-500">Registros disponibles</div>
                            <div class="stat-value text-3xl text-secondary">{{ count($registryReports) }}</div>
                        </div>
                    </div>

                    <x-alert
                        title="Listo para generar"
                        description="Usa los botones de cada tarjeta para preparar el reporte correspondiente."
                        icon="o-information-circle"
                        class="alert-info"
                    />
                </div>
            </x-card>
        </div>
    </div>

    <x-card title="Reportes de Registros" subtitle="Listados maestros para consulta y control" icon="o-list-bullet" shadow class="bg-base-100">
        <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-4">
            @foreach($registryReports as $report)
                <div class="bg-base-200/40 border border-base-300 rounded-lg p-4 flex flex-col min-h-48">
                    <div class="flex items-start gap-3">
                        <div class="w-11 h-11 rounded-lg bg-base-100 border border-base-300 flex items-center justify-center shrink-0">
                            <x-icon :name="$report['icon']" class="w-6 h-6 text-secondary" />
                        </div>

                        <div>
                            <h3 class="font-bold text-base-content leading-tight">{{ $report['title'] }}</h3>
                            <p class="text-sm text-gray-500 mt-1">{{ $report['description'] }}</p>
                        </div>
                    </div>

                    <div class="mt-auto pt-5">
                        <x-button
                            label="Generar Reporte"
                            icon="o-document-arrow-down"
                            wire:click="generate('{{ $report['route'] }}')"
                            spinner="generate"
                            class="btn-outline btn-secondary w-full"
                        />
                    </div>
                </div>
            @endforeach
        </div>
    </x-card>
    @endif
</div>
