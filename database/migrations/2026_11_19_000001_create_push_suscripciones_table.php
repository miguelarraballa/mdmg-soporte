<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Notificaciones push del navegador: una suscripción por navegador y usuario,
 * y la marca de prioridad urgente, que es la única que avisa por push.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('push_suscripciones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->text('endpoint');
            // El endpoint puede superar el límite de un índice; se indexa su SHA-256.
            $table->char('endpoint_hash', 64)->unique();
            $table->string('clave_p256dh');
            $table->string('clave_auth');
            $table->string('navegador')->nullable();
            $table->timestamps();
        });

        Schema::table('ticket_prioridades', function (Blueprint $table) {
            $table->boolean('es_urgente')->default(false)->after('es_default');
        });

        DB::table('ticket_prioridades')->where('nombre', 'Urgente')->update(['es_urgente' => true]);
    }

    public function down(): void
    {
        Schema::table('ticket_prioridades', function (Blueprint $table) {
            $table->dropColumn('es_urgente');
        });

        Schema::dropIfExists('push_suscripciones');
    }
};
