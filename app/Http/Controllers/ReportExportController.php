<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Product;
use Illuminate\Support\Facades\DB;
use Barryvdh\DomPDF\Facade\Pdf;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Contracts\View\View;
use Maatwebsite\Excel\Concerns\FromView;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;

class ReportExportController extends Controller
{
    public function export(Request $request)
    {
        $type = $request->query('type');
        $format = $request->query('format');
        $start = $request->query('start');
        $end = $request->query('end');

        $data = [];
        $tableHeaders = [];
        $title = 'Reporte de Sistema';
        $filename = 'reporte_' . date('Y_m_d_His');

        // 1. Extraer los datos según el tipo de reporte
        if ($type === 'productos') {
            $title = 'Catálogo de Productos e Inventario';
            $filename = 'reporte_productos_' . date('Y_m_d');
            $tableHeaders = ['CÓDIGO', 'NOMBRE', 'STOCK', 'PRECIO VENTA (C$)'];

            $products = Product::orderBy('name')->get();
            foreach ($products as $p) {
                $data[] = [
                    'INS-' . str_pad($p->id, 3, '0', STR_PAD_LEFT),
                    $p->name,
                    $p->stock,
                    number_format($p->sale_price, 2)
                ];
            }
        } elseif ($type === 'arqueo de caja') {
            $title = 'Reporte de Arqueos de Caja (' . date('d/m/Y', strtotime($start)) . ' al ' . date('d/m/Y', strtotime($end)) . ')';
            $filename = 'reporte_arqueo_' . date('Y_m_d');
            $tableHeaders = ['FECHA', 'ESTADO', 'VENTAS REALES (C$)', 'DESCUADRE (C$)'];

            $registers = DB::table('cash_registers')
                ->whereDate('created_at', '>=', $start)
                ->whereDate('created_at', '<=', $end)
                ->orderBy('created_at', 'desc')
                ->get();

            foreach ($registers as $r) {
                $data[] = [
                    date('d/m/Y h:i A', strtotime($r->created_at)),
                    ucfirst($r->status),
                    number_format($r->cash_sales ?? 0, 2),
                    number_format($r->difference ?? 0, 2)
                ];
            }
        }

        $viewData = [
            'title' => $title,
            'headers' => $tableHeaders,
            'data' => $data,
            'date' => date('d/m/Y h:i A')
        ];

        if ($format === 'excel') {
            return Excel::download(new class($viewData) implements FromView, ShouldAutoSize {
                public $viewData;
                public function __construct($viewData) { $this->viewData = $viewData; }
                public function view(): View { return view('reports.template', $this->viewData); }
            }, "{$filename}.xlsx");
        }

        // --- FORMATO PDF ---
        if ($format === 'pdf') {
            $pdf = Pdf::loadView('reports.template', $viewData);
            return $pdf->download("{$filename}.pdf");
        }

        // --- FORMATO WORD ---
        if ($format === 'word') {
            $headers = [
                "Content-type" => "application/vnd.ms-word",
                "Content-Disposition" => "attachment;Filename={$filename}.doc"
            ];
            return response()->make(view('reports.template', $viewData)->render(), 200, $headers);
        }

        return back()->with('error', 'Formato no soportado');
    }
}
