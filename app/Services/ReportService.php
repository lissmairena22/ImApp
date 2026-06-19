<?php

namespace App\Services;

use App\Models\Product;
use Illuminate\Support\Facades\DB;

class ReportService
{
    private const CASH_REPORT_EXCHANGE_RATE = 36.5;

    private function applyDateRange($query, string $column, string $startDate = '', string $endDate = '')
    {
        if ($startDate !== '') {
            $query->whereDate($column, '>=', $startDate);
        }

        if ($endDate !== '') {
            $query->whereDate($column, '<=', $endDate);
        }

        return $query;
    }

    /**
     * 1. Reporte de Productos (Con Filtros Dinámicos)
     */
    public function getProductsReport(string $categoryId = '', string $status = '', string $stock = ''): array
    {
        $headers = ['CÓDIGO', 'NOMBRE', 'CATEGORÍA', 'STOCK', 'PRECIO (C$)', 'ESTADO'];
        $data = [];

        $query = Product::with('category')->orderBy('name');

        if ($categoryId !== '') {
            $query->where('category_id', $categoryId);
        }

        if ($status === 'activos') {
            $query->where('is_active', true);
        } elseif ($status === 'inactivos') {
            $query->where('is_active', false);
        }

        if ($stock === 'bajo') {
            $query->whereColumn('stock', '<=', 'min_stock')->where('stock', '>', 0);
        } elseif ($stock === 'agotado') {
            $query->where('stock', '<=', 0);
        }

        $products = $query->get();

        foreach ($products as $p) {
            $data[] = [
                'INS-' . str_pad($p->id, 3, '0', STR_PAD_LEFT),
                $p->name,
                $p->category->name ?? 'N/A',
                $p->stock,
                number_format($p->sale_price, 2),
                $p->is_active ? 'Activo' : 'Inactivo'
            ];
        }

        // 👇 ESTE ES EL RETURN QUE HACÍA FALTA 👇
        return [
            'headers' => $headers,
            'data' => $data,
            'totals' => []
        ];
    }

    public function getClientsReport(): array
    {
        $headers = ['#', 'Cliente', 'DNI/RUC', 'Telefono', 'Correo', 'Direccion', 'Estado'];
        $data = [];

        $clients = DB::table('clients')
            ->whereNull('deleted_at')
            ->orderBy('name')
            ->get();

        foreach ($clients as $index => $client) {
            $data[] = [
                $index + 1,
                $client->name,
                $client->dni ?? 'Sin registro',
                $client->phone ?? 'Sin telefono',
                $client->email ?? 'Sin correo',
                $client->address ?? 'Sin direccion',
                $client->is_active ? 'Activo' : 'Inactivo',
            ];
        }

        return [
            'headers' => $headers,
            'data' => $data,
            'totals' => [],
        ];
    }

    public function getUsersReport(): array
    {
        $headers = ['#', 'Nombre', 'Usuario', 'Rol', 'Estado'];
        $data = [];

        $users = DB::table('users')
            ->orderBy('name')
            ->get();

        foreach ($users as $index => $user) {
            $data[] = [
                $index + 1,
                $user->name,
                $user->username,
                $user->role,
                $user->status,
            ];
        }

        return [
            'headers' => $headers,
            'data' => $data,
            'totals' => [],
        ];
    }

    public function getProvidersReport(): array
    {
        $headers = ['#', 'Proveedor', 'RUC', 'Telefono', 'Correo', 'Direccion', 'Estado'];
        $data = [];

        $providers = DB::table('providers')
            ->orderBy('company_name')
            ->get();

        foreach ($providers as $index => $provider) {
            $data[] = [
                $index + 1,
                $provider->company_name,
                $provider->ruc ?? 'Sin registro',
                $provider->phone ?? 'Sin telefono',
                $provider->email ?? 'Sin correo',
                $provider->address ?? 'Sin direccion',
                $provider->is_active ? 'Activo' : 'Inactivo',
            ];
        }

        return [
            'headers' => $headers,
            'data' => $data,
            'totals' => [],
        ];
    }

    public function getSalesReport(string $startDate = '', string $endDate = ''): array
    {
        $headers = ['#', 'Fecha', 'No.Factura', 'Cliente', 'Producto', 'Categoria', 'Unidad/Medida', 'Precio de venta', 'Cantidad', 'Subtotal', 'Total'];
        $data = [];
        $totalSales = 0;
        $countedInvoices = [];
        $lineNumbers = [];

        $invoiceItems = $this->applyDateRange(
            DB::table('invoices')
                ->join('invoice_items', 'invoices.id', '=', 'invoice_items.invoice_id')
                ->leftJoin('clients', 'invoices.client_id', '=', 'clients.id')
                ->leftJoin('products', 'invoice_items.product_id', '=', 'products.id')
                ->leftJoin('categories', 'products.category_id', '=', 'categories.id')
                ->leftJoin('units', 'products.unit_id', '=', 'units.id')
                ->select(
                    'invoices.id as invoice_id',
                    'invoices.invoice_date',
                    'invoices.invoice_number',
                    'invoices.total as invoice_total',
                    'clients.name as client_name',
                    'invoice_items.description',
                    'invoice_items.quantity',
                    'invoice_items.unit_price',
                    'invoice_items.subtotal',
                    'categories.name as category_name',
                    'units.name as unit_name'
                )
                ->where('invoices.status', '!=', 'Anulada'),
            'invoices.invoice_date',
            $startDate,
            $endDate
        )
            ->orderBy('invoices.invoice_date')
            ->orderBy('invoices.id')
            ->get();

        foreach ($invoiceItems as $item) {
            $lineNumbers[$item->invoice_id] = ($lineNumbers[$item->invoice_id] ?? 0) + 1;

            $data[] = [
                $item->invoice_id . '-' . $lineNumbers[$item->invoice_id],
                date('d/m/Y', strtotime($item->invoice_date)),
                $item->invoice_number,
                $item->client_name ?? 'Cliente General',
                $item->description,
                $item->category_name ?? 'Sin categoria',
                $item->unit_name ?? 'Und',
                'C$ ' . number_format((float) $item->unit_price, 2),
                number_format((float) $item->quantity, 2),
                'C$ ' . number_format((float) $item->subtotal, 2),
                'C$ ' . number_format((float) $item->invoice_total, 2),
            ];

            if (!isset($countedInvoices[$item->invoice_id])) {
                $countedInvoices[$item->invoice_id] = true;
                $totalSales += (float) $item->invoice_total;
            }
        }

        return [
            'headers' => $headers,
            'data' => $data,
            'totals' => [
                '',
                '',
                '',
                '',
                '',
                '',
                '',
                '',
                '',
                'Total Ventas',
                'C$ ' . number_format($totalSales, 2),
            ],
        ];
    }

    public function getPurchasesReport(string $startDate = '', string $endDate = ''): array
    {
        $headers = ['#', 'Fecha', 'No factura', 'Proveedor', 'Producto', 'Categoria', 'Unidad de medida', 'Precio de compra', 'Cantidad', 'Total'];
        $data = [];
        $totalPurchases = 0;
        $countedPurchases = [];
        $lineNumbers = [];

        $purchaseItems = $this->applyDateRange(
            DB::table('purchases')
                ->join('purchase_items', 'purchases.id', '=', 'purchase_items.purchase_id')
                ->leftJoin('providers', 'purchases.provider_id', '=', 'providers.id')
                ->leftJoin('products', 'purchase_items.product_id', '=', 'products.id')
                ->leftJoin('categories', 'products.category_id', '=', 'categories.id')
                ->leftJoin('units', 'products.unit_id', '=', 'units.id')
                ->select(
                    'purchases.id as purchase_id',
                    'purchases.purchase_date',
                    'purchases.provider_invoice_number',
                    'purchases.total',
                    'providers.company_name as provider_name',
                    'products.name as product_name',
                    'categories.name as category_name',
                    'units.name as unit_name',
                    'purchase_items.quantity',
                    'purchase_items.cost_price',
                    'purchase_items.subtotal'
                ),
            'purchases.purchase_date',
            $startDate,
            $endDate
        )
            ->orderBy('purchases.purchase_date')
            ->orderBy('purchases.id')
            ->get();

        foreach ($purchaseItems as $item) {
            $lineNumbers[$item->purchase_id] = ($lineNumbers[$item->purchase_id] ?? 0) + 1;

            $data[] = [
                $item->purchase_id . '-' . $lineNumbers[$item->purchase_id],
                date('d/m/Y', strtotime($item->purchase_date)),
                $item->provider_invoice_number ?? 'Sin factura',
                $item->provider_name ?? 'Sin proveedor',
                $item->product_name ?? 'Producto no disponible',
                $item->category_name ?? 'Sin categoria',
                $item->unit_name ?? 'Und',
                'C$ ' . number_format((float) $item->cost_price, 2),
                number_format((float) $item->quantity, 2),
                'C$ ' . number_format((float) $item->subtotal, 2),
            ];

            if (!isset($countedPurchases[$item->purchase_id])) {
                $countedPurchases[$item->purchase_id] = true;
                $totalPurchases += (float) $item->total;
            }
        }

        return [
            'headers' => $headers,
            'data' => $data,
            'totals' => [
                '',
                '',
                '',
                '',
                '',
                '',
                '',
                '',
                'Total Compras',
                'C$ ' . number_format($totalPurchases, 2),
            ],
        ];
    }

    public function getOrdersReport(string $startDate = '', string $endDate = ''): array
    {
        $headers = ['#', 'Fecha', 'Orden', 'Factura', 'Cliente', 'Producto', 'Categoria', 'Unidad/Medida', 'Precio', 'Cantidad', 'Subtotal', 'Total pedido', 'Estado'];
        $data = [];
        $lineNumbers = [];

        $orderItems = $this->applyDateRange(
            DB::table('orders')
                ->join('order_items', 'orders.id', '=', 'order_items.order_id')
                ->leftJoin('clients', 'orders.client_id', '=', 'clients.id')
                ->leftJoin('invoices', 'orders.id', '=', 'invoices.order_id')
                ->leftJoin('products', 'order_items.product_id', '=', 'products.id')
                ->leftJoin('categories', 'products.category_id', '=', 'categories.id')
                ->leftJoin('units', 'products.unit_id', '=', 'units.id')
                ->select(
                    'orders.id as order_id',
                    'orders.order_date',
                    'orders.estimated_price',
                    'orders.status',
                    'clients.name as client_name',
                    'invoices.invoice_number',
                    'order_items.description',
                    'order_items.quantity',
                    'order_items.unit_price',
                    'order_items.subtotal',
                    'categories.name as category_name',
                    'units.name as unit_name'
                ),
            'orders.order_date',
            $startDate,
            $endDate
        )
            ->orderBy('orders.order_date')
            ->orderBy('orders.id')
            ->get();

        foreach ($orderItems as $item) {
            $lineNumbers[$item->order_id] = ($lineNumbers[$item->order_id] ?? 0) + 1;

            $data[] = [
                $item->order_id . '-' . $lineNumbers[$item->order_id],
                date('d/m/Y', strtotime($item->order_date)),
                'ORD-' . str_pad($item->order_id, 3, '0', STR_PAD_LEFT),
                $item->invoice_number ?? 'Sin factura',
                $item->client_name ?? 'Sin cliente',
                $item->description,
                $item->category_name ?? 'Sin categoria',
                $item->unit_name ?? 'Und',
                'C$ ' . number_format((float) $item->unit_price, 2),
                number_format((float) $item->quantity, 2),
                'C$ ' . number_format((float) $item->subtotal, 2),
                'C$ ' . number_format((float) $item->estimated_price, 2),
                $item->status,
            ];
        }

        return [
            'headers' => $headers,
            'data' => $data,
            'totals' => [],
        ];
    }

    public function getDevolutionsReport(string $startDate = '', string $endDate = ''): array
    {
        $headers = ['#', 'Fecha', 'No factura', 'Cliente', 'Producto', 'Categoria', 'Unidad de medida', 'Motivo de devolucion', 'Precio de venta', 'Cantidad devuelta', 'Subtotal', 'Total'];
        $data = [];
        $totalReturned = 0;
        $countedDevolutions = [];
        $lineNumbers = [];

        $devolutionItems = $this->applyDateRange(
            DB::table('devolutions')
                ->join('devolution_items', 'devolutions.id', '=', 'devolution_items.devolution_id')
                ->leftJoin('invoices', 'devolutions.invoice_id', '=', 'invoices.id')
                ->leftJoin('clients', 'invoices.client_id', '=', 'clients.id')
                ->leftJoin('products', 'devolution_items.product_id', '=', 'products.id')
                ->leftJoin('categories', 'products.category_id', '=', 'categories.id')
                ->leftJoin('units', 'products.unit_id', '=', 'units.id')
                ->select(
                    'devolutions.id as devolution_id',
                    'devolutions.devolution_date',
                    'devolutions.reason',
                    'devolutions.amount_returned as devolution_total',
                    'invoices.invoice_number',
                    'clients.name as client_name',
                    'devolution_items.description',
                    'devolution_items.quantity',
                    'devolution_items.unit_price',
                    'devolution_items.amount_returned',
                    'categories.name as category_name',
                    'units.name as unit_name'
                ),
            'devolutions.devolution_date',
            $startDate,
            $endDate
        )
            ->orderBy('devolutions.devolution_date')
            ->orderBy('devolutions.id')
            ->get();

        foreach ($devolutionItems as $item) {
            $lineNumbers[$item->devolution_id] = ($lineNumbers[$item->devolution_id] ?? 0) + 1;

            $data[] = [
                $item->devolution_id . '-' . $lineNumbers[$item->devolution_id],
                date('d/m/Y', strtotime($item->devolution_date)),
                $item->invoice_number ?? 'Sin factura',
                $item->client_name ?? 'Sin cliente',
                $item->description,
                $item->category_name ?? 'Sin categoria',
                $item->unit_name ?? 'Und',
                $item->reason,
                'C$ ' . number_format((float) $item->unit_price, 2),
                number_format((float) $item->quantity, 2),
                'C$ ' . number_format((float) $item->amount_returned, 2),
                'C$ ' . number_format((float) $item->devolution_total, 2),
            ];

            if (!isset($countedDevolutions[$item->devolution_id])) {
                $countedDevolutions[$item->devolution_id] = true;
                $totalReturned += (float) $item->devolution_total;
            }
        }

        return [
            'headers' => $headers,
            'data' => $data,
            'totals' => [
                '',
                '',
                '',
                '',
                '',
                '',
                '',
                '',
                '',
                '',
                'Total devoluciones sobre venta',
                'C$ ' . number_format($totalReturned, 2),
            ],
        ];
    }

    public function getCashRegistersFormattedReport(string $startDate = '', string $endDate = ''): array
    {
        $headers = [
            'Fecha',
            'Nombre del cajero',
            'Total vendido C$',
            'TOTAL vendido $',
            'Total en caja C$',
            'Total en caja $',
            'Sobrante C$',
            'Sobrante $',
            'Faltante C$',
            'Faltante $',
        ];
        $data = [];

        $totals = [
            'cash_sales' => 0,
            'physical_balance' => 0,
            'surplus' => 0,
            'shortage' => 0,
        ];

        $registers = $this->applyDateRange(
            DB::table('cash_registers')
                ->leftJoin('users', 'cash_registers.user_id', '=', 'users.id')
                ->select('cash_registers.*', 'users.name as user_name')
                ->where('cash_registers.status', 'Cerrada'),
            'cash_registers.closed_at',
            $startDate,
            $endDate
        )
            ->orderBy('cash_registers.closed_at')
            ->get();

        foreach ($registers as $register) {
            $cashSales = (float) $register->cash_sales;
            $physicalBalance = (float) $register->physical_balance;
            $difference = (float) $register->difference;
            $surplus = max($difference, 0);
            $shortage = abs(min($difference, 0));

            $data[] = [
                $register->closed_at ? date('d/m/Y', strtotime($register->closed_at)) : 'Sin cierre',
                $register->user_name ?? 'Sin cajero',
                'C$ ' . number_format($cashSales, 2),
                '$ ' . number_format($this->toUsd($cashSales), 2),
                'C$ ' . number_format($physicalBalance, 2),
                '$ ' . number_format($this->toUsd($physicalBalance), 2),
                'C$ ' . number_format($surplus, 2),
                '$ ' . number_format($this->toUsd($surplus), 2),
                'C$ ' . number_format($shortage, 2),
                '$ ' . number_format($this->toUsd($shortage), 2),
            ];

            $totals['cash_sales'] += $cashSales;
            $totals['physical_balance'] += $physicalBalance;
            $totals['surplus'] += $surplus;
            $totals['shortage'] += $shortage;
        }

        return [
            'headers' => $headers,
            'data' => $data,
            'totals' => [
                'Totales',
                '',
                'C$ ' . number_format($totals['cash_sales'], 2),
                '$ ' . number_format($this->toUsd($totals['cash_sales']), 2),
                'C$ ' . number_format($totals['physical_balance'], 2),
                '$ ' . number_format($this->toUsd($totals['physical_balance']), 2),
                'C$ ' . number_format($totals['surplus'], 2),
                '$ ' . number_format($this->toUsd($totals['surplus']), 2),
                'C$ ' . number_format($totals['shortage'], 2),
                '$ ' . number_format($this->toUsd($totals['shortage']), 2),
            ],
        ];
    }

    /**
     * 2. Reporte con filtros y totales: Arqueo de Caja
     */
   public function getCashRegistersReport(string $startDate = '', string $endDate = ''): array
    {
        // Añadimos la nueva columna al final
        $headers = ['N° CAJA', 'FECHA', 'USUARIO', 'SALDO INICIAL', 'INGRESOS', 'EGRESOS', 'SALDO FINAL', 'DESCUADRE (C$)'];
        $data = [];

        $totals = [
            'initial' => 0,
            'incomes' => 0,
            'expenses' => 0,
            'final' => 0,
            'difference' => 0 // Nuevo acumulador para faltantes/sobrantes
        ];

        $registers = $this->applyDateRange(
            DB::table('cash_registers')
                ->leftJoin('users', 'cash_registers.user_id', '=', 'users.id')
                ->select('cash_registers.*', 'users.name as user_name'),
            'cash_registers.created_at',
            $startDate,
            $endDate
        )
            ->orderBy('cash_registers.created_at', 'desc')
            ->get();

        foreach ($registers as $r) {
            $initial = (float) $r->initial_balance;
            $incomes = (float) $r->cash_sales;
            $expenses = (float) $r->cash_out;
            $final = (float) $r->system_balance;
            $difference = (float) $r->difference; // Extraemos el descuadre real

            $data[] = [
                'CJ-' . str_pad($r->id, 4, '0', STR_PAD_LEFT),
                date('d/m/Y h:i A', strtotime($r->created_at)),
                $r->user_name ?? 'Sistema',
                number_format($initial, 2),
                number_format($incomes, 2),
                number_format($expenses, 2),
                number_format($final, 2),
                number_format($difference, 2) // Lo agregamos a la fila
            ];

            // Acumulamos los totales
            $totals['initial'] += $initial;
            $totals['incomes'] += $incomes;
            $totals['expenses'] += $expenses;
            $totals['final'] += $final;
            $totals['difference'] += $difference;
        }

        return [
            'headers' => $headers,
            'data' => $data,
            'totals' => [
                'TOTALES',
                '',
                '',
                number_format($totals['initial'], 2),
                number_format($totals['incomes'], 2),
                number_format($totals['expenses'], 2),
                number_format($totals['final'], 2),
                number_format($totals['difference'], 2) // Total del descuadre al final
            ]
        ];

    }

    /**
     * 3. Reporte con filtros y totales: Egresos (Agrupados por concepto)
     */
    public function getExpensesReport(string $startDate = '', string $endDate = ''): array
    {
        $headers = ['CONCEPTO / DESCRIPCIÓN', 'CANTIDAD DE VECES', 'MONTO TOTAL (C$)'];
        $data = [];
        $totalExpenses = 0;

        $expenses = $this->applyDateRange(
            DB::table('cash_movements')
                ->select(
                    'concept',
                    DB::raw('COUNT(id) as total_count'),
                    DB::raw('SUM(amount) as total_amount')
                )
                ->where('type', 'Egreso'),
            'movement_date',
            $startDate,
            $endDate
        )
            ->groupBy('concept')
            ->orderBy('total_amount', 'desc')
            ->get();

        foreach ($expenses as $e) {
            $monto = (float) $e->total_amount;

            $data[] = [
                $e->concept,
                $e->total_count . ' transacciones',
                number_format($monto, 2)
            ];

            $totalExpenses += $monto;
        }

        return [
            'headers' => $headers,
            'data' => $data,
            'totals' => [
                'TOTAL GENERAL DE EGRESOS',
                '',
                number_format($totalExpenses, 2)
            ]
        ];
    }

    private function toUsd(float $amount): float
    {
        return self::CASH_REPORT_EXCHANGE_RATE > 0 ? $amount / self::CASH_REPORT_EXCHANGE_RATE : 0;
    }
}
