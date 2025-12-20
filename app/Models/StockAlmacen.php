<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class StockAlmacen extends Model
{
    use SoftDeletes;
    protected $table = 'stock_almacens';
    protected $primaryKey = 'idStock';
    public $timestamps = true;

    protected $fillable = [
        'idProducto',
        'idAlmacen',
        'cantidad',
        'costo_unitario',
        'precio_venta'
    ];

    protected $casts = [
        'cantidad' => 'integer'
    ];

    public function producto()
    {
        return $this->belongsTo(Producto::class, 'idProducto', 'idProducto');
    }

    public function almacen()
    {
        return $this->belongsTo(Almacen::class, 'idAlmacen', 'idAlmacen');
    }

    public function movimientos()
    {
        return $this->hasMany(MovimientoInventario::class, 'idProducto', 'idProducto')
            ->where('idAlmacen', $this->idAlmacen);
    }
}
