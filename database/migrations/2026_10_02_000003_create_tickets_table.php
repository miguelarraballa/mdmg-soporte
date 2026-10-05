<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tickets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('ticket_categoria_id')->nullable()->constrained('ticket_categorias')->nullOnDelete();
            $table->foreignId('ticket_estado_id')->constrained('ticket_estados');
            $table->foreignId('ticket_prioridad_id')->nullable()->constrained('ticket_prioridades')->nullOnDelete();
            $table->string('asunto');
            $table->string('canal')->default('portal'); // portal, manual
            $table->timestamp('ultima_actividad_en')->nullable();
            $table->timestamp('cerrado_en')->nullable();
            $table->timestamps();
        });

        Schema::create('ticket_mensajes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ticket_id')->constrained('tickets')->cascadeOnDelete();
            $table->string('autor_tipo'); // cliente, agente
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->longText('cuerpo');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ticket_mensajes');
        Schema::dropIfExists('tickets');
    }
};
