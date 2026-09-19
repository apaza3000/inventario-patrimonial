<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

class Inventario extends Model
{
    protected $table = 'inventarios';

    // La clave real es (bien_id, anio); Eloquent no admite claves compuestas.
    protected $primaryKey = null;

    public $incrementing = false;

    public $timestamps = false;

    protected $fillable = ['inventario', 'anio', 'bien_id'];

    protected $casts = ['anio' => 'integer', 'bien_id' => 'integer'];

    public function bien(): BelongsTo
    {
        return $this->belongsTo(Bien::class, 'bien_id');
    }

    public function save(array $options = [])
    {
        throw new LogicException('Inventario tiene clave primaria compuesta; use una consulta con bien_id y anio para escribir.');
    }

    public function delete()
    {
        throw new LogicException('Inventario tiene clave primaria compuesta; use una consulta con bien_id y anio para eliminar.');
    }
}
