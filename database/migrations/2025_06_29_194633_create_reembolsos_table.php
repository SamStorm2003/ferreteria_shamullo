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
        Schema::create('reembolsos', function (Blueprint $table) {
            $table->id('idReembolso');
            $table->unsignedBigInteger('idVenta');
            $table->foreign('idVenta')
                ->references('idVenta')
                ->on('ventas')
                ->onDelete('cascade');
            $table->decimal('monto', 10, 2);
            $table->dateTime('fecha');
            $table->string('motivo', 255)->nullable();
            $table->enum('estado', ['pendiente', 'aprobado', 'rechazado'])->default('pendiente');
            $table->unsignedBigInteger('idUsuario')->nullable();
            $table->foreign('idUsuario')
                ->references('id')
                ->on('users')
                ->onDelete('set null');
            $table->softDeletes();
            $table->timestamps();
        });
        DB::statement("ALTER TABLE reembolsos ADD CONSTRAINT chk_monto_reembolso CHECK (monto > 0)");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('reembolsos');
    }
};
