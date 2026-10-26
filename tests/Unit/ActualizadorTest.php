<?php

namespace Tests\Unit;

use App\Support\Actualizador;
use PHPUnit\Framework\TestCase;

class ActualizadorTest extends TestCase
{
    private string $zip;

    private string $clavePrivada;

    private string $clavePublica;

    protected function setUp(): void
    {
        parent::setUp();

        $this->zip = tempnam(sys_get_temp_dir(), 'release');
        file_put_contents($this->zip, random_bytes(4096));

        $par = sodium_crypto_sign_keypair();
        $this->clavePrivada = sodium_crypto_sign_secretkey($par);
        $this->clavePublica = base64_encode(sodium_crypto_sign_publickey($par));
    }

    protected function tearDown(): void
    {
        @unlink($this->zip);

        parent::tearDown();
    }

    /** Igual que el paso «Firmar el zip» de release.yml. */
    private function firmar(string $zip, string $clavePrivada): string
    {
        return base64_encode(sodium_crypto_sign_detached(hash_file('sha256', $zip, true), $clavePrivada)).PHP_EOL;
    }

    public function test_acepta_un_zip_firmado_con_la_clave_de_las_releases(): void
    {
        $this->assertTrue(Actualizador::firmaValida($this->zip, $this->firmar($this->zip, $this->clavePrivada), $this->clavePublica));
    }

    public function test_rechaza_un_zip_modificado_despues_de_firmarlo(): void
    {
        $firma = $this->firmar($this->zip, $this->clavePrivada);
        file_put_contents($this->zip, 'x', FILE_APPEND);

        $this->assertFalse(Actualizador::firmaValida($this->zip, $firma, $this->clavePublica));
    }

    public function test_rechaza_un_zip_firmado_con_otra_clave(): void
    {
        $otra = sodium_crypto_sign_secretkey(sodium_crypto_sign_keypair());

        $this->assertFalse(Actualizador::firmaValida($this->zip, $this->firmar($this->zip, $otra), $this->clavePublica));
    }

    public function test_rechaza_firmas_vacias_o_mal_formadas(): void
    {
        $this->assertFalse(Actualizador::firmaValida($this->zip, '', $this->clavePublica));
        $this->assertFalse(Actualizador::firmaValida($this->zip, 'no es base64 ##', $this->clavePublica));
        $this->assertFalse(Actualizador::firmaValida($this->zip, base64_encode('corta'), $this->clavePublica));
        $this->assertFalse(Actualizador::firmaValida($this->zip.'-no-existe', $this->firmar($this->zip, $this->clavePrivada), $this->clavePublica));
    }
}
