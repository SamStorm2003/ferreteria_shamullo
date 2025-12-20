<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Envios extends Model
{
    use SoftDeletes;
    protected $table = 'envios';
    protected $primaryKey = 'idEnvio';

    protected $fillable = [
        'idVenta',
        'direccion_envio',
        'metodo_envio',
        'numero_seguimiento',
        'estado_envio',
        'fecha_envio',
        'fecha_entrega_estimada',
    ];

    protected $dates = [
        'fecha_envio',
        'fecha_entrega_estimada',
        'created_at',
        'updated_at',
        'deleted_at',
    ];

    public function venta()
    {
        return $this->belongsTo(Venta::class, 'idVenta', 'idVenta');
    }
}
