<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Enums\Rol;
use Database\Factories\UserFactory;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'email', 'password', 'rol', 'activo', 'slack_user_id'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable implements FilamentUser
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'rol' => Rol::class,
            'activo' => 'bool',
        ];
    }

    /**
     * Cada rol entra solo en su panel: administradores en /admin y clientes en /portal.
     */
    protected static function booted(): void
    {
        // Borrar los tickets uno a uno para que también se eliminen sus adjuntos.
        static::deleting(fn (User $user) => $user->tickets()->get()->each->delete());
    }

    public function canAccessPanel(Panel $panel): bool
    {
        return $this->activo && $this->rol?->panel() === $panel->getId();
    }

    public function tieneSlack(): bool
    {
        return filled($this->slack_user_id);
    }

    public function esAdmin(): bool
    {
        return $this->rol === Rol::Admin;
    }

    public function tickets(): HasMany
    {
        return $this->hasMany(Ticket::class);
    }

    public function servicios(): BelongsToMany
    {
        return $this->belongsToMany(Servicio::class)->withTimestamps();
    }

    /**
     * Servicios entre los que el cliente puede elegir al abrir un ticket.
     */
    public function serviciosDisponibles(): BelongsToMany
    {
        return $this->servicios()->where('servicios.activo', true)->orderBy('servicios.nombre');
    }

    public function scopeAdmins(Builder $query): Builder
    {
        return $query->where('rol', Rol::Admin)->where('activo', true);
    }

    public function scopeClientes(Builder $query): Builder
    {
        return $query->where('rol', Rol::Cliente);
    }
}
