<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('usuarios', function (Blueprint $table) {
            $table->integer('especialidad_id')->nullable()->after('rol_id')->index();
            $table->foreign('especialidad_id')
                ->references('id')
                ->on('especialidades')
                ->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('usuarios', function (Blueprint $table) {
            $table->dropForeign(['especialidad_id']);
            $table->dropIndex(['especialidad_id']);
            $table->dropColumn('especialidad_id');
        });
    }
};
