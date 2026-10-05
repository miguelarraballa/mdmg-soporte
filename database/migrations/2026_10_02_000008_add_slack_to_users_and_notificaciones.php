<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Webhook de Slack del cliente (cifrado con APP_KEY).
            $table->text('slack_webhook_url')->nullable()->after('activo');
        });

        Schema::table('notificaciones', function (Blueprint $table) {
            $table->string('canal')->default('email')->after('tipo')->index(); // email, slack
            $table->json('payload')->nullable()->after('cuerpo_html');
            $table->string('email_destinatario')->nullable()->change();
            $table->longText('cuerpo_html')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('notificaciones', function (Blueprint $table) {
            $table->dropColumn(['canal', 'payload']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('slack_webhook_url');
        });
    }
};
