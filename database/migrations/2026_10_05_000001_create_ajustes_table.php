<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ajustes de la app editables desde /admin/configuracion (nombre, icono, fuentes y colores).
 * Solo se guardan las claves modificadas; el resto toma los valores del manual (App\Filament\Marca).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ajustes', function (Blueprint $table) {
            $table->string('clave', 64)->primary();
            $table->text('valor')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ajustes');
    }
};
