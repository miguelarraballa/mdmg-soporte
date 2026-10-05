<?php

namespace App\Support;

use App\Enums\Rol;
use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Throwable;

/**
 * Instalación y actualización sin SSH (hosting compartido, subida por FTP).
 *
 * - Sin instalar: el middleware ComprobarInstalacion lleva todo a /instalar, que escribe el .env,
 *   genera APP_KEY, ejecuta las migraciones y crea el primer administrador.
 * - Instalada: el marcador storage/app/instalado guarda la versión. Al subir por FTP una release
 *   nueva (archivo VERSION distinto), la primera petición ejecuta las migraciones pendientes.
 */
class Instalacion
{
    public const EXTENSIONES = ['pdo_mysql', 'mbstring', 'openssl', 'tokenizer', 'ctype', 'fileinfo', 'intl', 'curl'];

    public static function marcador(): string
    {
        return storage_path('app/instalado');
    }

    public static function instalada(): bool
    {
        if (is_file(self::marcador())) {
            return true;
        }

        if (blank(config('app.key'))) {
            return false;
        }

        // Instalaciones anteriores al instalador: si ya hay un administrador, se da por instalada.
        try {
            if (Schema::hasTable('users') && User::where('rol', Rol::Admin)->exists()) {
                self::marcar();

                return true;
            }
        } catch (Throwable) {
            // Sin base de datos configurada todavía.
        }

        return false;
    }

    public static function marcar(): void
    {
        file_put_contents(self::marcador(), (string) config('app.version'));
    }

    /** Tras subir una versión nueva por FTP: migraciones pendientes y limpieza de cachés, una sola vez. */
    public static function actualizarSiHaceFalta(): void
    {
        $version = (string) config('app.version');

        if (trim((string) @file_get_contents(self::marcador())) === $version) {
            return;
        }

        $bloqueo = fopen(storage_path('app/instalacion.lock'), 'c');
        flock($bloqueo, LOCK_EX);

        try {
            // Otra petición pudo terminar la actualización mientras esperábamos el bloqueo.
            if (trim((string) @file_get_contents(self::marcador())) !== $version) {
                @set_time_limit(300);
                Artisan::call('migrate', ['--force' => true]);
                Artisan::call('optimize:clear');
                self::marcar();
            }
        } finally {
            flock($bloqueo, LOCK_UN);
            fclose($bloqueo);
        }
    }

    /** Crea el .env a partir de .env.example y genera APP_KEY (php artisan key:generate). */
    public static function prepararEnv(): void
    {
        $env = app()->environmentFilePath();

        if (! is_file($env) && ! @copy(base_path('.env.example'), $env)) {
            throw new RuntimeException('No se pudo crear el archivo .env.');
        }

        // key:generate solo sustituye una línea APP_KEY= existente.
        self::escribirEnv(['APP_KEY' => '']);

        Artisan::call('key:generate', ['--force' => true]);
    }

    /** Requisitos del servidor que no se cumplen (vacío = todo correcto). */
    public static function requisitosPendientes(): array
    {
        $pendientes = [];

        foreach (self::EXTENSIONES as $extension) {
            if (! extension_loaded($extension)) {
                $pendientes[] = "Falta la extensión de PHP «{$extension}».";
            }
        }

        $escribibles = [
            is_file(app()->environmentFilePath()) ? app()->environmentFilePath() : base_path(),
            storage_path(),
            storage_path('app'),
            storage_path('framework/cache'),
            storage_path('framework/sessions'),
            storage_path('framework/views'),
            storage_path('logs'),
            base_path('bootstrap/cache'),
            public_path('images/marca'),
        ];

        foreach ($escribibles as $ruta) {
            if (file_exists($ruta) && ! is_writable($ruta)) {
                $pendientes[] = 'Sin permiso de escritura: ' . str_replace(dirname(base_path()) . '/', '', $ruta);
            }
        }

        return $pendientes;
    }

    /** Sustituye (o añade) variables del .env, también las que estén comentadas. */
    public static function escribirEnv(array $valores): void
    {
        $env = app()->environmentFilePath();
        $contenido = (string) @file_get_contents($env);

        foreach ($valores as $clave => $valor) {
            $linea = $clave . '=' . self::valorEnv((string) $valor);
            $patron = '/^#?\s*' . preg_quote($clave, '/') . '=.*$/m';

            $contenido = preg_match($patron, $contenido)
                ? preg_replace_callback($patron, fn () => $linea, $contenido, 1)
                : rtrim($contenido) . PHP_EOL . $linea . PHP_EOL;
        }

        if (@file_put_contents($env, $contenido) === false) {
            throw new RuntimeException('No se pudo escribir el archivo .env.');
        }
    }

    private static function valorEnv(string $valor): string
    {
        if (preg_match('/^[A-Za-z0-9_.:\/@+=-]*$/', $valor)) {
            return $valor;
        }

        // Comillas simples: literal, sin interpolar ${...}.
        if (! str_contains($valor, "'")) {
            return "'{$valor}'";
        }

        return '"' . addcslashes($valor, '"\\$') . '"';
    }
}
