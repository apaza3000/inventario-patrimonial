<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;

class Usuario extends Authenticatable
{
    protected $table = 'usuarios';

    protected $rememberTokenName = null;

    public $timestamps = false;

    protected $fillable = [
        'nombres',
        'apellidos',
        'correo',
        'password_hash',
        'rol_id',
        'activo',
        'fecha_registro',
    ];

    protected $hidden = ['password_hash'];

    protected $casts = [
        'activo' => 'boolean',
        'fecha_registro' => 'datetime',
    ];

    public function getAuthPassword(): string
    {
        return $this->password_hash;
    }

    public function rol(): BelongsTo
    {
        return $this->belongsTo(Rol::class, 'rol_id');
    }
}
