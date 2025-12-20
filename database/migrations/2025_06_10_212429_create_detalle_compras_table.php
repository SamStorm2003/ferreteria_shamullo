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
        Schema::create('detalle_compras', function (Blueprint $table) {
            $table->id('idDetalle');
            $table->integer('cantidad');
            $table->decimal('costo_unitario', 10, 2);
            $table->timestamps();
            $table->foreignId('idCompra')
                ->constrained('compras', 'idCompra')
                ->onDelete('cascade');
            $table->foreignId('idProducto')
                ->constrained('productos', 'idProducto')
                ->onDelete('restrict');
            $table->softDeletes();
        });
        DB::statement('ALTER TABLE detalle_compras ADD CONSTRAINT chk_cantidad_compra CHECK (cantidad > 0)');
        DB::statement('ALTER TABLE detalle_compras ADD CONSTRAINT chk_costo_unitario CHECK (costo_unitario >= 0)');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('detalle_compras');
    }
};
