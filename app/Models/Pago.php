<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Pago extends Model
{
    use SoftDeletes;
    protected $table = 'pagos';
    protected $primaryKey = 'idPago';

    public $timestamps = true;

    protected $fillable = [
        'idVenta',
        'monto',
        'metodo',
        'fecha',
        'estado',
        'referencia_pago',
    ];

    protected $casts = [
        'monto' => 'decimal:2',
        'fecha' => 'datetime',
    ];

    public function venta()
    {
        return $this->belongsTo(Venta::class, 'idVenta', 'idVenta');
    }
}
