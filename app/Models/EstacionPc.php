<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EstacionPc extends Model
{
    protected $table = 'estaciones_pc';

    public $timestamps = false;

    protected $fillable = ['codigo', 'descripcion', 'activo'];

    protected $casts = ['activo' => 'boolean'];

    public function componentes(): HasMany
    {
        return $this->hasMany(EstacionComponente::class, 'estacion_id');
    }
}
