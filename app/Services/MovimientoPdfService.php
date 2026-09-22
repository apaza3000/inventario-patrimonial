<?php

namespace App\Services;

use App\Models\Movimiento;
use Barryvdh\DomPDF\Facade\Pdf;

class MovimientoPdfService
{
    public function render(Movimiento $movimiento): string
    {
        return Pdf::loadView('pdf.movimiento', [
            'movimiento' => $movimiento,
        ])->setPaper('a4')->output();
    }
}
