# MDMG Soporte

Sistema de tickets de soporte para clientes de MDMG, con integración con Slack y email.

## Instalación

```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate
npm install && npm run build
```

Cada instalación de cliente define su propio nombre en `APP_NAME` dentro del `.env`. Si no se define, se usa `MDMG Soporte`.
