<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('especialidades')
            ->where('nombre', 'JUA')
            ->update(['nombre' => 'ADMINISTRATIVO']);
    }

    public function down(): void
    {
        DB::table('especialidades')
            ->where('nombre', 'ADMINISTRATIVO')
            ->update(['nombre' => 'JUA']);
    }
};
