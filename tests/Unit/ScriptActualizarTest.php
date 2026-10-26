<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use RuntimeException;

define('MDMG_ACTUALIZAR_SIN_PETICION', true);

require_once __DIR__.'/../../public/actualizar.php';

/** public/actualizar.php: sustitución del código, vuelta atrás y bloque del .htaccess raíz. */
class ScriptActualizarTest extends TestCase
{
    private string $base;

    private const BLOQUE = "# BEGIN MDMG Soporte\nRewriteEngine On\nRewriteRule ^(.*)$ app/public/$1 [L]\n# END MDMG Soporte";

    private const LEGADO = "RewriteEngine On\nRewriteRule ^(.*)$ app/public/$1 [L]";

    protected function setUp(): void
    {
        parent::setUp();

        $this->base = sys_get_temp_dir().'/mdmg-actualizar-'.bin2hex(random_bytes(6));

        // Versión instalada 1.0.0, con un logo subido y su .env
        $this->escribir('VERSION', '1.0.0');
        $this->escribir('.env', 'APP_KEY=secreta');
        $this->escribir('app/Viejo.php', 'viejo');
        $this->escribir('public/index.php', 'index viejo');
        $this->escribir('public/images/marca/subidas/logo.png', 'mi logo');
        $this->escribir('storage/logs/laravel.log', 'log');

        // Versión 1.1.0 extraída
        $nueva = 'storage/app/actualizacion/1.1.0/extraido/app';
        $this->escribir("{$nueva}/VERSION", '1.1.0');
        $this->escribir("{$nueva}/app/Nuevo.php", 'nuevo');
        $this->escribir("{$nueva}/public/index.php", 'index nuevo');
        $this->escribir("{$nueva}/public/images/marca/icono.svg", 'icono');
        $this->escribir("{$nueva}/vendor/autoload.php", 'autoload');
    }

    protected function tearDown(): void
    {
        exec('rm -rf '.escapeshellarg($this->base));

        parent::tearDown();
    }

    private function escribir(string $ruta, string $contenido): void
    {
        @mkdir(dirname("{$this->base}/{$ruta}"), 0755, true);
        file_put_contents("{$this->base}/{$ruta}", $contenido);
    }

    private function leer(string $ruta): ?string
    {
        return is_file("{$this->base}/{$ruta}") ? file_get_contents("{$this->base}/{$ruta}") : null;
    }

    private function plan(array $cambios = []): array
    {
        return [
            'accion' => 'actualizar',
            'version' => '1.1.0',
            'origen' => 'storage/app/actualizacion/1.1.0/extraido/app',
            'entradas' => ['VERSION', 'app', 'public', 'vendor'],
            'copia' => 'storage/app/actualizacion/reemplazada',
            'final' => 'storage/app/actualizacion/anterior',
            'conservar' => ['public/images/marca/subidas', 'public/storage'],
            ...$cambios,
        ];
    }

    public function test_sustituye_el_codigo_y_conserva_env_storage_y_archivos_subidos(): void
    {
        $avisos = mdmg_aplicar_plan($this->base, $this->plan());

        $this->assertSame([], $avisos);
        $this->assertSame('1.1.0', $this->leer('VERSION'));
        $this->assertSame('nuevo', $this->leer('app/Nuevo.php'));
        $this->assertNull($this->leer('app/Viejo.php'));
        $this->assertSame('index nuevo', $this->leer('public/index.php'));
        $this->assertSame('icono', $this->leer('public/images/marca/icono.svg'));
        $this->assertSame('mi logo', $this->leer('public/images/marca/subidas/logo.png'));
        $this->assertSame('APP_KEY=secreta', $this->leer('.env'));
        $this->assertSame('log', $this->leer('storage/logs/laravel.log'));
    }

    public function test_guarda_la_version_sustituida_para_poder_restaurarla(): void
    {
        mdmg_aplicar_plan($this->base, $this->plan());

        $anterior = 'storage/app/actualizacion/anterior';
        $this->assertSame('viejo', $this->leer("{$anterior}/app/Viejo.php"));
        $this->assertSame(
            ['version' => '1.0.0', 'entradas' => ['VERSION', 'app', 'public', 'vendor']],
            json_decode($this->leer("{$anterior}/.actualizacion.json"), true),
        );

        // Restaurar es el mismo plan con la copia como origen
        mdmg_aplicar_plan($this->base, $this->plan([
            'accion' => 'restaurar',
            'origen' => $anterior,
            'entradas' => ['VERSION', 'app', 'public'],
        ]));

        $this->assertSame('1.0.0', $this->leer('VERSION'));
        $this->assertSame('viejo', $this->leer('app/Viejo.php'));
        $this->assertSame('mi logo', $this->leer('public/images/marca/subidas/logo.png'));
        $this->assertSame('1.1.0', $this->leer("{$anterior}/VERSION"));
    }

    public function test_si_falla_a_mitad_deshace_lo_movido(): void
    {
        try {
            mdmg_aplicar_plan($this->base, $this->plan(['entradas' => ['VERSION', 'app', 'no-existe-en-el-zip', 'public']]));
            $this->fail('Tenía que fallar');
        } catch (RuntimeException) {
        }

        $this->assertSame('1.0.0', $this->leer('VERSION'));
        $this->assertSame('viejo', $this->leer('app/Viejo.php'));
        $this->assertSame('index viejo', $this->leer('public/index.php'));
        $this->assertSame('mi logo', $this->leer('public/images/marca/subidas/logo.png'));
        $this->assertSame('nuevo', $this->leer('storage/app/actualizacion/1.1.0/extraido/app/app/Nuevo.php'));
    }

    public function test_rechaza_rutas_fuera_de_la_carpeta_de_actualizacion(): void
    {
        $this->expectException(RuntimeException::class);

        mdmg_aplicar_plan($this->base, $this->plan(['origen' => 'storage/app/actualizacion/../../../etc']));
    }

    public function test_el_token_es_de_un_solo_uso(): void
    {
        $this->escribir('storage/app/actualizacion/plan.json', json_encode([
            ...$this->plan(),
            'token' => hash('sha256', 'secreto'),
            'expira' => time() + 60,
        ]));

        $this->assertSame('1.1.0', mdmg_leer_plan($this->base, 'secreto')['version']);

        $this->expectException(RuntimeException::class);
        mdmg_leer_plan($this->base, 'secreto');
    }

    public function test_rechaza_un_token_incorrecto_o_caducado(): void
    {
        $this->escribir('storage/app/actualizacion/plan.json', json_encode(['token' => hash('sha256', 'secreto'), 'expira' => time() + 60]));

        try {
            mdmg_leer_plan($this->base, 'otro');
            $this->fail('Tenía que rechazar el token');
        } catch (RuntimeException) {
        }

        $this->escribir('storage/app/actualizacion/plan.json', json_encode(['token' => hash('sha256', 'secreto'), 'expira' => time() - 1]));

        $this->expectException(RuntimeException::class);
        mdmg_leer_plan($this->base, 'secreto');
    }

    public function test_sustituye_solo_el_bloque_marcado_del_htaccess(): void
    {
        $this->escribir('raiz.htaccess', "AddHandler application/x-httpd-php84 .php\n\n# BEGIN MDMG Soporte\nviejo\n# END MDMG Soporte\n\nphp_value upload_max_filesize 64M\n");

        $this->assertNull(mdmg_actualizar_htaccess("{$this->base}/raiz.htaccess", self::BLOQUE, self::LEGADO));
        $this->assertSame(
            "AddHandler application/x-httpd-php84 .php\n\n".self::BLOQUE."\n\nphp_value upload_max_filesize 64M\n",
            $this->leer('raiz.htaccess'),
        );
    }

    public function test_pone_marcadores_al_bloque_de_las_instalaciones_antiguas(): void
    {
        $this->escribir('raiz.htaccess', "AddHandler application/x-httpd-php84 .php\r\n".str_replace("\n", "\r\n", self::LEGADO)."\r\n");

        $this->assertNull(mdmg_actualizar_htaccess("{$this->base}/raiz.htaccess", self::BLOQUE, self::LEGADO));
        $this->assertSame("AddHandler application/x-httpd-php84 .php\n".self::BLOQUE."\n", $this->leer('raiz.htaccess'));
    }

    public function test_avisa_sin_tocarlo_si_el_htaccess_no_tiene_el_bloque(): void
    {
        $this->escribir('raiz.htaccess', "RewriteEngine On\nRewriteRule ^ otra-cosa [L]\n");

        $this->assertNotNull(mdmg_actualizar_htaccess("{$this->base}/raiz.htaccess", self::BLOQUE, self::LEGADO));
        $this->assertSame("RewriteEngine On\nRewriteRule ^ otra-cosa [L]\n", $this->leer('raiz.htaccess'));
    }
}
