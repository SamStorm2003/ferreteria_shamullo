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
        Schema::create('almacens', function (Blueprint $table) {
            $table->id('idAlmacen');
            $table->string('nombre', 100);
            $table->string('ubicacion', 255);
            $table->timestamp('fecha_registro')->useCurrent();
            $table->softDeletes();
            $table->timestamps();
            $table->index('nombre');
        });
    }
    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('almacens');
    }
};
