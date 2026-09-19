<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EstacionComponente extends Model
{
    protected $table = 'estacion_componentes';

    public $timestamps = false;

    protected $fillable = ['estacion_id', 'bien_id', 'fecha_asignacion', 'fecha_retiro', 'activo'];

    protected $casts = [
        'fecha_asignacion' => 'date',
        'fecha_retiro' => 'date',
        'activo' => 'boolean',
    ];

    public function estacion(): BelongsTo
    {
        return $this->belongsTo(EstacionPc::class, 'estacion_id');
    }

    public function bien(): BelongsTo
    {
        return $this->belongsTo(Bien::class, 'bien_id');
    }
}
