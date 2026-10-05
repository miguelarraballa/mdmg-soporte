<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();

        DB::table('ticket_categorias')->insert([
            'nombre' => 'Soporte',
            'color' => 'primary',
            'orden' => 1,
            'es_default' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $estados = [
            ['nombre' => 'Nuevo', 'color' => 'info', 'orden' => 1, 'es_default' => true],
            ['nombre' => 'En proceso', 'color' => 'warning', 'orden' => 2, 'es_en_proceso' => true],
            ['nombre' => 'Pendiente cliente', 'color' => 'primary', 'orden' => 3, 'es_pendiente_cliente' => true],
            ['nombre' => 'Resuelto', 'color' => 'success', 'orden' => 4, 'es_cierre' => true],
            ['nombre' => 'Cerrado', 'color' => 'gray', 'orden' => 5, 'es_cierre' => true],
        ];

        foreach ($estados as $estado) {
            DB::table('ticket_estados')->insert([
                'es_default' => false,
                'es_cierre' => false,
                'es_en_proceso' => false,
                'es_pendiente_cliente' => false,
                ...$estado,
                'activo' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        $prioridades = [
            ['nombre' => 'Baja', 'color' => 'gray', 'orden' => 1, 'es_default' => false],
            ['nombre' => 'Media', 'color' => 'info', 'orden' => 2, 'es_default' => true],
            ['nombre' => 'Alta', 'color' => 'warning', 'orden' => 3, 'es_default' => false],
            ['nombre' => 'Urgente', 'color' => 'danger', 'orden' => 4, 'es_default' => false],
        ];

        foreach ($prioridades as $prioridad) {
            DB::table('ticket_prioridades')->insert([
                ...$prioridad,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        DB::table('ticket_categorias')->delete();
        DB::table('ticket_estados')->delete();
        DB::table('ticket_prioridades')->delete();
    }
};
