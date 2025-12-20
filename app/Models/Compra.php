<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Compra extends Model
{
   use SoftDeletes;

    protected $table = 'compras';
    protected $primaryKey = 'idCompra';
    public $timestamps = true;

    protected $fillable = [
        'idProveedor',
        'idUsuario',
        'idAlmacen',
        'fecha',
        'total',
        'estado',
    ];

    protected $casts = [
        'fecha' => 'datetime',
    ];

    public function proveedor()
    {
        return $this->belongsTo(Proveedor::class, 'idProveedor', 'idProveedor');
    }

    public function detalles()
    {
        return $this->hasMany(DetalleCompra::class, 'idCompra', 'idCompra');
    }

    public function usuario()
    {
        return $this->belongsTo(User::class, 'idUsuario', 'id');
    }

    public function almacen()
    {
        return $this->belongsTo(Almacen::class, 'idAlmacen', 'idAlmacen');
    }
}
