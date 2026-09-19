<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Equipo extends Model
{
    protected $table = 'equipos';

    protected $primaryKey = 'bien_id';

    public $incrementing = false;

    public $timestamps = false;

    protected $fillable = ['bien_id', 'tipo_equipo_id', 'marca_id', 'modelo', 'serie'];

    public function bien(): BelongsTo
    {
        return $this->belongsTo(Bien::class, 'bien_id');
    }

    public function tipoEquipo(): BelongsTo
    {
        return $this->belongsTo(TipoEquipo::class, 'tipo_equipo_id');
    }

    public function marca(): BelongsTo
    {
        return $this->belongsTo(Marca::class, 'marca_id');
    }

    public function equipoComputo(): HasOne
    {
        return $this->hasOne(EquipoComputo::class, 'bien_id', 'bien_id');
    }

    public function monitor(): HasOne
    {
        return $this->hasOne(Monitor::class, 'bien_id', 'bien_id');
    }
}
