<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('envios', function (Blueprint $table) {
            $table->id('idEnvio');
            $table->unsignedBigInteger('idVenta');
            $table->foreign('idVenta')
                ->references('idVenta')
                ->on('ventas')
                ->onDelete('cascade');
            $table->string('direccion_envio', 255);
            $table->string('metodo_envio', 50);
            $table->string('numero_seguimiento', 100)->nullable();
            $table->enum('estado_envio', ['pendiente', 'enviado', 'entregado'])->default('pendiente');
            $table->dateTime('fecha_envio')->nullable();
            $table->date('fecha_entrega_estimada')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('envios');
    }
};
