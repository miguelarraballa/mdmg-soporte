<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Manuales de uso (un index.html) que ven los clientes con el servicio asignado.
        Schema::create('manuales', function (Blueprint $table) {
            $table->id();
            $table->string('titulo');
            $table->foreignId('servicio_id')->constrained('servicios')->cascadeOnDelete();
            $table->string('archivo');
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('manuales');
    }
};
