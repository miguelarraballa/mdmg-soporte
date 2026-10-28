<?php

namespace App\Support;

use FilesystemIterator;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use PharData;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;
use Throwable;
use ZipArchive;

/**
 * Actualización desde Configuración, sin FTP: descarga el zip de la última release de GitHub, verifica su
 * firma ed25519 (con sodium u openssl), lo extrae en storage/app/actualizacion y deja un plan que ejecuta public/actualizar.php,
 * que sustituye las carpetas de código sin cargar Laravel. La versión sustituida queda en
 * storage/app/actualizacion/anterior para poder restaurarla.
 */
class Actualizador
{
    /** Rutas de la app que no vienen en el zip y se conservan al sustituir el código. */
    public const CONSERVAR = ['.env', 'storage', 'public/images/marca/subidas', 'public/storage'];

    private const ESPACIO_MINIMO = 300 * 1024 * 1024;

    private const MINUTOS_PLAN = 10;

    private const BYTES_FIRMA = 64;

    private const BYTES_CLAVE = 32;

    private const DER_CLAVE_ED25519 = '302a300506032b6570032100';

    private const MARCA_INICIO = '# BEGIN MDMG Soporte';

    private const MARCA_FIN = '# END MDMG Soporte';

    public static function carpeta(string $ruta = ''): string
    {
        return storage_path('app/actualizacion'.($ruta !== '' ? '/'.$ruta : ''));
    }

    /**
     * Lo que impide actualizar desde aquí (vacío = se puede).
     *
     * @return list<string>
     */
    public static function requisitosPendientes(): array
    {
        $pendientes = [];

        if (is_dir(base_path('.git'))) {
            $pendientes[] = 'Es una copia de desarrollo (repositorio git): actualízala con git.';
        }

        if (! class_exists(ZipArchive::class) && ! class_exists(PharData::class)) {
            $pendientes[] = 'Falta la extensión «zip» de PHP.';
        }

        if (! function_exists('sodium_crypto_sign_verify_detached') && ! defined('OPENSSL_KEYTYPE_ED25519')) {
            $pendientes[] = 'Falta la extensión «openssl» de PHP, necesaria para verificar la firma.';
        }

        if (blank(config('app.clave_firma'))) {
            $pendientes[] = 'No hay clave pública para verificar la firma (app.clave_firma).';
        }

        $libre = @disk_free_space(storage_path());

        if ($libre !== false && $libre < self::ESPACIO_MINIMO) {
            $pendientes[] = sprintf('Hacen falta al menos %d MB libres en disco (hay %d MB).', self::ESPACIO_MINIMO / 1048576, $libre / 1048576);
        }

        // Basta con poder escribir en la carpeta de la app: public/actualizar.php renombra en ella las
        // carpetas de código que PHP no puede mover (p. ej. subidas por FTP con otro usuario).
        foreach ([base_path(), storage_path('app')] as $ruta) {
            if (! is_writable($ruta)) {
                $pendientes[] = 'PHP no puede escribir en '.str_replace(dirname(base_path()).'/', '', $ruta).'. Dale los mismos permisos que a la carpeta storage.';
            }
        }

        return $pendientes;
    }

    /**
     * Descarga el zip y su firma de la release.
     *
     * @param  array{version: string, zip: string, firma: ?string, tamano: int}  $release
     */
    public static function descargar(array $release): void
    {
        if (blank($release['firma'])) {
            throw new RuntimeException("La release v{$release['version']} no está firmada: actualiza por FTP.");
        }

        self::limpiar();

        $carpeta = self::carpeta($release['version']);
        File::ensureDirectoryExists($carpeta);
        @set_time_limit(300);

        try {
            Http::timeout(300)->withOptions(['sink' => "{$carpeta}/release.zip"])->get($release['zip'])->throw();
            $firma = Http::timeout(30)->get($release['firma'])->throw()->body();
        } catch (Throwable $e) {
            report($e);

            throw new RuntimeException('No se pudo descargar la release de GitHub: '.$e->getMessage());
        }

        if (filesize("{$carpeta}/release.zip") !== $release['tamano']) {
            throw new RuntimeException('La descarga está incompleta. Vuelve a intentarlo.');
        }

        file_put_contents("{$carpeta}/release.zip.sig", trim($firma));
    }

    /** Comprueba que el zip lo firmó la clave privada de las releases (secreto RELEASE_FIRMA_CLAVE). */
    public static function verificar(string $version): void
    {
        $carpeta = self::carpeta($version);

        if (! self::firmaValida("{$carpeta}/release.zip", (string) @file_get_contents("{$carpeta}/release.zip.sig"), (string) config('app.clave_firma'))) {
            File::deleteDirectory($carpeta);

            throw new RuntimeException('La firma del zip no es válida. Se ha descartado la descarga y no se ha cambiado nada.');
        }
    }

    /**
     * La firma cubre el SHA-256 del zip, así no hace falta cargarlo entero en memoria. Se verifica con
     * sodium o, si el hosting no la tiene, con openssl (ed25519 desde PHP 8.4; Laravel ya exige openssl).
     */
    public static function firmaValida(string $zip, string $firma, string $clavePublica, ?bool $conSodium = null): bool
    {
        $firma = base64_decode(trim($firma), true);
        $clave = base64_decode($clavePublica, true);

        if (! is_file($zip) || $firma === false || $clave === false
            || strlen($firma) !== self::BYTES_FIRMA || strlen($clave) !== self::BYTES_CLAVE) {
            return false;
        }

        $mensaje = hash_file('sha256', $zip, true);

        if ($conSodium ?? function_exists('sodium_crypto_sign_verify_detached')) {
            return sodium_crypto_sign_verify_detached($firma, $mensaje, $clave);
        }

        // Clave ed25519 en bruto → PEM (cabecera DER SubjectPublicKeyInfo de ed25519 + los 32 bytes).
        $pem = "-----BEGIN PUBLIC KEY-----\n"
            .chunk_split(base64_encode(hex2bin(self::DER_CLAVE_ED25519).$clave), 64, "\n")
            ."-----END PUBLIC KEY-----\n";

        return openssl_verify($mensaje, $firma, $pem, 0) === 1;
    }

    public static function extraer(string $version): void
    {
        $carpeta = self::carpeta($version);
        $destino = "{$carpeta}/extraido";

        File::deleteDirectory($destino);
        @set_time_limit(300);

        if (class_exists(ZipArchive::class)) {
            $zip = new ZipArchive;

            if ($zip->open("{$carpeta}/release.zip") !== true || ! $zip->extractTo($destino)) {
                throw new RuntimeException('No se pudo extraer el zip.');
            }

            $zip->close();
        } else {
            (new PharData("{$carpeta}/release.zip"))->extractTo($destino, null, true);
        }

        $app = "{$destino}/app";

        foreach (['artisan', 'public/index.php', 'public/actualizar.php', 'vendor/autoload.php'] as $archivo) {
            if (! is_file("{$app}/{$archivo}")) {
                throw new RuntimeException("El zip no tiene la estructura esperada (falta app/{$archivo}).");
            }
        }

        if (trim((string) @file_get_contents("{$app}/VERSION")) !== $version) {
            throw new RuntimeException("El zip no corresponde a la versión v{$version}.");
        }

        self::igualarPermisos($app);
    }

    /**
     * Si PHP se ejecuta con otro usuario que el dueño de la app (hostings con PHP como «nobody»), el código
     * nuevo sería de PHP y por FTP ya no se podría sobrescribir. En ese caso se le dan los mismos permisos
     * que tiene la carpeta de la app, que son los que el hosting ya permite.
     */
    private static function igualarPermisos(string $app): void
    {
        if (@fileowner($app) === @fileowner(base_path())) {
            return;
        }

        $modo = fileperms(base_path()) & 0777;
        $elementos = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($app, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST,
        );

        @chmod($app, $modo);

        foreach ($elementos as $elemento) {
            if (! $elemento->isLink()) {
                @chmod($elemento->getPathname(), $elemento->isDir() ? $modo : $modo & 0666);
            }
        }
    }

    /** Deja el plan para public/actualizar.php y devuelve la URL que lo ejecuta. */
    public static function prepararActualizacion(string $version, string $volver): string
    {
        $extraido = self::carpeta("{$version}/extraido");
        $bloque = (string) @file_get_contents("{$extraido}/htaccess-raiz.txt");

        return self::escribirPlan([
            'accion' => 'actualizar',
            'version' => $version,
            'origen' => self::relativa("{$extraido}/app"),
            'entradas' => self::entradas("{$extraido}/app"),
            'htaccess' => dirname(base_path()).'/.htaccess',
            'bloque' => $bloque,
            'legado' => self::sinMarcas($bloque),
        ], $volver);
    }

    /** Versión guardada en la copia de seguridad, o null si no hay ninguna. */
    public static function versionAnterior(): ?string
    {
        $manifiesto = json_decode((string) @file_get_contents(self::carpeta('anterior/.actualizacion.json')), true);

        return is_array($manifiesto) && ! empty($manifiesto['entradas']) ? (string) $manifiesto['version'] : null;
    }

    public static function prepararRestauracion(string $volver): string
    {
        $manifiesto = json_decode((string) @file_get_contents(self::carpeta('anterior/.actualizacion.json')), true);

        if (! is_array($manifiesto) || empty($manifiesto['entradas']) || is_dir(base_path('.git'))) {
            throw new RuntimeException('No hay ninguna versión anterior que restaurar.');
        }

        return self::escribirPlan([
            'accion' => 'restaurar',
            'version' => (string) $manifiesto['version'],
            'origen' => self::relativa(self::carpeta('anterior')),
            'entradas' => array_values($manifiesto['entradas']),
            'en_base' => (array) ($manifiesto['en_base'] ?? []),
        ], $volver);
    }

    /**
     * Resultado del último plan ejecutado (se lee una sola vez).
     *
     * @return array{ok: bool, accion: string, version: string, avisos?: list<string>, error?: string}|null
     */
    public static function resultado(): ?array
    {
        $archivo = self::carpeta('resultado.json');
        $resultado = is_file($archivo) ? json_decode((string) file_get_contents($archivo), true) : null;

        if ($resultado !== null) {
            @unlink($archivo);
            self::limpiar();
        }

        return is_array($resultado) ? $resultado : null;
    }

    /** Borra descargas y restos de actualizaciones anteriores; conserva la copia de seguridad. */
    public static function limpiar(): void
    {
        if (! is_dir(self::carpeta())) {
            return;
        }

        foreach (File::directories(self::carpeta()) as $carpeta) {
            if (basename($carpeta) !== 'anterior') {
                self::borrarApartadasEnBase($carpeta);
                File::deleteDirectory($carpeta);
            }
        }

        @unlink(self::carpeta('plan.json'));
    }

    /**
     * Una copia de seguridad descartada puede tener carpetas renombradas junto al código («en_base»).
     * Se borran si se puede: si las subió otro usuario por FTP, PHP quizá no pueda y quedan ocultas.
     */
    private static function borrarApartadasEnBase(string $copia): void
    {
        $manifiesto = json_decode((string) @file_get_contents("{$copia}/.actualizacion.json"), true);

        foreach ((array) ($manifiesto['en_base'] ?? []) as $nombre) {
            if (is_string($nombre) && preg_match('/^\.[A-Za-z0-9_-][A-Za-z0-9._-]*$/', $nombre)) {
                File::deleteDirectory(base_path($nombre));
            }
        }
    }

    private static function escribirPlan(array $plan, string $volver): string
    {
        $token = Str::random(48);

        File::ensureDirectoryExists(self::carpeta());
        File::deleteDirectory(self::carpeta('reemplazada'));

        $guardado = file_put_contents(self::carpeta('plan.json'), json_encode([
            ...$plan,
            'token' => hash('sha256', $token),
            'expira' => now()->addMinutes(self::MINUTOS_PLAN)->getTimestamp(),
            'copia' => self::relativa(self::carpeta('reemplazada')),
            'final' => self::relativa(self::carpeta('anterior')),
            'conservar' => array_values(array_filter(self::CONSERVAR, fn (string $ruta) => str_contains($ruta, '/'))),
            'volver' => $volver,
        ]));

        if ($guardado === false) {
            throw new RuntimeException('No se pudo escribir el plan de actualización.');
        }

        return asset('actualizar.php').'?token='.$token;
    }

    /**
     * Carpetas y archivos de primer nivel de una app que se sustituyen (todo salvo lo que se conserva).
     *
     * @return list<string>
     */
    private static function entradas(string $app): array
    {
        $excluidas = ['.', '..', '.git', ...array_filter(self::CONSERVAR, fn (string $ruta) => ! str_contains($ruta, '/'))];

        return array_values(array_diff(scandir($app) ?: [], $excluidas));
    }

    private static function relativa(string $ruta): string
    {
        return Str::after($ruta, base_path().'/');
    }

    /** El bloque tal como estaba en el .htaccess raíz antes de llevar marcadores (v1.0.x). */
    private static function sinMarcas(string $bloque): string
    {
        return trim(str_replace([self::MARCA_INICIO, self::MARCA_FIN], '', $bloque));
    }
}
