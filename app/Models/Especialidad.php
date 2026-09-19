<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Especialidad extends Model
{
    protected $table = 'especialidades';

    public $timestamps = false;

    protected $fillable = ['nombre'];

    public function ambientes(): HasMany
    {
        return $this->hasMany(Ambiente::class, 'especialidad_id');
    }
}
