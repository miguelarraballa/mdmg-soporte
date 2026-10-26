# MDMG Soporte

Sistema de tickets de soporte para clientes de MDMG, con integración con Slack y email.

## Publicar una versión

```bash
git tag v1.0.1
git push origin v1.0.1
```

La acción `.github/workflows/release.yml` ejecuta `composer install --no-dev`, `npm install` y `npm run build`, y adjunta a la release de GitHub un `mdmg-soporte-v1.0.1.zip` listo para subir, con su `.sha256` y su firma `.sig`. La versión (archivo `VERSION`) se toma del tag.

La firma (ed25519) necesita el secreto `RELEASE_FIRMA_CLAVE` en el repositorio (Settings → Secrets and variables → Actions): sin él la release no se publica. La clave pública correspondiente está en `config/app.php` (`clave_firma`); si se cambia la clave privada, hay que cambiar también la pública, y las instalaciones con la clave antigua tendrán que actualizarse una vez por FTP.

## Instalación en un hosting sin SSH

Requisitos: PHP 8.4 o superior con `pdo_mysql`, `mbstring`, `openssl`, `intl`, `fileinfo` y `curl`, y una base de datos MySQL/MariaDB.

1. Crea una base de datos vacía y un usuario con permisos sobre ella desde el panel del hosting.
2. Descomprime el zip de la release y sube su contenido por FTP a `public_html` (queda `public_html/app/` y `public_html/htaccess-raiz.txt`).
3. Añade a `public_html/.htaccess` el bloque de `htaccess-raiz.txt`, que envía las peticiones a `app/public` y bloquea el acceso directo a `app/` (sin él, `app/.env` queda accesible desde la web):
   - si no hay `.htaccess`, renombra `htaccess-raiz.txt` a `.htaccess`;
   - si ya hay uno (el hosting suele añadir sus directivas), pega el bloque **al final**, sin borrar lo que haya: las reglas de redirección del hosting (por ejemplo, forzar HTTPS) tienen que ir antes.

   Después puedes borrar `htaccess-raiz.txt`.
4. Abre la web en el navegador. Mientras no esté instalada, todas las páginas llevan a `/instalar`, que:
   - crea el `.env` y genera `APP_KEY` (`php artisan key:generate`),
   - pide los datos de la base de datos y comprueba la conexión,
   - ejecuta las migraciones (`php artisan migrate`),
   - crea el primer administrador.

Al terminar, `/instalar` deja de estar disponible. El resto de ajustes (correo, Slack) se configuran en el `.env` por FTP.

Para las notificaciones por email y Slack hace falta un cron que ejecute cada minuto `php artisan schedule:run` (la mayoría de paneles de hosting permiten añadir cron jobs sin SSH). El scheduler también procesa la cola de Laravel (`queue:work --stop-when-empty`), por donde salen los emails de Filament como la recuperación de contraseña.

## Actualizar

### Desde Configuración

Cuando hay una release nueva, en **Configuración** aparece el botón **Actualizar a vX.Y.Z**. Por pasos, y sin salir de la página:

1. comprueba el servidor (extensiones `zip` y `sodium`, 300 MB libres y permisos de escritura sobre las carpetas de la app);
2. descarga el zip de la release de GitHub;
3. verifica su firma con la clave pública de `config/app.php` (si no coincide, se descarta y no se cambia nada);
4. lo extrae en `app/storage/app/actualizacion`;
5. `app/public/actualizar.php` sustituye las carpetas de código (se conservan `app/.env`, `app/storage` y los logos subidos); si algo falla a mitad, deshace lo movido.

La petición siguiente ejecuta las migraciones pendientes. La versión sustituida se guarda en `app/storage/app/actualizacion/anterior` y el botón **Volver a vX.Y.Z** la restaura (sin deshacer las migraciones). Si el bloque `# BEGIN MDMG Soporte` … `# END MDMG Soporte` del `.htaccess` raíz cambia, también se sustituye, sin tocar lo que haya añadido el hosting; si no se puede, aparece un aviso.

Si el hosting no lo permite (por ejemplo, archivos que PHP no puede modificar), el primer paso lo indica y hay que actualizar por FTP. En una copia de desarrollo (con `.git`) el actualizador no se ejecuta.

### Por FTP

Sube por FTP el contenido del zip de la nueva versión sobre la instalación existente (sin borrar `app/.env` ni `app/storage`). En la primera visita se ejecutan las migraciones pendientes y se limpian las cachés automáticamente.

El zip no incluye `public_html/.htaccess`, así que la actualización por FTP no lo toca. Solo si cambia el bloque entre `# BEGIN MDMG Soporte` y `# END MDMG Soporte` de `htaccess-raiz.txt` (lo indicarán las notas de la versión) hay que sustituirlo a mano.

## Desarrollo

```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate
npm install && npm run build
```

Cada instalación de cliente define su propio nombre en `APP_NAME` dentro del `.env` (o desde Configuración). Si no se define, se usa `MDMG Soporte`.
