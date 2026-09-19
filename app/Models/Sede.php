<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Sede extends Model
{
    protected $table = 'sedes';

    public $timestamps = false;

    protected $fillable = ['nombre', 'direccion', 'activo'];

    protected $casts = ['activo' => 'boolean'];

    public function ambientes(): HasMany
    {
        return $this->hasMany(Ambiente::class, 'sede_id');
    }
}
