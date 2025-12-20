<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Almacen extends Model
{
    use SoftDeletes;
    protected $table = 'almacens';
    protected $primaryKey = 'idAlmacen';
    public $timestamps = true;

    protected $fillable = [
        'nombre',
        'ubicacion',
        'fecha_registro'
    ];

    public function users()
    {
        return $this->hasMany(User::class, 'idAlmacen');
    }
}
