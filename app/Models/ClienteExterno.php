<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ClienteExterno extends Model
{
    use SoftDeletes;
    protected $table = 'cliente_externos';

    protected $primaryKey = 'idClienteExterno';

    public $timestamps = true;

    protected $fillable = [
        'nombre',
        'documento_identidad',
        'telefono',
        'correo',
        'direccion',
    ];

    public function ventas()
    {
        return $this->hasMany(Venta::class, 'idClienteExterno', 'idClienteExterno');
    }
}
