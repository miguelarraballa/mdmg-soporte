<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Throwable;

/**
 * Compara la versión instalada (APP_VERSION) con la última release publicada en GitHub.
 * Se usa la release (no el tag) porque el zip tarda unos minutos en generarse tras subir el tag.
 * La consulta se cachea unas horas para no agotar el límite de la API sin token (60/h).
 */
class ActualizacionService
{
    private const CACHE = 'actualizacion.ultima_release';

    private const HORAS = 6;

    public static function instalada(): string
    {
        return self::normalizar((string) config('app.version'));
    }

    /**
     * Última release con su zip y su firma, o null si no se pudo consultar.
     *
     * @return array{version: string, zip: string, firma: ?string, tamano: int}|null
     */
    public static function release(): ?array
    {
        $release = Cache::get(self::CACHE);

        if ($release === null) {
            $release = self::consultar();

            if ($release !== null) {
                Cache::put(self::CACHE, $release, now()->addHours(self::HORAS));
            }
        }

        return $release;
    }

    /** Última versión publicada (sin la "v"), o null si no se pudo consultar. */
    public static function ultima(): ?string
    {
        return self::release()['version'] ?? null;
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

    public static function urlReleases(): string
    {
        return 'https://github.com/'.config('app.repositorio').'/releases';
    }

    /** @return array{version: string, zip: string, firma: ?string, tamano: int}|null */
    private static function consultar(): ?array
    {
        try {
            $release = Http::acceptJson()
                ->timeout(5)
                ->get('https://api.github.com/repos/'.config('app.repositorio').'/releases/latest')
                ->throw()
                ->json();
        } catch (Throwable $e) {
            report($e);

            return null;
        }

        $version = self::normalizar((string) ($release['tag_name'] ?? ''));
        $assets = collect($release['assets'] ?? []);
        $zip = $assets->first(fn (array $asset) => Str::endsWith($asset['name'], '.zip'));
        $firma = $assets->first(fn (array $asset) => Str::endsWith($asset['name'], '.zip.sig'));

        if (! preg_match('/^\d+(\.\d+)*$/', $version) || $zip === null) {
            return null;
        }

        return [
            'version' => $version,
            'zip' => $zip['browser_download_url'],
            'firma' => $firma['browser_download_url'] ?? null,
            'tamano' => (int) $zip['size'],
        ];
    }

    private static function normalizar(string $version): string
    {
        return ltrim(trim($version), 'vV');
    }
}
