<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TipoMueble extends Model
{
    protected $table = 'tipos_mueble';

    public $timestamps = false;

    protected $fillable = ['nombre'];

    public function muebles(): HasMany
    {
        return $this->hasMany(Mueble::class, 'tipo_mueble_id');
    }
}
