<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Reporte extends Model
{
    protected $table = 'reportes';
    protected $primaryKey = 'idReporte';
    public $timestamps = true;

    protected $fillable = [
        'tipo',
        'fecha_inicio',
        'fecha_fin',
        'descripcion',
        'idUsuario'
    ];

    protected $casts = [
        'fecha_inicio' => 'datetime',
        'fecha_fin' => 'datetime'
    ];

    public function usuario()
    {
        return $this->belongsTo(User::class, 'idUsuario', 'id');
    }
}
