<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Ambiente extends Model
{
    protected $table = 'ambientes';

    public $timestamps = false;

    protected $fillable = [
        'nombre',
        'sede_id',
        'tipo_ambiente_id',
        'especialidad_id',
        'area',
        'ancho',
        'largo',
        'activo',
    ];

    protected $casts = [
        'area' => 'decimal:2',
        'ancho' => 'decimal:2',
        'largo' => 'decimal:2',
        'activo' => 'boolean',
    ];

    public function sede(): BelongsTo
    {
        return $this->belongsTo(Sede::class, 'sede_id');
    }

    public function tipoAmbiente(): BelongsTo
    {
        return $this->belongsTo(TipoAmbiente::class, 'tipo_ambiente_id');
    }

    public function especialidad(): BelongsTo
    {
        return $this->belongsTo(Especialidad::class, 'especialidad_id');
    }

    public function bienes(): HasMany
    {
        return $this->hasMany(Bien::class, 'ambiente_id');
    }
}
