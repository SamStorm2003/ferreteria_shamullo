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
        Schema::create('productos', function (Blueprint $table) {
            $table->id('idProducto');
            $table->string('nombre', 100);
            $table->string('codigo', 50)->unique();
            $table->string('descripcion', 255);
            $table->unsignedBigInteger('idCategoria')->nullable();
            $table->foreign('idCategoria')
                ->references('idCategoria')
                ->on('categorias')
                ->onDelete('set null');
            $table->string('marca', 100)->nullable();
            $table->string('url_imagen', 255)->nullable();
            $table->unsignedBigInteger('idProveedor')->nullable();
            $table->foreign('idProveedor')
                ->references('idProveedor')
                ->on('proveedors')
                ->onDelete('set null');
            $table->timestamp('fecha_ingreso')->useCurrent();
            $table->timestamp('fecha_actualizacion')->useCurrent()->useCurrentOnUpdate();
            $table->enum('estado', ['activo', 'inactivo'])->default('activo');
            $table->softDeletes();
            $table->timestamps();
            $table->index('estado');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('productos');
    }
};
