<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Marca extends Model
{
    protected $table = 'marcas';

    public $timestamps = false;

    protected $fillable = ['nombre'];

    public function equipos(): HasMany
    {
        return $this->hasMany(Equipo::class, 'marca_id');
    }
}
