<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notificaciones', function (Blueprint $table) {
            $table->id();
            $table->string('tipo');
            $table->string('email_destinatario');
            $table->string('asunto');
            $table->longText('cuerpo_html');
            $table->string('estado')->default('en_cola'); // en_cola, enviado, error
            $table->unsignedTinyInteger('intentos')->default(0);
            $table->text('error')->nullable();
            $table->timestamp('enviado_en')->nullable();
            $table->foreignId('ticket_id')->nullable()->constrained('tickets')->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['estado', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notificaciones');
    }
};
