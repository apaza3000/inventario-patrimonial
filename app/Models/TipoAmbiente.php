<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TipoAmbiente extends Model
{
    protected $table = 'tipos_ambiente';

    public $timestamps = false;

    protected $fillable = ['nombre'];

    public function ambientes(): HasMany
    {
        return $this->hasMany(Ambiente::class, 'tipo_ambiente_id');
    }
}
