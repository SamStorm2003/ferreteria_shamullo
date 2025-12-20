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
        Schema::create('ventas', function (Blueprint $table) {
            $table->id('idVenta');
            $table->dateTime('fecha');
            $table->unsignedBigInteger('idUsuarioCliente')->nullable();
            $table->foreign('idUsuarioCliente')
                ->references('id')->on('users')
                ->onDelete('set null');
            $table->unsignedBigInteger('idClienteExterno')->nullable();
            $table->foreign('idClienteExterno')
                ->references('idClienteExterno')->on('cliente_externos')
                ->onDelete('set null');
            $table->foreignId('idUsuarioVendedor')
                ->nullable()
                ->constrained('users', 'id')
                ->onDelete('set null');
            $table->decimal('total', 10, 2);
            $table->enum('estado', ['completada', 'pendiente', 'cancelada'])->default('pendiente');
            $table->enum('tipo_entrega', ['envio', 'recogida']);
            $table->softDeletes();
            $table->timestamps();
        });
        DB::statement('ALTER TABLE ventas ADD CONSTRAINT chk_total_venta CHECK (total >= 0)');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ventas');
    }
};
