<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class DetalleCompra extends Model
{
    use SoftDeletes;
    protected $table = 'detalle_compras';
    protected $primaryKey = 'idDetalle';
    public $timestamps = true;

    protected $fillable = [
        'idCompra',
        'idProducto',
        'cantidad',
        'costo_unitario'
    ];

    protected $casts = [
        'cantidad' => 'integer',
        'costo_unitario' => 'decimal:2'
    ];

    public function compra()
    {
        return $this->belongsTo(Compra::class, 'idCompra', 'idCompra');
    }

    public function producto()
    {
        return $this->belongsTo(Producto::class, 'idProducto', 'idProducto');
    }
}
