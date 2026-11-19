<?php

namespace Tests\Feature;

use App\Enums\NotificacionEstado;
use App\Enums\Rol;
use App\Filament\Marca;
use App\Models\Ajuste;
use App\Models\Notificacion;
use App\Models\PushSuscripcion;
use App\Models\Servicio;
use App\Models\Ticket;
use App\Models\TicketPrioridad;
use App\Models\User;
use App\Services\NotificacionService;
use App\Services\PushService;
use App\Services\TicketService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PushNotificacionesTest extends TestCase
{
    use RefreshDatabase;

    private Servicio $servicio;

    private User $cliente;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->servicio = Servicio::create(['nombre' => 'Hosting', 'activo' => true]);
        $this->cliente = User::factory()->create(['rol' => Rol::Cliente, 'activo' => true]);
        $this->cliente->servicios()->attach($this->servicio);
        $this->admin = User::factory()->create(['rol' => Rol::Admin, 'activo' => true]);
    }

    private function suscribir(User $user): void
    {
        PushService::suscribir($user, [
            'endpoint' => 'https://fcm.googleapis.com/fcm/send/'.$user->id,
            'keys' => ['p256dh' => 'clave-p256dh', 'auth' => 'clave-auth'],
        ]);
    }

    private function crearTicket(string $prioridad, User $autor): Ticket
    {
        return TicketService::crear(
            cliente: $this->cliente,
            servicio: $this->servicio,
            asunto: 'La web no carga',
            mensaje: '<p>Error <strong>500</strong> en la portada</p>',
            autor: $autor,
            prioridad: TicketPrioridad::where('nombre', $prioridad)->firstOrFail(),
        );
    }

    /**
     * @return array<int, int> ids de los usuarios con un push en cola
     */
    private function destinatariosPush(): array
    {
        return Notificacion::where('canal', 'push')->pluck('user_id')->all();
    }

    public function test_la_prioridad_urgente_queda_marcada_al_migrar(): void
    {
        $this->assertTrue(TicketPrioridad::where('nombre', 'Urgente')->value('es_urgente'));
        $this->assertSame(1, TicketPrioridad::where('es_urgente', true)->count());
    }

    public function test_ticket_urgente_del_cliente_avisa_por_push_a_los_admins_suscritos(): void
    {
        $this->suscribir($this->admin);
        $this->suscribir($this->cliente);
        $adminSinSuscripcion = User::factory()->create(['rol' => Rol::Admin, 'activo' => true]);

        $ticket = $this->crearTicket('Urgente', autor: $this->cliente);

        $this->assertSame([$this->admin->id], $this->destinatariosPush());
        $this->assertNotContains($adminSinSuscripcion->id, $this->destinatariosPush());

        $push = Notificacion::where('canal', 'push')->sole();
        $this->assertSame(NotificacionEstado::EnCola, $push->estado);
        $this->assertSame('Urgente · Nuevo ticket', $push->payload['title']);
        $this->assertSame("#{$ticket->numero()} La web no carga\nError 500 en la portada", $push->payload['body']);
        $this->assertStringContainsString("/admin/tickets/{$ticket->id}", $push->payload['url']);
        $this->assertSame("ticket-{$ticket->id}", $push->payload['tag']);
    }

    public function test_ticket_no_urgente_no_avisa_por_push(): void
    {
        $this->suscribir($this->admin);
        $this->suscribir($this->cliente);

        $ticket = $this->crearTicket('Alta', autor: $this->cliente);
        TicketService::responder($ticket, 'Lo revisamos', $this->admin);
        TicketService::responder($ticket, 'Gracias', $this->cliente);

        $this->assertSame([], $this->destinatariosPush());
    }

    public function test_ticket_urgente_abierto_por_soporte_avisa_al_cliente(): void
    {
        $this->suscribir($this->admin);
        $this->suscribir($this->cliente);

        $this->crearTicket('Urgente', autor: $this->admin);

        $this->assertSame([$this->cliente->id], $this->destinatariosPush());
    }

    public function test_respuestas_de_un_ticket_urgente_avisan_a_la_otra_parte(): void
    {
        $ticket = $this->crearTicket('Urgente', autor: $this->cliente);
        $this->suscribir($this->admin);
        $this->suscribir($this->cliente);

        TicketService::responder($ticket, 'Lo estamos revisando', $this->admin);
        $this->assertSame([$this->cliente->id], $this->destinatariosPush());

        TicketService::responder($ticket, 'Sigue fallando', $this->cliente);
        $this->assertSame([$this->cliente->id, $this->admin->id], $this->destinatariosPush());
    }

    public function test_el_envio_falla_si_el_usuario_ya_no_tiene_navegadores_suscritos(): void
    {
        $ticket = $this->crearTicket('Urgente', autor: $this->cliente);
        $this->suscribir($this->cliente);
        TicketService::responder($ticket, 'Lo estamos revisando', $this->admin);
        $this->cliente->pushSuscripciones()->delete();

        $push = Notificacion::where('canal', 'push')->sole();

        $this->assertFalse(NotificacionService::enviar($push));
        $this->assertSame(NotificacionEstado::Error, $push->fresh()->estado);
        $this->assertStringContainsString('ningún navegador', $push->fresh()->error);
    }

    public function test_el_usuario_suscribe_y_desuscribe_su_navegador(): void
    {
        $endpoint = 'https://fcm.googleapis.com/fcm/send/abc123';

        $this->actingAs($this->cliente)
            ->postJson(route('push.suscribir'), [
                'endpoint' => $endpoint,
                'keys' => ['p256dh' => 'clave-p256dh', 'auth' => 'clave-auth'],
            ])
            ->assertNoContent();

        $this->assertSame($this->cliente->id, PushSuscripcion::where('endpoint_hash', PushSuscripcion::hash($endpoint))->value('user_id'));

        // El mismo navegador con otro usuario: la suscripción pasa a ser suya.
        $this->actingAs($this->admin)
            ->postJson(route('push.suscribir'), [
                'endpoint' => $endpoint,
                'keys' => ['p256dh' => 'clave-p256dh', 'auth' => 'clave-auth'],
            ])
            ->assertNoContent();

        $this->assertSame(1, PushSuscripcion::count());
        $this->assertSame($this->admin->id, PushSuscripcion::value('user_id'));

        $this->deleteJson(route('push.desuscribir'), ['endpoint' => $endpoint])->assertNoContent();

        $this->assertSame(0, PushSuscripcion::count());
    }

    public function test_solo_se_admiten_endpoints_de_servicios_push_conocidos(): void
    {
        foreach (['http://fcm.googleapis.com/fcm/send/x', 'https://127.0.0.1/x', 'https://fcm.googleapis.com.example.com/x'] as $endpoint) {
            $this->actingAs($this->cliente)
                ->postJson(route('push.suscribir'), [
                    'endpoint' => $endpoint,
                    'keys' => ['p256dh' => 'clave-p256dh', 'auth' => 'clave-auth'],
                ])
                ->assertUnprocessable();
        }

        $this->assertSame(0, PushSuscripcion::count());
    }

    public function test_hay_que_iniciar_sesion_para_suscribirse(): void
    {
        $this->postJson(route('push.suscribir'), [])->assertUnauthorized();
    }

    public function test_las_claves_vapid_se_generan_una_vez_y_sobreviven_a_restaurar_la_marca(): void
    {
        $clave = PushService::clavePublica();

        $this->assertNotEmpty($clave);

        Marca::restaurar();

        $this->assertNotNull(Ajuste::find('push_vapid'));
        $this->assertSame($clave, json_decode(Ajuste::find('push_vapid')->valor, true)['publicKey']);
    }

    public function test_los_paneles_muestran_la_campana(): void
    {
        $this->actingAs($this->cliente)->followingRedirects()->get('/portal')->assertOk()->assertSee('pushNotificaciones', escape: false);
        $this->actingAs($this->admin)->followingRedirects()->get('/admin')->assertOk()->assertSee('pushNotificaciones', escape: false);
    }
}
