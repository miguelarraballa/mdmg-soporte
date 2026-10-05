<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('servicios', function (Blueprint $table) {
            $table->id();
            $table->string('nombre');
            $table->text('descripcion')->nullable();
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });

        // Servicios asignados a cada cliente.
        Schema::create('servicio_user', function (Blueprint $table) {
            $table->foreignId('servicio_id')->constrained('servicios')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamps();

            $table->primary(['servicio_id', 'user_id']);
        });

        Schema::table('tickets', function (Blueprint $table) {
            $table->foreignId('servicio_id')->nullable()->after('user_id')->constrained('servicios')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->dropConstrainedForeignId('servicio_id');
        });
        Schema::dropIfExists('servicio_user');
        Schema::dropIfExists('servicios');
    }
};
