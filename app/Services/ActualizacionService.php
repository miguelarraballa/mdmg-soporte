<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Compara la versión instalada (APP_VERSION) con el último tag del repositorio de GitHub.
 * La consulta se cachea unas horas para no agotar el límite de la API sin token (60/h).
 */
class ActualizacionService
{
    private const CACHE = 'actualizacion.ultima_version';

    private const HORAS = 6;

    public static function instalada(): string
    {
        return self::normalizar((string) config('app.version'));
    }

    /** Último tag publicado (sin la "v"), o null si no se pudo consultar. */
    public static function ultima(): ?string
    {
        $ultima = Cache::get(self::CACHE);

        if ($ultima === null) {
            $ultima = self::consultar();

            if ($ultima !== null) {
                Cache::put(self::CACHE, $ultima, now()->addHours(self::HORAS));
            }
        }

        return $ultima;
    }

    public static function hayActualizacion(): bool
    {
        $ultima = self::ultima();

        return $ultima !== null && version_compare($ultima, self::instalada(), '>');
    }

    public static function olvidar(): void
    {
        Cache::forget(self::CACHE);
    }

    public static function urlTags(): string
    {
        return 'https://github.com/' . config('app.repositorio') . '/tags';
    }

    private static function consultar(): ?string
    {
        try {
            $tags = Http::acceptJson()
                ->timeout(5)
                ->get('https://api.github.com/repos/' . config('app.repositorio') . '/tags', ['per_page' => 100])
                ->throw()
                ->json('*.name', []);
        } catch (Throwable $e) {
            report($e);

            return null;
        }

        return collect($tags)
            ->map(fn (string $tag) => self::normalizar($tag))
            ->filter(fn (string $version) => preg_match('/^\d+(\.\d+)*$/', $version))
            ->sort(fn (string $a, string $b) => version_compare($b, $a))
            ->first();
    }

    private static function normalizar(string $version): string
    {
        return ltrim(trim($version), 'vV');
    }
}
