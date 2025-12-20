<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('proveedors', function (Blueprint $table) {
            $table->id('idProveedor');
            $table->string('nombre', 100);
            $table->string('contacto', 100)->nullable();
            $table->string('telefono', 20)->nullable();
            $table->string('correo', 100)->nullable();
            $table->string('direccion', 255)->nullable();
            $table->enum('estado', ['activo', 'inactivo'])->default('activo');
            $table->dateTime('fecha_registro')->default(DB::raw('CURRENT_TIMESTAMP'));
            $table->softDeletes();
            $table->timestamps();
            $table->index('nombre');
            $table->index('estado');
        });
        DB::statement("ALTER TABLE proveedors ADD CONSTRAINT chk_telefono_proveedor CHECK (telefono REGEXP '^[0-9]{7,20}$' OR telefono IS NULL)");
        DB::statement("ALTER TABLE proveedors ADD CONSTRAINT chk_correo_proveedor CHECK (correo REGEXP '^[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Za-z]{2,}$' OR correo IS NULL)");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('proveedors');
    }
};
