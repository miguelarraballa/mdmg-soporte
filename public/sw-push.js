/*
 * Service worker de las notificaciones push (tickets urgentes).
 * Se registra desde resources/views/filament/push-notificaciones.blade.php.
 * Debe servirse desde la raíz del sitio para que su ámbito cubra /admin y /portal.
 */
self.addEventListener('push', (event) => {
    let datos = {};

    try {
        datos = event.data ? event.data.json() : {};
    } catch (e) {
        datos = { body: event.data ? event.data.text() : '' };
    }

    event.waitUntil(
        self.registration.showNotification(datos.title || 'Nueva notificación', {
            body: datos.body || '',
            icon: datos.icon || undefined,
            tag: datos.tag || undefined,
            renotify: Boolean(datos.tag),
            requireInteraction: true,
            data: { url: datos.url || '/' },
        }),
    );
});

self.addEventListener('notificationclick', (event) => {
    event.notification.close();

    const url = new URL(event.notification.data?.url || '/', self.location.origin).href;

    event.waitUntil(
        self.clients.matchAll({ type: 'window', includeUncontrolled: true }).then((ventanas) => {
            for (const ventana of ventanas) {
                if (ventana.url === url && 'focus' in ventana) {
                    return ventana.focus();
                }
            }

            return self.clients.openWindow(url);
        }),
    );
});
