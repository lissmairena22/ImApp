<?php

namespace App\Exports;

use Illuminate\Contracts\View\View;
use Maatwebsite\Excel\Concerns\FromView;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;

class GenericReportExport implements FromView, ShouldAutoSize
{
    protected array $viewData;

    public function __construct(array $viewData)
    {
        $this->viewData = $viewData;
    }

    public function view(): View
    {
        return view('reports.template', $this->viewData);
    }
}
