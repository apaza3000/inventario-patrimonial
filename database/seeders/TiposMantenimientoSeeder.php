<?php

namespace Database\Seeders;

use App\Models\TipoMantenimiento;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class TiposMantenimientoSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function (): void {
            foreach (['Preventivo', 'Correctivo'] as $nombre) {
                TipoMantenimiento::firstOrCreate(
                    ['nombre' => $nombre],
                    ['descripcion' => null]
                );
            }
        });
    }
}
