<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CondicionBien extends Model
{
    protected $table = 'condiciones_bien';

    public $timestamps = false;

    protected $fillable = ['nombre', 'descripcion'];

    public function bienes(): HasMany
    {
        return $this->hasMany(Bien::class, 'condicion_id');
    }
}
