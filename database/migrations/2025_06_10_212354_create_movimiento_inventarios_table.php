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
        Schema::create('movimiento_inventarios', function (Blueprint $table) {
            $table->id('idMovimiento');
            $table->unsignedBigInteger('idProducto');
            $table->unsignedBigInteger('idAlmacen');
            $table->enum('tipo', ['entrada', 'salida', 'ajuste']);
            $table->integer('cantidad');
            $table->decimal('costo_unitario', 10, 2);
            $table->dateTime('fecha')->nullable();
            $table->unsignedBigInteger('idUsuario')->nullable();
            $table->string('motivo', 255)->nullable();
            $table->foreign('idProducto')
                ->references('idProducto')
                ->on('productos')
                ->onDelete('restrict');
            $table->foreign('idAlmacen')
                ->references('idAlmacen')
                ->on('almacens')
                ->onDelete('restrict');
            $table->foreign('idUsuario')
                ->references('id')
                ->on('users')
                ->onDelete('set null');
            $table->softDeletes();
            $table->timestamps();
        });
        DB::statement("ALTER TABLE movimiento_inventarios ADD CONSTRAINT chk_cantidad_mov CHECK (cantidad != 0)");
        DB::statement("ALTER TABLE movimiento_inventarios ADD CONSTRAINT chk_costo_mov CHECK (costo_unitario >= 0)");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('movimiento_inventarios');
    }
};
