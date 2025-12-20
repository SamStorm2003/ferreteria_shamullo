<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class MovimientoInventario extends Model
{
    use SoftDeletes;
    protected $table = 'movimiento_inventarios';
    protected $primaryKey = 'idMovimiento';
    public $timestamps = true;

    protected $fillable = [
        'idProducto',
        'idAlmacen',
        'tipo',
        'cantidad',
        'costo_unitario',
        'precio_venta',
        'fecha',
        'idUsuario',
        'motivo',
    ];

    protected $casts = [
        'cantidad' => 'integer',
        'costo_unitario' => 'decimal:2',
        'fecha' => 'datetime',
    ];

    public function producto()
    {
        return $this->belongsTo(Producto::class, 'idProducto', 'idProducto');
    }

    public function almacen()
    {
        return $this->belongsTo(Almacen::class, 'idAlmacen', 'idAlmacen');
    }

    public function usuario()
    {
        return $this->belongsTo(User::class, 'idUsuario', 'id');
    }
}
