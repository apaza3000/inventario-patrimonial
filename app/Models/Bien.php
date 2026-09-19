<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Bien extends Model
{
    protected $table = 'bienes';

    public $timestamps = false;

    protected $fillable = [
        'cbi',
        'descripcion',
        'estado_id',
        'condicion_id',
        'ambiente_id',
        'observaciones',
        'activo',
        'fecha_registro',
    ];

    protected $casts = [
        'activo' => 'boolean',
        'fecha_registro' => 'datetime',
    ];

    public function ambiente(): BelongsTo
    {
        return $this->belongsTo(Ambiente::class, 'ambiente_id');
    }

    public function estado(): BelongsTo
    {
        return $this->belongsTo(EstadoBien::class, 'estado_id');
    }

    public function condicion(): BelongsTo
    {
        return $this->belongsTo(CondicionBien::class, 'condicion_id');
    }
}
