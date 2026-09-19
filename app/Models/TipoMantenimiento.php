<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TipoMantenimiento extends Model
{
    protected $table = 'tipos_mantenimiento';

    public $timestamps = false;

    protected $fillable = ['nombre', 'descripcion'];

    public function mantenimientos(): HasMany
    {
        return $this->hasMany(Mantenimiento::class, 'tipo_mantenimiento_id');
    }
}
