<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Producto extends Model
{
    use SoftDeletes;
    protected $table = 'productos';
    protected $primaryKey = 'idProducto';
    public $timestamps = true;

    protected $fillable = [
        'nombre',
        'codigo',
        'descripcion',
        'idCategoria',
        'marca',
        'url_imagen',
        'idProveedor',
        'fecha_ingreso',
        'fecha_actualizacion',
        'estado',
    ];

    protected $casts = [
        'fecha_ingreso' => 'datetime',
        'fecha_actualizacion' => 'datetime',
    ];

    public function proveedor(): BelongsTo
    {
        return $this->belongsTo(Proveedor::class, 'idProveedor', 'idProveedor');
    }

    protected function setEstadoAttribute($value)
    {
        $this->attributes['estado'] = $value ? 'activo' : 'inactivo';
    }

    public function stockAlmacenes()
    {
        return $this->hasMany(StockAlmacen::class, 'idProducto', 'idProducto');
    }

    public function categoria(): BelongsTo
    {
        return $this->belongsTo(Categoria::class, 'idCategoria', 'idCategoria');
    }

    public function promocion()
    {
        return $this->hasOne(Promocion::class, 'idProducto', 'idProducto');
    }
}
