<?php

use Livewire\Volt\Component;
use Mary\Traits\Toast;
use App\Services\ReportService;
use App\Exports\GenericReportExport;
use Barryvdh\DomPDF\Facade\Pdf;
use Maatwebsite\Excel\Facades\Excel;
use App\Models\Category;

new class extends Component
{
    use Toast;

    // UI y Filtros Generales
    public bool $previewModal = false;
    public string $currentType = '';
    public string $currentTitle = '';

    // Filtros de Fecha (Reportes Operativos)
    public string $startDate = '';
    public string $endDate = '';

    // Filtros de Productos
    public $categories = [];
    public string $filterCategory = '';
    public string $filterStatus = '';
    public string $filterStock = '';

    // Datos de la tabla
    public array $previewHeaders = [];
    public array $previewData = [];
    public array $previewTotals = [];

    // Arreglo de reportes operativos
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
            'title' => 'Reporte de Arqueo de Caja',
            'description' => 'Obtiene el resumen de caja, movimientos y cierre de turno.',
            'icon' => 'o-calculator',
            'color' => 'border-error',
            'type' => 'arqueo de caja',
        ],
        [
            'title' => 'Reporte de Egresos',
            'description' => 'Historial de gastos, vales y salidas de dinero de caja.',
            'icon' => 'o-arrow-trending-down',
            'color' => 'border-error',
            'type' => 'egresos',
        ],
    ];

    // Arreglo de reportes de registros
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

    public function updated($property)
    {
        $filtros = ['startDate', 'endDate', 'filterCategory', 'filterStatus', 'filterStock'];
        if (in_array($property, $filtros)) {
            $this->loadPreviewData();
        }
    }

    public function openPreview(string $type, string $title): void
    {
        $this->currentType = $type;
        $this->currentTitle = $title;

        if ($type === 'productos') {
            $this->startDate = '';
            $this->endDate = '';
            $this->filterCategory = '';
            $this->filterStatus = '';
            $this->filterStock = '';
            $this->categories = Category::orderBy('name')->get();
        } else {
            $this->startDate = '';
            $this->endDate = '';
        }

        $this->loadPreviewData();
        $this->previewModal = true;
    }

    public function loadPreviewData()
    {
        $reportService = app(ReportService::class);
        $result = [];

        switch ($this->currentType) {
            case 'productos':
                $result = $reportService->getProductsReport($this->filterCategory, $this->filterStatus, $this->filterStock);
                break;
            case 'clientes':
                $result = $reportService->getClientsReport();
                break;
            case 'usuarios':
                $result = $reportService->getUsersReport();
                break;
            case 'proveedores':
                $result = $reportService->getProvidersReport();
                break;
            case 'compra':
                $result = $reportService->getPurchasesReport($this->startDate, $this->endDate);
                break;
            case 'venta':
                $result = $reportService->getSalesReport($this->startDate, $this->endDate);
                break;
            case 'pedidos':
                $result = $reportService->getOrdersReport($this->startDate, $this->endDate);
                break;
            case 'devoluciones':
                $result = $reportService->getDevolutionsReport($this->startDate, $this->endDate);
                break;
            case 'arqueo de caja':
                $result = $reportService->getCashRegistersFormattedReport($this->startDate, $this->endDate);
                break;
            case 'egresos':
                $result = $reportService->getExpensesReport($this->startDate, $this->endDate);
                break;
            default:
                $result = ['headers' => [], 'data' => [], 'totals' => []];
                break;
        }

        $this->previewHeaders = array_map(function($header, $index) {
            return ['key' => 'col_'.$index, 'label' => $header];
        }, $result['headers'] ?? [], array_keys($result['headers'] ?? []));

        $this->previewData = array_map(function($row) {
            $formattedRow = [];
            foreach ($row as $index => $value) {
                $formattedRow['col_'.$index] = $value;
            }
            return $formattedRow;
        }, $result['data'] ?? []);

        $this->previewTotals = $result['totals'] ?? [];
    }

    public function export(string $format)
    {
        if (empty($this->previewHeaders) || empty($this->previewData)) {
            $this->warning('No hay datos para exportar con los filtros actuales.');
            return;
        }

        $simpleHeaders = array_column($this->previewHeaders, 'label');
        $simpleData = array_map('array_values', $this->previewData);

        $viewData = [
            'title' => $this->currentTitle,
            'headers' => $simpleHeaders,
            'data' => $simpleData,
            'totals' => $this->previewTotals,
            'date' => date('d/m/Y h:i A')
        ];

        $filename = 'reporte_' . strtolower(str_replace(' ', '_', $this->currentType)) . '_' . date('Y_m_d_His');

        if ($format === 'excel') {
            return Excel::download(new GenericReportExport($viewData), "{$filename}.xlsx");
        }

        if ($format === 'pdf') {
            $pdf = Pdf::loadView('reports.template', $viewData)->setPaper('a4', 'landscape');
            return response()->streamDownload(fn () => print($pdf->output()), "{$filename}.pdf");
        }

        if ($format === 'word') {
            $headers = [
                "Content-type" => "application/vnd.ms-word",
                "Content-Disposition" => "attachment;Filename={$filename}.doc"
            ];
            return response()->streamDownload(fn () => print(view('reports.template', $viewData)->render()), "{$filename}.doc", $headers);
        }
    }
};
?>

<div>
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
                                    wire:click="openPreview('{{ $report['type'] }}', '{{ $report['title'] }}')"
                                    spinner
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
                            wire:click="openPreview('{{ $report['route'] }}', '{{ $report['title'] }}')"
                            spinner
                            class="btn-outline btn-secondary w-full"
                        />
                    </div>
                </div>
            @endforeach
        </div>
    </x-card>

    <x-modal wire:model="previewModal" title="{{ $currentTitle }}" subtitle="Vista Previa de Impresión" separator class="backdrop-blur-sm" box-class="max-w-6xl">

        @if(in_array($currentType, ['compra', 'venta', 'pedidos', 'devoluciones', 'arqueo de caja', 'egresos']))
            <div class="grid grid-cols-2 gap-4 mb-6 bg-base-200 p-4 rounded-lg">
                <x-input label="Fecha de Inicio" type="date" wire:model.live="startDate" icon="o-calendar" />
                <x-input label="Fecha de Fin" type="date" wire:model.live="endDate" icon="o-calendar" />
            </div>
        @endif

        @if($currentType === 'productos')
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6 bg-base-200 p-4 rounded-lg">
                <x-select label="Filtrar por Categoría" wire:model.live="filterCategory" :options="$categories" placeholder="Todas las categorías" option-value="id" option-label="name" icon="o-tag" />
                <x-select label="Estado" wire:model.live="filterStatus" :options="[['id' => 'activos', 'name' => 'Solo Activos'], ['id' => 'inactivos', 'name' => 'Solo Inactivos']]" placeholder="Todos los estados" icon="o-check-circle" />
                <x-select label="Nivel de Stock" wire:model.live="filterStock" :options="[['id' => 'bajo', 'name' => 'Stock Bajo (Crítico)'], ['id' => 'agotado', 'name' => 'Agotados (Stock 0)']]" placeholder="Cualquier cantidad" icon="o-cube" />
            </div>
        @endif

        <div class="max-h-96 overflow-y-auto border border-base-300 rounded-lg bg-base-100">
            @if(count($previewData) > 0)
                <x-table :headers="$previewHeaders" :rows="$previewData" striped class="text-sm" />

                @if(!empty($previewTotals))
<div class="bg-base-200 p-4 font-bold grid gap-2 text-sm border-t border-base-300" style="grid-template-columns: repeat({{ count($previewTotals) }}, minmax(0, 1fr));">                        @foreach($previewTotals as $total)
                            <div class="text-center">{{ $total }}</div>
                        @endforeach
                    </div>
                @endif
            @else
                <div class="p-10 text-center text-gray-400">
                    <x-icon name="o-inbox" class="w-12 h-12 mx-auto mb-3" />
                    <p>No hay datos registrados con los filtros actuales.</p>
                </div>
            @endif
        </div>

        <x-slot:actions>
            <div class="flex justify-between w-full">
                <x-button label="Cerrar" @click="$wire.previewModal = false" class="btn-ghost" />

                <div class="flex gap-2">
                    <x-button label="Word" icon="o-document-text" wire:click="export('word')" class="btn-info text-white" :disabled="count($previewData) == 0" spinner="export" />
                    <x-button label="Excel" icon="o-table-cells" wire:click="export('excel')" class="btn-success text-white" :disabled="count($previewData) == 0" spinner="export" />
                    <x-button label="PDF" icon="o-document-arrow-down" wire:click="export('pdf')" class="btn-error text-white" :disabled="count($previewData) == 0" spinner="export" />
                </div>
            </div>
        </x-slot:actions>
    </x-modal>
</div>
