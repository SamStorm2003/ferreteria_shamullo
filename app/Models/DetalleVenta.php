<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class DetalleVenta extends Model
{
    use SoftDeletes;
    protected $table = 'detalle_ventas';
    protected $primaryKey = 'idDetalle';

    public $timestamps = true;

    protected $fillable = [
        'idVenta',
        'idProducto',
        'idAlmacen',
        'cantidad',
        'precio_unitario'
    ];

    protected $casts = [
        'cantidad' => 'integer',
        'precio_unitario' => 'decimal:2',
    ];

    public function venta()
    {
        return $this->belongsTo(Venta::class, 'idVenta', 'idVenta');
    }

    public function producto()
    {
        return $this->belongsTo(Producto::class, 'idProducto', 'idProducto');
    }

    public function almacen()
    {
        return $this->belongsTo(Almacen::class, 'idAlmacen', 'idAlmacen');
    }
}
