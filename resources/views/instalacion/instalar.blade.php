{{-- Instalador web: se muestra mientras la aplicación no está instalada (ver App\Support\Instalacion). --}}
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>Instalación · {{ config('app.name') }}</title>
    <link rel="icon" href="{{ asset('favicon.ico') }}">
    <link rel="stylesheet" href="https://fonts.bunny.net/css?family=exo-2:600|pt-sans:400,700&display=swap">
    <style>
        * { box-sizing: border-box; }
        body { margin: 0; background: #f1f1f1; color: #333; font: 16px/1.5 'PT Sans', system-ui, sans-serif; }
        main { max-width: 640px; margin: 48px auto; padding: 0 16px; }
        .cabecera { display: flex; align-items: center; gap: 12px; margin-bottom: 24px; }
        .cabecera img { width: 40px; height: 40px; }
        h1, h2 { font-family: 'Exo 2', system-ui, sans-serif; font-weight: 600; color: #0a0a0a; margin: 0; }
        h1 { font-size: 1.6rem; }
        h2 { font-size: 1.1rem; margin-bottom: 4px; }
        .tarjeta { background: #fff; border: 1px solid #ccc; border-radius: 8px; padding: 24px; margin-bottom: 16px; }
        .ayuda { color: #5a5a5a; font-size: .9rem; margin: 0 0 16px; }
        .campos { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }
        .campo { display: flex; flex-direction: column; gap: 4px; }
        .campo--ancho { grid-column: 1 / -1; }
        label { font-weight: 700; font-size: .9rem; color: #0a0a0a; }
        input { font: inherit; padding: 8px 10px; border: 1px solid #ccc; border-radius: 6px; background: #fff; color: #0a0a0a; width: 100%; }
        input:focus { outline: 2px solid #000; outline-offset: 1px; border-color: #000; }
        .error { color: #b00020; font-size: .85rem; }
        .avisos { background: #fff4f4; border-color: #e3a1a1; }
        .avisos ul { margin: 8px 0 0; padding-left: 20px; }
        button { font: 600 1rem 'Exo 2', system-ui, sans-serif; background: #000; color: #fff; border: 0; border-radius: 6px; padding: 12px 24px; cursor: pointer; width: 100%; }
        button:hover { background: #333; }
        button:disabled { background: #8c8c8c; cursor: wait; }
        @media (max-width: 560px) { .campos { grid-template-columns: 1fr; } main { margin: 24px auto; } }
    </style>
</head>
<body>
<main>
    <div class="cabecera">
        <img src="{{ asset('images/marca/mdmg-soporte-icono-negro.svg') }}" alt="">
        <h1>Instalación</h1>
    </div>

    @if ($pendientes)
        <div class="tarjeta avisos">
            <h2>Antes de continuar</h2>
            <p class="ayuda">Corrige lo siguiente en el servidor (por FTP o desde el panel del hosting) y recarga la página.</p>
            <ul>
                @foreach ($pendientes as $pendiente)
                    <li>{{ $pendiente }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    @if ($formulario)
        @if ($errors->hasAny(['db_host', 'instalacion']))
            <div class="tarjeta avisos">
                <p class="error" style="margin:0">{{ $errors->first('instalacion') ?: $errors->first('db_host') }}</p>
            </div>
        @endif

        <form method="POST" action="{{ url('instalar') }}" onsubmit="this.querySelector('button').disabled = true; this.querySelector('button').textContent = 'Instalando…';">
            @csrf

            <section class="tarjeta">
                <h2>Aplicación</h2>
                <p class="ayuda">El nombre se podrá cambiar después en Configuración.</p>
                <div class="campos">
                    <div class="campo">
                        <label for="app_nombre">Nombre</label>
                        <input id="app_nombre" name="app_nombre" value="{{ old('app_nombre', 'MDMG Soporte') }}" required>
                        @error('app_nombre') <span class="error">{{ $message }}</span> @enderror
                    </div>
                    <div class="campo">
                        <label for="app_url">URL</label>
                        <input id="app_url" name="app_url" type="url" value="{{ old('app_url', $url) }}" required>
                        @error('app_url') <span class="error">{{ $message }}</span> @enderror
                    </div>
                </div>
            </section>

            <section class="tarjeta">
                <h2>Base de datos MySQL / MariaDB</h2>
                <p class="ayuda">Crea antes una base de datos vacía y un usuario con todos los permisos sobre ella desde el panel del hosting.</p>
                <div class="campos">
                    <div class="campo">
                        <label for="db_host">Servidor</label>
                        <input id="db_host" name="db_host" value="{{ old('db_host', 'localhost') }}" required>
                    </div>
                    <div class="campo">
                        <label for="db_puerto">Puerto</label>
                        <input id="db_puerto" name="db_puerto" type="number" value="{{ old('db_puerto', 3306) }}" required>
                        @error('db_puerto') <span class="error">{{ $message }}</span> @enderror
                    </div>
                    <div class="campo campo--ancho">
                        <label for="db_nombre">Nombre de la base de datos</label>
                        <input id="db_nombre" name="db_nombre" value="{{ old('db_nombre') }}" required>
                        @error('db_nombre') <span class="error">{{ $message }}</span> @enderror
                    </div>
                    <div class="campo">
                        <label for="db_usuario">Usuario</label>
                        <input id="db_usuario" name="db_usuario" value="{{ old('db_usuario') }}" autocomplete="off" required>
                        @error('db_usuario') <span class="error">{{ $message }}</span> @enderror
                    </div>
                    <div class="campo">
                        <label for="db_password">Contraseña</label>
                        <input id="db_password" name="db_password" type="password" autocomplete="new-password">
                    </div>
                </div>
            </section>

            <section class="tarjeta">
                <h2>Primer administrador</h2>
                <p class="ayuda">Con este usuario entrarás en el panel de administración (/admin).</p>
                <div class="campos">
                    <div class="campo">
                        <label for="admin_nombre">Nombre</label>
                        <input id="admin_nombre" name="admin_nombre" value="{{ old('admin_nombre') }}" required>
                        @error('admin_nombre') <span class="error">{{ $message }}</span> @enderror
                    </div>
                    <div class="campo">
                        <label for="admin_email">Email</label>
                        <input id="admin_email" name="admin_email" type="email" value="{{ old('admin_email') }}" required>
                        @error('admin_email') <span class="error">{{ $message }}</span> @enderror
                    </div>
                    <div class="campo">
                        <label for="admin_password">Contraseña</label>
                        <input id="admin_password" name="admin_password" type="password" autocomplete="new-password" minlength="8" required>
                        @error('admin_password') <span class="error">{{ $message }}</span> @enderror
                    </div>
                    <div class="campo">
                        <label for="admin_password_confirmation">Repite la contraseña</label>
                        <input id="admin_password_confirmation" name="admin_password_confirmation" type="password" autocomplete="new-password" required>
                    </div>
                </div>
            </section>

            <button type="submit">Instalar</button>
        </form>
    @endif
</main>
</body>
</html>
