<?php

namespace Tests\Feature;

use App\Enums\Rol;
use App\Filament\Shared\VerManual;
use App\Models\Manual;
use App\Models\Servicio;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ManualTest extends TestCase
{
    use RefreshDatabase;

    private Servicio $servicio;

    private Manual $manual;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake(Manual::DISCO);
        Storage::disk(Manual::DISCO)->put('manuales/manual.html', '<h1>Manual del hosting</h1>');

        $this->servicio = Servicio::create(['nombre' => 'Hosting']);
        $this->manual = Manual::create([
            'titulo' => 'Manual del hosting',
            'servicio_id' => $this->servicio->id,
            'archivo' => 'manuales/manual.html',
        ]);
    }

    private function cliente(bool $conServicio): User
    {
        $cliente = User::factory()->create(['rol' => Rol::Cliente, 'activo' => true]);

        if ($conServicio) {
            $cliente->servicios()->attach($this->servicio);
        }

        return $cliente;
    }

    public function test_el_cliente_con_el_servicio_ve_el_manual_aislado(): void
    {
        $this->actingAs($this->cliente(conServicio: true))
            ->get(route('manuales.contenido', $this->manual))
            ->assertOk()
            ->assertSee('Manual del hosting')
            ->assertHeader('Content-Security-Policy');

        $this->get(VerManual::getUrl(['manual' => $this->manual], panel: 'portal'))
            ->assertOk()
            ->assertSee(route('manuales.contenido', $this->manual), escape: false);
    }

    public function test_el_cliente_sin_el_servicio_no_ve_el_manual(): void
    {
        $this->actingAs($this->cliente(conServicio: false))
            ->get(route('manuales.contenido', $this->manual))
            ->assertNotFound();

        $this->get(VerManual::getUrl(['manual' => $this->manual], panel: 'portal'))
            ->assertNotFound();
    }

    public function test_un_manual_inactivo_no_se_muestra(): void
    {
        $this->manual->update(['activo' => false]);

        $this->actingAs($this->cliente(conServicio: true))
            ->get(route('manuales.contenido', $this->manual))
            ->assertNotFound();
    }

    public function test_el_menu_ayuda_solo_lista_los_manuales_del_cliente(): void
    {
        Storage::disk(Manual::DISCO)->put('manuales/otro.html', '<h1>Otro</h1>');
        Manual::create([
            'titulo' => 'Manual de otro servicio',
            'servicio_id' => Servicio::create(['nombre' => 'Correo'])->id,
            'archivo' => 'manuales/otro.html',
        ]);

        $this->actingAs($this->cliente(conServicio: true));
        Filament::setCurrentPanel('portal');

        $etiquetas = collect(VerManual::getNavigationItems())->map->getLabel()->all();

        $this->assertSame(['Manual del hosting'], $etiquetas);
    }

    public function test_al_borrar_el_servicio_se_borran_sus_manuales_y_archivos(): void
    {
        $this->servicio->delete();

        $this->assertModelMissing($this->manual);
        Storage::disk(Manual::DISCO)->assertMissing('manuales/manual.html');
    }
}
