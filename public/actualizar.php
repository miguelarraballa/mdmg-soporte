<?php

/**
 * Sustitución del código en una actualización (o restauración) lanzada desde Configuración.
 *
 * Laravel (App\Support\Actualizador) deja la versión ya descargada, verificada y extraída, escribe el plan
 * en storage/app/actualizacion/plan.json y redirige aquí con un token de un solo uso. Este script no carga
 * Laravel, así que no le afecta que el código cambie mientras se ejecuta: mueve las carpetas con rename()
 * (mismo disco, casi instantáneo) y, si algo falla, deshace lo movido. La petición siguiente ya arranca con
 * la versión nueva y aplica las migraciones (Instalacion::actualizarSiHaceFalta).
 *
 * El plan lo escribe la versión instalada y lo ejecuta este mismo archivo: su formato tiene que seguir
 * siendo compatible entre versiones.
 */
const MDMG_CARPETA = 'storage/app/actualizacion';
const MDMG_MARCA_INICIO = '# BEGIN MDMG Soporte';
const MDMG_MARCA_FIN = '# END MDMG Soporte';

/** Lee el plan y comprueba el token (de un solo uso: el plan se borra al leerlo). */
function mdmg_leer_plan(string $base, string $token): array
{
    $archivo = $base.'/'.MDMG_CARPETA.'/plan.json';
    $plan = is_file($archivo) ? json_decode((string) file_get_contents($archivo), true) : null;

    if (! is_array($plan) || $token === '' || ! hash_equals((string) ($plan['token'] ?? ''), hash('sha256', $token))) {
        throw new RuntimeException('El enlace de actualización no es válido. Vuelve a Configuración e inténtalo de nuevo.');
    }

    @unlink($archivo);

    if ((int) ($plan['expira'] ?? 0) < time()) {
        throw new RuntimeException('El enlace de actualización ha caducado. Vuelve a Configuración e inténtalo de nuevo.');
    }

    return $plan;
}

/** Ruta relativa a la app, sin «..» ni caracteres raros. */
function mdmg_ruta(string $ruta, bool $enCarpeta = false): string
{
    $segmentos = explode('/', $ruta);

    foreach ($segmentos as $segmento) {
        if ($segmento === '' || $segmento === '.' || $segmento === '..' || ! preg_match('/^[A-Za-z0-9._-]+$/', $segmento)) {
            throw new RuntimeException("Ruta no válida en el plan: {$ruta}");
        }
    }

    if ($enCarpeta && ! str_starts_with($ruta, MDMG_CARPETA.'/')) {
        throw new RuntimeException("Ruta fuera de la carpeta de actualización: {$ruta}");
    }

    return $ruta;
}

/**
 * Pone en la app las entradas de «origen» y deja las actuales en «copia», que acaba siendo «final»
 * (la copia de seguridad que permite restaurar). Devuelve los avisos que no impiden la actualización.
 *
 * @return list<string>
 */
function mdmg_aplicar_plan(string $base, array $plan): array
{
    $origen = $base.'/'.mdmg_ruta((string) $plan['origen'], true);
    $copia = $base.'/'.mdmg_ruta((string) $plan['copia'], true);
    $final = $base.'/'.mdmg_ruta((string) $plan['final'], true);
    $entradas = array_map(fn ($entrada) => mdmg_ruta((string) $entrada), (array) $plan['entradas']);
    $conservar = array_map(fn ($ruta) => mdmg_ruta((string) $ruta), (array) ($plan['conservar'] ?? []));

    if (! is_dir($origen) || $entradas === []) {
        throw new RuntimeException('No se encuentran los archivos de la versión que se iba a instalar.');
    }

    if (file_exists($copia) || ! @mkdir($copia, 0755, true)) {
        throw new RuntimeException('No se pudo preparar la copia de seguridad de la versión actual.');
    }

    $version = is_file($base.'/VERSION') ? trim((string) file_get_contents($base.'/VERSION')) : '';
    $hechos = [];
    $mover = function (string $de, string $a) use (&$hechos): void {
        if (! @rename($de, $a)) {
            throw new RuntimeException('No se pudo mover '.basename($de).'. No se ha cambiado nada.');
        }

        $hechos[] = [$de, $a];
    };

    try {
        // Lo que no viene en el zip (logos subidos, enlaces de storage) pasa del código actual al nuevo.
        foreach ($conservar as $indice => $ruta) {
            if (! file_exists($base.'/'.$ruta) && ! is_link($base.'/'.$ruta)) {
                continue;
            }

            $destino = $origen.'/'.$ruta;

            if (file_exists($destino) || is_link($destino)) {
                $mover($destino, $copia.'/.descartado-'.$indice);
            }

            if (! is_dir(dirname($destino)) && ! @mkdir(dirname($destino), 0755, true)) {
                throw new RuntimeException('No se pudo preparar '.dirname($ruta).'. No se ha cambiado nada.');
            }

            $mover($base.'/'.$ruta, $destino);
        }

        foreach ($entradas as $entrada) {
            if (file_exists($base.'/'.$entrada) || is_link($base.'/'.$entrada)) {
                $mover($base.'/'.$entrada, $copia.'/'.$entrada);
            }

            $mover($origen.'/'.$entrada, $base.'/'.$entrada);
        }
    } catch (Throwable $e) {
        foreach (array_reverse($hechos) as [$de, $a]) {
            @rename($a, $de);
        }

        throw $e;
    }

    file_put_contents($copia.'/.actualizacion.json', json_encode(['version' => $version, 'entradas' => $entradas]));

    // La copia de seguridad anterior se aparta (Laravel la borra después) y esta ocupa su lugar.
    if (file_exists($final)) {
        @rename($final, $base.'/'.MDMG_CARPETA.'/descartada-'.time());
    }

    @rename($copia, $final);

    $avisos = [];

    if (! empty($plan['htaccess']) && trim((string) ($plan['bloque'] ?? '')) !== '') {
        $aviso = mdmg_actualizar_htaccess((string) $plan['htaccess'], (string) $plan['bloque'], (string) ($plan['legado'] ?? ''));

        if ($aviso !== null) {
            $avisos[] = $aviso;
        }
    }

    clearstatcache(true);

    if (function_exists('opcache_reset')) {
        @opcache_reset();
    }

    return $avisos;
}

/**
 * Sustituye en el .htaccess raíz solo el bloque entre marcadores, sin tocar lo que haya añadido el hosting.
 * Las instalaciones anteriores a los marcadores tienen el bloque sin ellos («legado»): se reemplaza igual.
 */
function mdmg_actualizar_htaccess(string $archivo, string $bloque, string $legado): ?string
{
    $bloque = trim(str_replace("\r\n", "\n", $bloque));
    $legado = trim(str_replace("\r\n", "\n", $legado));
    $actual = is_file($archivo) ? str_replace("\r\n", "\n", (string) file_get_contents($archivo)) : null;

    if ($actual === null) {
        return 'No se encontró el .htaccess raíz: revisa que tenga el bloque de htaccess-raiz.txt.';
    }

    $patron = '/^'.preg_quote(MDMG_MARCA_INICIO, '/').'$.*?^'.preg_quote(MDMG_MARCA_FIN, '/').'$/ms';

    if (preg_match($patron, $actual)) {
        $nuevo = preg_replace_callback($patron, fn () => $bloque, $actual, 1);
    } elseif ($legado !== '' && str_contains($actual, $legado)) {
        $posicion = strpos($actual, $legado);
        $nuevo = substr_replace($actual, $bloque, $posicion, strlen($legado));
    } else {
        return 'El .htaccess raíz no tiene el bloque «'.MDMG_MARCA_INICIO.'»: revísalo a mano con htaccess-raiz.txt.';
    }

    if ($nuevo === $actual) {
        return null;
    }

    if (! is_writable($archivo) || file_put_contents($archivo, $nuevo, LOCK_EX) === false) {
        return 'No se pudo escribir el .htaccess raíz: sustituye a mano el bloque «'.MDMG_MARCA_INICIO.'».';
    }

    return null;
}

function mdmg_guardar_resultado(string $base, array $resultado): void
{
    @file_put_contents($base.'/'.MDMG_CARPETA.'/resultado.json', json_encode($resultado));
}

// Los tests cargan las funciones sin atender ninguna petición.
if (! defined('MDMG_ACTUALIZAR_SIN_PETICION')) {
    header('Cache-Control: no-store');
    @set_time_limit(300);
    ignore_user_abort(true);

    $base = dirname(__DIR__);

    try {
        $plan = mdmg_leer_plan($base, (string) ($_GET['token'] ?? ''));
    } catch (Throwable $e) {
        http_response_code(403);
        header('Content-Type: text/html; charset=utf-8');
        echo '<!doctype html><meta charset="utf-8"><title>Actualización</title>'
            .'<p style="font-family:sans-serif">'.htmlspecialchars($e->getMessage()).'</p>'
            .'<p style="font-family:sans-serif"><a href="/">Volver</a></p>';

        exit;
    }

    try {
        $avisos = mdmg_aplicar_plan($base, $plan);
        mdmg_guardar_resultado($base, ['ok' => true, 'accion' => $plan['accion'] ?? 'actualizar', 'version' => $plan['version'] ?? '', 'avisos' => $avisos]);
    } catch (Throwable $e) {
        mdmg_guardar_resultado($base, ['ok' => false, 'accion' => $plan['accion'] ?? 'actualizar', 'version' => $plan['version'] ?? '', 'error' => $e->getMessage()]);
    }

    header('Location: '.(string) $plan['volver'], true, 303);
}
