{{--
    Campana de la barra superior (admin y portal): activa o desactiva las notificaciones
    push de tickets urgentes en este navegador. Solo se muestra si el navegador las admite
    (en iPhone/iPad, solo con la web añadida a la pantalla de inicio).
--}}
<div
    x-data="pushNotificaciones({
        clave: @js(\App\Services\PushService::clavePublica()),
        serviceWorker: @js(asset('sw-push.js')),
        url: @js(route('push.suscribir')),
    })"
    x-show="soportado && listo"
    x-cloak
    style="display: flex; align-items: center"
>
    <span x-show="activo">
        <x-filament::icon-button
            icon="heroicon-s-bell-alert"
            color="primary"
            label="Desactivar notificaciones push"
            tooltip="Notificaciones push de tickets urgentes activadas. Pulsa para desactivarlas en este navegador."
            x-on:click="desactivar()"
            x-bind:disabled="cargando"
        />
    </span>

    <span x-show="! activo && ! bloqueado">
        <x-filament::icon-button
            icon="heroicon-o-bell"
            color="gray"
            label="Activar notificaciones push"
            tooltip="Activar notificaciones push de tickets urgentes en este navegador"
            x-on:click="activar()"
            x-bind:disabled="cargando"
        />
    </span>

    <span x-show="! activo && bloqueado">
        <x-filament::icon-button
            icon="heroicon-o-bell-slash"
            color="gray"
            label="Notificaciones push bloqueadas"
            tooltip="Las notificaciones están bloqueadas para esta web. Permítelas en los ajustes del navegador para activarlas."
        />
    </span>
</div>

@once
    <script>
        document.addEventListener('alpine:init', () => {
            Alpine.data('pushNotificaciones', ({ clave, serviceWorker, url }) => ({
                soportado: 'serviceWorker' in navigator && 'PushManager' in window && 'Notification' in window,
                listo: false,
                activo: false,
                bloqueado: false,
                cargando: false,

                async init() {
                    if (! this.soportado) {
                        return;
                    }

                    try {
                        this.bloqueado = Notification.permission === 'denied';

                        const registro = await navigator.serviceWorker.register(serviceWorker);
                        const suscripcion = await registro.pushManager.getSubscription();

                        this.activo = Boolean(suscripcion) && Notification.permission === 'granted' && this.mismaClave(suscripcion);

                        // Se vuelve a enviar por si en este navegador ha entrado otro usuario.
                        if (this.activo) {
                            await this.peticion('POST', suscripcion.toJSON());
                        }
                    } catch (error) {
                        console.warn('Notificaciones push:', error);
                    } finally {
                        this.listo = true;
                    }
                },

                async activar() {
                    this.cargando = true;

                    try {
                        const permiso = await Notification.requestPermission();

                        if (permiso !== 'granted') {
                            this.bloqueado = permiso === 'denied';

                            return;
                        }

                        const registro = await navigator.serviceWorker.ready;
                        let suscripcion = await registro.pushManager.getSubscription();

                        // Suscripción con otras claves VAPID (p. ej. tras reinstalar): se rehace.
                        if (suscripcion && ! this.mismaClave(suscripcion)) {
                            await suscripcion.unsubscribe();
                            suscripcion = null;
                        }

                        suscripcion ??= await registro.pushManager.subscribe({
                            userVisibleOnly: true,
                            applicationServerKey: this.bytes(clave),
                        });

                        await this.peticion('POST', suscripcion.toJSON());

                        this.activo = true;
                        this.aviso('Notificaciones push activadas en este navegador', 'success');
                    } catch (error) {
                        console.warn('Notificaciones push:', error);
                        this.aviso('No se han podido activar las notificaciones push', 'danger');
                    } finally {
                        this.cargando = false;
                    }
                },

                async desactivar() {
                    this.cargando = true;

                    try {
                        const registro = await navigator.serviceWorker.ready;
                        const suscripcion = await registro.pushManager.getSubscription();

                        if (suscripcion) {
                            await this.peticion('DELETE', { endpoint: suscripcion.endpoint });
                            await suscripcion.unsubscribe();
                        }

                        this.activo = false;
                        this.aviso('Notificaciones push desactivadas en este navegador', 'success');
                    } catch (error) {
                        console.warn('Notificaciones push:', error);
                        this.aviso('No se han podido desactivar las notificaciones push', 'danger');
                    } finally {
                        this.cargando = false;
                    }
                },

                async peticion(metodo, datos) {
                    const respuesta = await fetch(url, {
                        method: metodo,
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? '',
                        },
                        body: JSON.stringify(datos),
                    });

                    if (! respuesta.ok) {
                        throw new Error(`HTTP ${respuesta.status}`);
                    }
                },

                mismaClave(suscripcion) {
                    const actual = suscripcion.options?.applicationServerKey;

                    if (! actual) {
                        return true;
                    }

                    const a = new Uint8Array(actual);
                    const b = this.bytes(clave);

                    return a.length === b.length && a.every((byte, i) => byte === b[i]);
                },

                bytes(base64url) {
                    const base64 = (base64url + '='.repeat((4 - base64url.length % 4) % 4)).replace(/-/g, '+').replace(/_/g, '/');

                    return Uint8Array.from(atob(base64), (c) => c.charCodeAt(0));
                },

                aviso(titulo, tipo) {
                    if (window.FilamentNotification) {
                        new FilamentNotification().title(titulo).status(tipo).send();
                    }
                },
            }));
        });
    </script>
@endonce
