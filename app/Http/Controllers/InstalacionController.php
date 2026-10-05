<?php

namespace App\Http\Controllers;

use App\Enums\Rol;
use App\Models\User;
use App\Support\Instalacion;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;
use Throwable;

/**
 * Instalador web para hostings sin SSH: escribe el .env, migra la base de datos y crea el primer administrador.
 * Solo es accesible mientras la aplicación no está instalada (middleware ComprobarInstalacion).
 */
class InstalacionController extends Controller
{
    public function create(Request $request): View
    {
        $pendientes = Instalacion::requisitosPendientes();

        return view('instalacion.instalar', [
            'pendientes' => $pendientes,
            'formulario' => $pendientes === [],
            'url' => $request->root(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $datos = $request->validate([
            'app_nombre' => ['required', 'string', 'max:100'],
            'app_url' => ['required', 'url'],
            'db_host' => ['required', 'string'],
            'db_puerto' => ['required', 'integer', 'between:1,65535'],
            'db_nombre' => ['required', 'string'],
            'db_usuario' => ['required', 'string'],
            'db_password' => ['nullable', 'string'],
            'admin_nombre' => ['required', 'string', 'max:255'],
            'admin_email' => ['required', 'email', 'max:255'],
            'admin_password' => ['required', 'confirmed', Password::min(8)],
        ]);

        @set_time_limit(300);

        // 1. Probar la conexión antes de tocar el .env.
        config([
            'database.default' => 'mysql',
            'database.connections.mysql.host' => $datos['db_host'],
            'database.connections.mysql.port' => $datos['db_puerto'],
            'database.connections.mysql.database' => $datos['db_nombre'],
            'database.connections.mysql.username' => $datos['db_usuario'],
            'database.connections.mysql.password' => (string) $datos['db_password'],
        ]);
        DB::purge('mysql');

        try {
            DB::connection('mysql')->getPdo();
        } catch (Throwable $e) {
            return $this->error('db_host', 'No se pudo conectar con la base de datos: ' . $e->getMessage());
        }

        try {
            // 2. .env de producción con los datos introducidos.
            Instalacion::escribirEnv([
                'APP_NAME' => $datos['app_nombre'],
                'APP_ENV' => 'production',
                'APP_DEBUG' => 'false',
                'APP_URL' => rtrim($datos['app_url'], '/'),
                'APP_LOCALE' => 'es',
                'APP_FAKER_LOCALE' => 'es_ES',
                'LOG_LEVEL' => 'error',
                'DB_CONNECTION' => 'mysql',
                'DB_HOST' => $datos['db_host'],
                'DB_PORT' => $datos['db_puerto'],
                'DB_DATABASE' => $datos['db_nombre'],
                'DB_USERNAME' => $datos['db_usuario'],
                'DB_PASSWORD' => (string) $datos['db_password'],
            ]);

            // 3. php artisan migrate
            Artisan::call('migrate', ['--force' => true]);

            // 4. Primer administrador.
            User::updateOrCreate(['email' => $datos['admin_email']], [
                'name' => $datos['admin_nombre'],
                'password' => $datos['admin_password'],
                'rol' => Rol::Admin,
                'activo' => true,
            ]);

            Instalacion::marcar();
            Artisan::call('optimize:clear');
        } catch (Throwable $e) {
            report($e);

            return $this->error('instalacion', 'La instalación no se completó: ' . $e->getMessage());
        }

        return redirect('/admin');
    }

    private function error(string $campo, string $mensaje): RedirectResponse
    {
        return back()
            ->withErrors([$campo => $mensaje])
            ->withInput(request()->except('db_password', 'admin_password', 'admin_password_confirmation'));
    }
}
