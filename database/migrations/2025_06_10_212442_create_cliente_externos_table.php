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
        Schema::create('cliente_externos', function (Blueprint $table) {
            $table->id('idClienteExterno');
            $table->string('nombre', 100);
            $table->string('documento_identidad', 50)->nullable();
            $table->string('telefono', 20)->nullable();
            $table->string('correo', 100)->nullable();
            $table->string('direccion', 255)->nullable();
            $table->softDeletes();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cliente_externos');
    }
};
