<?php

namespace App\Http\Middleware;

use App\Support\Instalacion;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * Sin instalar, toda la web lleva al instalador (/instalar); ya instalada, el instalador deja de existir
 * y se aplican las migraciones de una versión recién subida por FTP.
 */
class ComprobarInstalacion
{
    public function handle(Request $request, Closure $next): Response
    {
        if (app()->runningUnitTests()) {
            return $next($request);
        }

        $enInstalador = $request->is('instalar', 'instalar/*');

        if (Instalacion::instalada()) {
            if ($enInstalador) {
                return redirect('/');
            }

            Instalacion::actualizarSiHaceFalta();

            return $next($request);
        }

        // Aún no hay base de datos para las sesiones ni la caché.
        config(['session.driver' => 'file', 'cache.default' => 'file']);

        if (blank(config('app.key'))) {
            try {
                Instalacion::prepararEnv();
            } catch (Throwable $e) {
                return response()->view('instalacion.instalar', [
                    'pendientes' => [$e->getMessage(), ...Instalacion::requisitosPendientes()],
                    'formulario' => false,
                ], 500);
            }

            // Recargar con la APP_KEY nueva (las cookies cifradas la necesitan).
            return redirect('/instalar');
        }

        return $enInstalador ? $next($request) : redirect('/instalar');
    }
}
