<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Factura extends Model
{
    use HasFactory;
    protected $table = 'facturas';
    protected $primaryKey = 'id';

    protected $fillable = [
        'idVenta',
        'numero_factura',
        'fecha',
        'nit_emisor',
        'nit_cliente',
        'razon_social_cliente',
        'total',
    ];
    public function venta()
    {
        return $this->belongsTo(Venta::class, 'idVenta', 'idVenta');
    }
}
