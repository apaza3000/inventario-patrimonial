<?php

namespace Database\Seeders;

use App\Models\Rol;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class RolesInicialesSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function (): void {
            foreach (['asistente', 'director', 'coordinador', 'administrador', 'superadmin'] as $nombre) {
                Rol::firstOrCreate(
                    ['nombre' => $nombre],
                    ['descripcion' => null]
                );
            }
        });
    }
}
