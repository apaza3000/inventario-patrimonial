<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Movimiento extends Model
{
    public const ESTADO_PENDIENTE_FIRMA = 'pendiente_firma';

    public const ESTADO_FINALIZADO = 'finalizado';

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
        'estado',
        'pdf_generado_ruta',
        'pdf_firmado_ruta',
        'fecha_firma',
    ];

    protected $hidden = ['pdf_generado_ruta', 'pdf_firmado_ruta'];

    protected $casts = [
        'fecha_movimiento' => 'datetime',
        'fecha_firma' => 'datetime',
    ];

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
