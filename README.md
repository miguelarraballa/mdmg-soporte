# MDMG Soporte

Sistema de tickets de soporte para clientes de MDMG, con integración con Slack y email.

## Publicar una versión

```bash
git tag v1.0.1
git push origin v1.0.1
```

La acción `.github/workflows/release.yml` ejecuta `composer install --no-dev`, `npm install` y `npm run build`, y adjunta a la release de GitHub un `mdmg-soporte-v1.0.1.zip` listo para subir. La versión (archivo `VERSION`) se toma del tag.

## Instalación en un hosting sin SSH

Requisitos: PHP 8.4 o superior con `pdo_mysql`, `mbstring`, `openssl`, `intl`, `fileinfo` y `curl`, y una base de datos MySQL/MariaDB.

1. Crea una base de datos vacía y un usuario con permisos sobre ella desde el panel del hosting.
2. Descomprime el zip de la release y sube su contenido por FTP a `public_html` (queda `public_html/.htaccess` y `public_html/app/`). El `.htaccess` raíz envía las peticiones a `app/public` y bloquea el acceso directo a `app/`.
3. Abre la web en el navegador. Mientras no esté instalada, todas las páginas llevan a `/instalar`, que:
   - crea el `.env` y genera `APP_KEY` (`php artisan key:generate`),
   - pide los datos de la base de datos y comprueba la conexión,
   - ejecuta las migraciones (`php artisan migrate`),
   - crea el primer administrador.

Al terminar, `/instalar` deja de estar disponible. El resto de ajustes (correo, Slack) se configuran en el `.env` por FTP.

Para las notificaciones por email y Slack hace falta un cron que ejecute cada minuto `php artisan schedule:run` (la mayoría de paneles de hosting permiten añadir cron jobs sin SSH).

## Actualizar

Sube por FTP el contenido del zip de la nueva versión sobre la instalación existente (sin borrar `app/.env` ni `app/storage`). En la primera visita se ejecutan las migraciones pendientes y se limpian las cachés automáticamente.

## Desarrollo

```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate
npm install && npm run build
```

Cada instalación de cliente define su propio nombre en `APP_NAME` dentro del `.env` (o desde Configuración). Si no se define, se usa `MDMG Soporte`.
