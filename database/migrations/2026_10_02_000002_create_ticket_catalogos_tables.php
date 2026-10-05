<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ticket_categorias', function (Blueprint $table) {
            $table->id();
            $table->string('nombre');
            $table->string('color')->nullable();
            $table->unsignedInteger('orden')->default(0);
            $table->boolean('es_default')->default(false);
            $table->timestamps();
        });

        Schema::create('ticket_estados', function (Blueprint $table) {
            $table->id();
            $table->string('nombre');
            $table->string('color')->nullable();
            $table->unsignedInteger('orden')->default(0);
            $table->boolean('es_default')->default(false);
            $table->boolean('es_cierre')->default(false);
            $table->boolean('es_en_proceso')->default(false);
            $table->boolean('es_pendiente_cliente')->default(false);
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });

        Schema::create('ticket_prioridades', function (Blueprint $table) {
            $table->id();
            $table->string('nombre');
            $table->string('color')->nullable();
            $table->unsignedInteger('orden')->default(0);
            $table->boolean('es_default')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ticket_prioridades');
        Schema::dropIfExists('ticket_estados');
        Schema::dropIfExists('ticket_categorias');
    }
};
