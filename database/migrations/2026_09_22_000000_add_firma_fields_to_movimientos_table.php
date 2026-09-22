<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE movimientos MODIFY ordenado_por INT NOT NULL, MODIFY ejecutado_por INT NOT NULL');

        Schema::table('movimientos', function (Blueprint $table) {
            $table->enum('estado', ['pendiente_firma', 'finalizado'])
                ->default('pendiente_firma')
                ->after('observaciones');
            $table->string('pdf_generado_ruta', 255)->nullable()->after('estado');
            $table->string('pdf_firmado_ruta', 255)->nullable()->after('pdf_generado_ruta');
            $table->dateTime('fecha_firma')->nullable()->after('pdf_firmado_ruta');
            $table->index(['bien_id', 'estado']);
        });
    }

    public function down(): void
    {
        Schema::table('movimientos', function (Blueprint $table) {
            $table->dropIndex(['bien_id', 'estado']);
            $table->dropColumn([
                'estado',
                'pdf_generado_ruta',
                'pdf_firmado_ruta',
                'fecha_firma',
            ]);
        });

        DB::statement('ALTER TABLE movimientos MODIFY ordenado_por INT NULL, MODIFY ejecutado_por INT NULL');
    }
};
