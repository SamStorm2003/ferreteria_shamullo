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
        Schema::create('compras', function (Blueprint $table) {
            $table->id('idCompra');
            $table->unsignedBigInteger('idProveedor');
            $table->unsignedBigInteger('idUsuario')->nullable();
            $table->dateTime('fecha')->nullable();
            $table->decimal('total', 10, 2);
            $table->enum('estado', ['completada', 'pendiente', 'cancelada'])->default('pendiente');
            $table->foreign('idProveedor')
                ->references('idProveedor')
                ->on('proveedors')
                ->onDelete('restrict');
            $table->foreign('idUsuario')
                ->references('id')
                ->on('users')
                ->onDelete('set null');
            $table->unsignedBigInteger('idAlmacen')->nullable();
            $table->foreign('idAlmacen')
                ->references('idAlmacen')
                ->on('almacens')
                ->onDelete('restrict');
            $table->timestamps();
            $table->softDeletes();
        });
        DB::statement("ALTER TABLE compras ADD CONSTRAINT chk_total_compra CHECK (total >= 0)");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('compras');
    }
};
