<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Mantenimiento extends Model
{
    protected $table = 'mantenimientos';

    public $timestamps = false;

    protected $fillable = [
        'bien_id',
        'tipo_mantenimiento_id',
        'tecnico_id',
        'fecha_mantenimiento',
        'descripcion',
        'diagnostico',
        'trabajo_realizado',
        'observaciones',
    ];

    protected $casts = ['fecha_mantenimiento' => 'date'];

    public function bien(): BelongsTo
    {
        return $this->belongsTo(Bien::class, 'bien_id');
    }

    public function tipoMantenimiento(): BelongsTo
    {
        return $this->belongsTo(TipoMantenimiento::class, 'tipo_mantenimiento_id');
    }

    public function tecnico(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'tecnico_id');
    }
}
