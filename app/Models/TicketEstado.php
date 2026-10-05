<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TicketEstado extends Model
{
    protected $table = 'ticket_estados';

    protected $fillable = [
        'nombre',
        'color',
        'orden',
        'es_default',
        'es_cierre',
        'es_en_proceso',
        'es_pendiente_cliente',
        'activo',
    ];

    protected $casts = [
        'orden' => 'integer',
        'es_default' => 'bool',
        'es_cierre' => 'bool',
        'es_en_proceso' => 'bool',
        'es_pendiente_cliente' => 'bool',
        'activo' => 'bool',
    ];

    public function tickets(): HasMany
    {
        return $this->hasMany(Ticket::class, 'ticket_estado_id');
    }

    public function color(): string
    {
        return $this->color ?? 'gray';
    }

    public static function default(): ?self
    {
        return static::where('es_default', true)->where('activo', true)->first()
            ?? static::where('activo', true)->orderBy('orden')->first();
    }

    public static function enProceso(): ?self
    {
        return static::where('es_en_proceso', true)->where('activo', true)->first();
    }

    public static function pendienteCliente(): ?self
    {
        return static::where('es_pendiente_cliente', true)->where('activo', true)->first();
    }

    public static function cierre(): ?self
    {
        return static::where('es_cierre', true)->where('activo', true)->orderBy('orden')->first();
    }
}
