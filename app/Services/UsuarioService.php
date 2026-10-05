<?php

namespace App\Services;

use App\Enums\Rol;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class UsuarioService
{
    /**
     * Crea un usuario. Sin contraseña se le asigna una aleatoria y se le envía
     * un email de bienvenida con un enlace para establecer la suya.
     */
    public static function crear(string $nombre, string $email, Rol $rol, ?string $password = null, bool $enviarBienvenida = true): User
    {
        $user = User::create([
            'name' => $nombre,
            'email' => Str::lower(trim($email)),
            'rol' => $rol,
            'activo' => true,
            'password' => filled($password) ? $password : Str::password(32),
        ]);

        if ($enviarBienvenida) {
            self::enviarBienvenida($user);
        }

        return $user;
    }

    public static function enviarBienvenida(User $user): void
    {
        $token = Password::broker()->createToken($user);
        $panel = Filament::getPanel($user->rol->panel());

        NotificacionService::encolar(
            tipo: 'bienvenida',
            email: $user->email,
            asunto: 'Tu acceso a ' . config('app.name'),
            contenido: [
                'nombre' => $user->name,
                'parrafos' => [
                    'Se ha creado tu cuenta con el email <strong>' . e($user->email) . '</strong>.',
                    'Para empezar, establece tu contraseña con el siguiente botón. El enlace caduca en '
                        . (int) round(config('auth.passwords.users.expire') / 60) . ' horas; '
                        . 'si caduca, usa "¿Has olvidado tu contraseña?" en la página de acceso.',
                    'Página de acceso: <a href="' . e($panel->getLoginUrl()) . '">' . e($panel->getLoginUrl()) . '</a>',
                ],
                'boton' => ['url' => $panel->getResetPasswordUrl($token, $user), 'texto' => 'Establecer contraseña'],
            ],
            user: $user,
        );
    }

    /**
     * Busca al usuario en el espacio de trabajo de Slack por su email y guarda su ID.
     * Devuelve el ID, o null si no está en el espacio de trabajo.
     */
    public static function vincularSlack(User $user): ?string
    {
        $id = SlackService::buscarPorEmail($user->email);

        if ($id) {
            $user->update(['slack_user_id' => $id]);
        }

        return $id;
    }

    /**
     * Importa usuarios desde un CSV con cabecera. Columnas: nombre, email, rol
     * (opcional: admin o cliente; por defecto cliente) y slack_id (opcional, solo
     * clientes: su ID de usuario de Slack, U…). Acepta coma o punto y coma.
     * Cada fila se valida por separado: las erróneas se devuelven sin abortar el resto.
     *
     * Con $vincularSlack, a los clientes sin slack_id se les busca en Slack por su email.
     *
     * @return array{creados: int, errores: array<int, string>, slack_vinculados: int}
     */
    public static function importarCsv(string $ruta, bool $enviarBienvenida = true, bool $vincularSlack = false): array
    {
        $resultado = ['creados' => 0, 'errores' => [], 'slack_vinculados' => 0];

        $handle = fopen($ruta, 'r');
        $primeraLinea = fgets($handle);
        $separador = substr_count($primeraLinea, ';') > substr_count($primeraLinea, ',') ? ';' : ',';
        rewind($handle);

        $cabecera = array_map(
            fn ($c) => Str::of($c)->replace("\u{FEFF}", '')->trim()->lower()->ascii()->toString(),
            fgetcsv($handle, separator: $separador, escape: '') ?: [],
        );
        $cabecera = array_map(fn ($c) => [
            'name' => 'nombre',
            'correo' => 'email',
            'slack' => 'slack_id',
            'slack_user_id' => 'slack_id',
            'id_slack' => 'slack_id',
        ][$c] ?? $c, $cabecera);

        if (! in_array('nombre', $cabecera) || ! in_array('email', $cabecera)) {
            fclose($handle);
            $resultado['errores'][1] = 'La cabecera debe incluir las columnas "nombre" y "email".';

            return $resultado;
        }

        $linea = 1;
        $emailsVistos = [];

        while (($fila = fgetcsv($handle, separator: $separador, escape: '')) !== false) {
            $linea++;

            if ($fila === [null] || blank(implode('', $fila))) {
                continue;
            }

            $datos = array_combine($cabecera, array_pad(array_slice($fila, 0, count($cabecera)), count($cabecera), null));
            $datos = array_map(fn ($v) => is_string($v) ? trim($v) : $v, $datos);
            $datos['email'] = Str::lower($datos['email'] ?? '');
            $datos['rol'] = Str::lower($datos['rol'] ?? '') ?: Rol::Cliente->value;
            $datos['slack_id'] = Str::upper($datos['slack_id'] ?? '') ?: null;

            $validator = Validator::make($datos, [
                'nombre' => ['required', 'string', 'max:255'],
                'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')],
                'rol' => ['required', Rule::enum(Rol::class)],
                'slack_id' => ['nullable', 'regex:' . SlackService::PATRON_ID_USUARIO, 'prohibited_if:rol,admin'],
            ], [
                'slack_id.regex' => 'El ID de Slack debe tener el formato U0123ABCD (ID de miembro).',
                'slack_id.prohibited_if' => 'El ID de Slack solo se puede asignar a clientes.',
            ], ['nombre' => 'nombre', 'email' => 'email', 'rol' => 'rol', 'slack_id' => 'ID de Slack']);

            if ($validator->fails()) {
                $resultado['errores'][$linea] = implode(' ', $validator->errors()->all());

                continue;
            }

            if (isset($emailsVistos[$datos['email']])) {
                $resultado['errores'][$linea] = "Email repetido en el CSV (ya aparece en la línea {$emailsVistos[$datos['email']]}).";

                continue;
            }

            $emailsVistos[$datos['email']] = $linea;

            $user = DB::transaction(function () use ($datos, $enviarBienvenida) {
                $user = self::crear($datos['nombre'], $datos['email'], Rol::from($datos['rol']), enviarBienvenida: $enviarBienvenida);

                if ($datos['slack_id']) {
                    $user->update(['slack_user_id' => $datos['slack_id']]);
                }

                return $user;
            });
            $resultado['creados']++;

            if ($user->tieneSlack()) {
                $resultado['slack_vinculados']++;
            } elseif ($vincularSlack && $user->rol === Rol::Cliente) {
                try {
                    $resultado['slack_vinculados'] += self::vincularSlack($user) ? 1 : 0;
                } catch (\Throwable $e) {
                    // Un fallo de Slack no impide el alta; el ID se puede vincular después.
                    $resultado['errores'][$linea] = "Usuario creado, pero no se pudo buscar en Slack: {$e->getMessage()}";
                }
            }
        }

        fclose($handle);

        return $resultado;
    }
}
