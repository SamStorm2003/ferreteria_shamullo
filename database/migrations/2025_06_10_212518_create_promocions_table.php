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
        Schema::create('promocions', function (Blueprint $table) {
            $table->id('idPromocion');
            $table->string('nombre', 100);
            $table->text('descripcion');
            $table->string('url_imagen', 255)->nullable();
            $table->unsignedBigInteger('idProducto')->nullable();
            $table->foreign('idProducto')
                ->references('idProducto')
                ->on('productos')
                ->onDelete('set null');
            $table->decimal('descuento', 5, 2);
            $table->date('fecha_inicio');
            $table->date('fecha_fin');
            $table->enum('estado', ['activa', 'inactiva'])->default('activa');
            $table->softDeletes();
            $table->timestamps();
        });
        DB::statement('ALTER TABLE promocions ADD CONSTRAINT chk_descuento CHECK (descuento >= 0 AND descuento <= 100)');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('promocions');
    }
};
