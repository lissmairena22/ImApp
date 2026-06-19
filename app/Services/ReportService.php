<?php

namespace App\Services;

use App\Models\Product;
use Illuminate\Support\Facades\DB;

class ReportService
{
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

    /**
     * 2. Reporte con filtros y totales: Arqueo de Caja
     */
   public function getCashRegistersReport(string $startDate, string $endDate): array
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

        $registers = DB::table('cash_registers')
            ->leftJoin('users', 'cash_registers.user_id', '=', 'users.id')
            ->select('cash_registers.*', 'users.name as user_name')
            ->whereDate('cash_registers.created_at', '>=', $startDate)
            ->whereDate('cash_registers.created_at', '<=', $endDate)
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
                number_format($totals['final'], 2)
            ]
        ];
    }

    /**
     * 3. Reporte con filtros y totales: Egresos (Agrupados por concepto)
     */
    public function getExpensesReport(string $startDate, string $endDate): array
    {
        $headers = ['CONCEPTO / DESCRIPCIÓN', 'CANTIDAD DE VECES', 'MONTO TOTAL (C$)'];
        $data = [];
        $totalExpenses = 0;

        $expenses = DB::table('cash_movements')
            ->select(
                'concept',
                DB::raw('COUNT(id) as total_count'),
                DB::raw('SUM(amount) as total_amount')
            )
            ->where('type', 'Egreso')
            ->whereDate('movement_date', '>=', $startDate)
            ->whereDate('movement_date', '<=', $endDate)
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
}
