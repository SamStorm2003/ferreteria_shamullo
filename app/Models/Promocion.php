<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Promocion extends Model
{
    use SoftDeletes;
    protected $table = 'promocions';
    protected $primaryKey = 'idPromocion';
    public $timestamps = true;

    protected $fillable = [
        'nombre',
        'descripcion',
        'idProducto',
        'descuento',
        'url_imagen',
        'fecha_inicio',
        'fecha_fin',
        'estado'
    ];

    protected $casts = [
        'descuento' => 'decimal:2',
        'fecha_inicio' => 'date',
        'fecha_fin' => 'date',
    ];

    public function producto(): BelongsTo
    {
        return $this->belongsTo(Producto::class, 'idProducto', 'idProducto');
    }
}
