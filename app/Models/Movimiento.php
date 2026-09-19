<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Movimiento extends Model
{
    protected $table = 'movimientos';

    public $timestamps = false;

    protected $fillable = [
        'bien_id',
        'ambiente_origen_id',
        'ambiente_destino_id',
        'ordenado_por',
        'ejecutado_por',
        'fecha_movimiento',
        'motivo',
        'observaciones',
    ];

    protected $casts = ['fecha_movimiento' => 'datetime'];

    public function bien(): BelongsTo
    {
        return $this->belongsTo(Bien::class, 'bien_id');
    }

    public function ambienteOrigen(): BelongsTo
    {
        return $this->belongsTo(Ambiente::class, 'ambiente_origen_id');
    }

    public function ambienteDestino(): BelongsTo
    {
        return $this->belongsTo(Ambiente::class, 'ambiente_destino_id');
    }

    public function ordenadoPor(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'ordenado_por');
    }

    public function ejecutadoPor(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'ejecutado_por');
    }
}
