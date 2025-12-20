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
        Schema::create('stock_almacens', function (Blueprint $table) {
            $table->id('idStock');
            $table->unsignedBigInteger('idProducto');
            $table->unsignedBigInteger('idAlmacen');
            $table->integer('cantidad')->default(0);
            $table->decimal('costo_unitario', 10, 2)->default(0);
            $table->decimal('precio_venta', 10, 2)->default(0);
            $table->timestamp('fecha_registro')->useCurrent();
            $table->softDeletes();
            $table->timestamps();
            $table->foreign('idProducto')
                ->references('idProducto')
                ->on('productos')
                ->onDelete('cascade');
            $table->foreign('idAlmacen')
                ->references('idAlmacen')
                ->on('almacens')
                ->onDelete('cascade');
        });
        DB::statement("ALTER TABLE stock_almacens ADD CONSTRAINT chk_cantidad CHECK (cantidad >= 0)");
        DB::statement("ALTER TABLE stock_almacens ADD CONSTRAINT chk_costo CHECK (costo_unitario >= 0)");
        DB::statement("ALTER TABLE stock_almacens ADD CONSTRAINT chk_precio_venta CHECK (precio_venta >= 0)");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stock_almacens');
    }
};
