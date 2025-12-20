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
        Schema::create('pagos', function (Blueprint $table) {
            $table->id('idPago');
            $table->unsignedBigInteger('idVenta');
            $table->foreign('idVenta')
                ->references('idVenta')
                ->on('ventas')
                ->onDelete('cascade');
            $table->decimal('monto', 10, 2);
            $table->enum('metodo', ['tarjeta', 'efectivo', 'online', 'transferencia']);
            $table->dateTime('fecha');
            $table->enum('estado', ['aprobado', 'pendiente', 'rechazado'])->default('pendiente');
            $table->string('referencia_pago', 100);
            $table->softDeletes();
            $table->timestamps();
        });
        DB::statement("ALTER TABLE pagos ADD CONSTRAINT chk_monto_pago CHECK (monto > 0)");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pagos');
    }
};
