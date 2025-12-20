<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Proveedor extends Model
{
    use SoftDeletes;
    protected $table = 'proveedors';
    protected $primaryKey = 'idProveedor';
    public $timestamps = true;

    protected $fillable = [
        'nombre',
        'contacto',
        'telefono',
        'correo',
        'direccion',
        'estado',
    ];

    protected function setEstadoAttribute($value)
    {
        $this->attributes['estado'] = $value ? 'activo' : 'inactivo';
    }
}
