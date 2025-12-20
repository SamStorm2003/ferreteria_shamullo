<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
Use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('facturas', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('idVenta');
            $table->string('numero_factura', 50);
            $table->dateTime('fecha')->default(DB::raw('CURRENT_TIMESTAMP'));
            $table->string('nit_emisor', 20)->nullable();
            $table->string('nit_cliente', 20)->nullable();
            $table->string('razon_social_cliente', 100)->nullable();
            $table->decimal('total', 10, 2);
            $table->foreign('idVenta')->references('idVenta')->on('ventas')->onDelete('cascade');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('facturas');
    }
};
