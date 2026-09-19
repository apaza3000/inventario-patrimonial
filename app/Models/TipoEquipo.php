<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TipoEquipo extends Model
{
    protected $table = 'tipos_equipo';

    public $timestamps = false;

    protected $fillable = ['nombre'];

    public function equipos(): HasMany
    {
        return $this->hasMany(Equipo::class, 'tipo_equipo_id');
    }
}
