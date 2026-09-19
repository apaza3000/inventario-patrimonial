<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EquipoComputo extends Model
{
    protected $table = 'equipos_computo';

    protected $primaryKey = 'bien_id';

    public $incrementing = false;

    public $timestamps = false;

    protected $fillable = ['bien_id', 'procesador', 'ram', 'almacenamiento', 'detalle_adicional'];

    public function equipo(): BelongsTo
    {
        return $this->belongsTo(Equipo::class, 'bien_id', 'bien_id');
    }
}
