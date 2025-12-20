<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('detalle_ventas', function (Blueprint $table) {
            $table->id('idDetalle');
            $table->unsignedBigInteger('idVenta');
            $table->foreign('idVenta')
                ->references('idVenta')
                ->on('ventas')
                ->onDelete('cascade');

            $table->unsignedBigInteger('idProducto');
            $table->foreign('idProducto')
                ->references('idProducto')
                ->on('productos')
                ->onDelete('restrict');
            $table->unsignedBigInteger('idAlmacen');
            $table->foreign('idAlmacen')
                ->references('idAlmacen')
                ->on('almacens')
                ->onDelete('restrict');
            $table->integer('cantidad');
            $table->decimal('precio_unitario', 10, 2);
            $table->softDeletes();
            $table->timestamps();
        });
        DB::statement("ALTER TABLE detalle_ventas ADD CONSTRAINT chk_cantidad_detalle CHECK (cantidad > 0)");
        DB::statement("ALTER TABLE detalle_ventas ADD CONSTRAINT chk_precio_unitario CHECK (precio_unitario >= 0)");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('detalle_ventas');
    }
};
