<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Monitor extends Model
{
    protected $table = 'monitores';

    protected $primaryKey = 'bien_id';

    public $incrementing = false;

    public $timestamps = false;

    protected $fillable = ['bien_id', 'tamanio_pantalla', 'detalle_pantalla'];

    public function equipo(): BelongsTo
    {
        return $this->belongsTo(Equipo::class, 'bien_id', 'bien_id');
    }
}
