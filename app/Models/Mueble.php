<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Mueble extends Model
{
    protected $table = 'muebles';

    protected $primaryKey = 'bien_id';

    public $incrementing = false;

    public $timestamps = false;

    protected $fillable = ['bien_id', 'tipo_mueble_id', 'material', 'color'];

    public function bien(): BelongsTo
    {
        return $this->belongsTo(Bien::class, 'bien_id');
    }

    public function tipoMueble(): BelongsTo
    {
        return $this->belongsTo(TipoMueble::class, 'tipo_mueble_id');
    }
}
