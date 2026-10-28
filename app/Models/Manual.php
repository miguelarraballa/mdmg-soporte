<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

/**
 * Manual de uso de un servicio: un index.html en el disco privado que solo se sirve
 * (a través de ManualController) a los administradores y a los clientes con ese servicio.
 */
class Manual extends Model
{
    public const DISCO = 'local';

    public const DIRECTORIO = 'manuales';

    protected $table = 'manuales';

    protected $fillable = [
        'titulo',
        'servicio_id',
        'archivo',
        'activo',
    ];

    protected $casts = [
        'activo' => 'bool',
    ];

    protected static function booted(): void
    {
        // Al sustituir o borrar el manual se elimina también el archivo anterior.
        static::updated(function (Manual $manual) {
            if ($manual->wasChanged('archivo') && filled($anterior = $manual->getOriginal('archivo'))) {
                Storage::disk(self::DISCO)->delete($anterior);
            }
        });

        static::deleted(fn (Manual $manual) => Storage::disk(self::DISCO)->delete($manual->archivo));
    }

    public function servicio(): BelongsTo
    {
        return $this->belongsTo(Servicio::class);
    }

    public function scopeActivos(Builder $query): Builder
    {
        return $query->where('activo', true);
    }

    /**
     * Manuales activos que puede consultar el usuario: todos si es administrador
     * y, si es cliente, los de los servicios que tiene asignados.
     */
    public function scopeVisiblesPara(Builder $query, User $user): Builder
    {
        return $query->activos()
            ->when(! $user->esAdmin(), fn (Builder $query) => $query->whereIn(
                'servicio_id',
                $user->servicios()->select('servicios.id'),
            ));
    }

    public function esVisiblePara(User $user): bool
    {
        return $user->activo && static::visiblesPara($user)->whereKey($this->getKey())->exists();
    }
}
