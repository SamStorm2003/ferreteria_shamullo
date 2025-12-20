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
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email', 100)->unique()->index();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('workos_id')->unique()->nullable();
            $table->rememberToken();
            $table->string('avatar')->nullable();
            $table->string('password')->nullable();
            $table->string('apellido', 100)->nullable();
            $table->string('telefono', 20)->nullable()->index();
            $table->string('direccion', 255)->nullable();
            $table->string('ciudad', 100)->nullable();
            $table->string('documento_identidad', 50)->nullable()->index();
            $table->date('fecha_nacimiento')->nullable();
            $table->unsignedBigInteger('idAlmacen')->nullable();
            $table->foreign('idAlmacen')->references('idAlmacen')->on('almacens')->onDelete('set null');
            $table->enum('estado', ['activo', 'inactivo'])->default('activo');
            $table->softDeletes();
            $table->timestamps();
        });

        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('users');
        Schema::dropIfExists('sessions');
    }
};
