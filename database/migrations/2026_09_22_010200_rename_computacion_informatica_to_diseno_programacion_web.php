<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const ESPECIALIDAD_ID = 1;

    private const NOMBRE_ANTERIOR = 'COMPUTACION E INFORMATICA';

    private const NOMBRE_NUEVO = 'DISEÑO Y PROGRAMACIÓN WEB';

    public function up(): void
    {
        DB::transaction(function () {
            $registro = DB::table('especialidades')
                ->where('id', self::ESPECIALIDAD_ID)
                ->lockForUpdate()
                ->first();

            if ($registro === null) {
                throw new \RuntimeException('No existe la especialidad con ID 1.');
            }

            $duplicado = DB::table('especialidades')
                ->where('nombre', self::NOMBRE_NUEVO)
                ->where('id', '!=', self::ESPECIALIDAD_ID)
                ->exists();

            if ($duplicado) {
                throw new \RuntimeException('Ya existe otra especialidad llamada DISEÑO Y PROGRAMACIÓN WEB.');
            }

            if ($registro->nombre === self::NOMBRE_NUEVO) {
                return;
            }

            if ($registro->nombre !== self::NOMBRE_ANTERIOR) {
                throw new \RuntimeException('La especialidad con ID 1 no tiene el nombre esperado para realizar el cambio.');
            }

            DB::table('especialidades')
                ->where('id', self::ESPECIALIDAD_ID)
                ->update(['nombre' => self::NOMBRE_NUEVO]);
        });
    }

    public function down(): void
    {
        DB::transaction(function () {
            $registro = DB::table('especialidades')
                ->where('id', self::ESPECIALIDAD_ID)
                ->lockForUpdate()
                ->first();

            if ($registro === null) {
                throw new \RuntimeException('No existe la especialidad con ID 1.');
            }

            $duplicado = DB::table('especialidades')
                ->where('nombre', self::NOMBRE_ANTERIOR)
                ->where('id', '!=', self::ESPECIALIDAD_ID)
                ->exists();

            if ($duplicado) {
                throw new \RuntimeException('Ya existe otra especialidad llamada COMPUTACION E INFORMATICA.');
            }

            if ($registro->nombre === self::NOMBRE_ANTERIOR) {
                return;
            }

            if ($registro->nombre !== self::NOMBRE_NUEVO) {
                throw new \RuntimeException('La especialidad con ID 1 no tiene el nombre esperado para revertir el cambio.');
            }

            DB::table('especialidades')
                ->where('id', self::ESPECIALIDAD_ID)
                ->update(['nombre' => self::NOMBRE_ANTERIOR]);
        });
    }
};
