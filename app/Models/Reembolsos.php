<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Reembolsos extends Model
{
    use SoftDeletes;

    protected $table = 'reembolsos';
    protected $primaryKey = 'idReembolso';

    protected $fillable = [
        'idVenta',
        'monto',
        'fecha',
        'motivo',
        'estado',
        'idUsuario',
    ];

    protected $casts = [
        'fecha' => 'datetime',
        'monto' => 'decimal:2',
        'estado' => 'string',
    ];

    public function venta()
    {
        return $this->belongsTo(Venta::class, 'idVenta', 'idVenta');
    }

    public function usuario()
    {
        return $this->belongsTo(User::class, 'idUsuario', 'id');
    }
}
