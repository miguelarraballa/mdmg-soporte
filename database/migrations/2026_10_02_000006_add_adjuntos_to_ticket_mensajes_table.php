<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ticket_mensajes', function (Blueprint $table) {
            // Lista de {ruta, nombre, mime, tamano} en el disco privado "local".
            $table->json('adjuntos')->nullable()->after('cuerpo');
        });
    }

    public function down(): void
    {
        Schema::table('ticket_mensajes', function (Blueprint $table) {
            $table->dropColumn('adjuntos');
        });
    }
};
