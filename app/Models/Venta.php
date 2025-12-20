<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Venta extends Model
{
    use SoftDeletes;
    protected $table = 'ventas';
    protected $primaryKey = 'idVenta';
    public $timestamps = true;

    protected $fillable = [
        'fecha',
        'idUsuarioCliente',
        'idClienteExterno',
        'idUsuarioVendedor',
        'total',
        'estado',
        'tipo_entrega',
    ];

    protected $casts = [
        'fecha' => 'datetime',
        'total' => 'decimal:2'
    ];

    public function clienteUsuario()
    {
        return $this->belongsTo(User::class, 'idUsuarioCliente', 'id');
    }

    public function clienteExterno()
    {
        return $this->belongsTo(ClienteExterno::class, 'idClienteExterno', 'idClienteExterno');
    }

    public function vendedor()
    {
        return $this->belongsTo(User::class, 'idUsuarioVendedor', 'id');
    }

    public function detalles()
    {
        return $this->hasMany(DetalleVenta::class, 'idVenta', 'idVenta');
    }

    public function pagos()
    {
        return $this->hasMany(Pago::class, 'idVenta', 'idVenta');
    }

    public function envio()
    {
        return $this->hasOne(Envios::class, 'idVenta', 'idVenta');
    }
}
