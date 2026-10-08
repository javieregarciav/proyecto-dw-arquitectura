<?php

declare(strict_types=1);

namespace Tests;

use App\Domain\Velocidad;
use App\Http\HttpException;
use App\Security\Sanitizador;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class SanitizadorTest extends TestCase
{
    /** @return iterable<string, array{string, string}> */
    public static function textosValidos(): iterable
    {
        yield 'mayúsculas'            => ['Bienvenidos a UMG', 'BIENVENIDOS A UMG'];
        yield 'acentos y eñe'         => ['Año Ñandú camión', 'ANO NANDU CAMION'];
        yield 'diéresis'              => ['pingüino', 'PINGUINO'];
        yield 'trim y espacios'       => ['   hola    mundo  ', 'HOLA MUNDO'];
        yield 'caracteres de control' => ["ho\x00la\tmun\ndo", 'HOLAMUNDO'];
        yield 'símbolos ASCII'        => ['<script>alert(1)</script>', '<SCRIPT>ALERT(1)</SCRIPT>'];
        yield 'justo 50'              => [str_repeat('a', 50), str_repeat('A', 50)];
        yield '50 tras normalizar'    => ['  ' . str_repeat('b', 50) . '  ', str_repeat('B', 50)];
    }

    #[DataProvider('textosValidos')]
    public function testNormalizaElTexto(string $entrada, string $esperado): void
    {
        self::assertSame($esperado, Sanitizador::texto($entrada));
    }

    /** @return iterable<string, array{mixed}> */
    public static function textosRechazados(): iterable
    {
        yield 'vacío'               => [''];
        yield 'solo espacios'       => ['     '];
        yield 'solo control'        => ["\x00\x01\n"];
        yield '51 caracteres'       => [str_repeat('x', 51)];
        yield 'emoji'               => ['hola 😀'];
        yield 'fuera de la fuente'  => ['こんにちは'];
        yield 'espacio no separable' => ["hola\u{00A0}mundo"];
        yield 'UTF-8 inválido'      => ["hola \xC3\x28"];
        yield 'ausente'             => [null];
    }

    #[DataProvider('textosRechazados')]
    public function testRechazaConValidacion(mixed $entrada): void
    {
        $e = $this->capturar(static fn () => Sanitizador::texto($entrada));

        self::assertSame(422, $e->estado());
        self::assertSame('VALIDACION', $e->codigo());
        self::assertArrayHasKey('mensaje', $e->campos());
    }

    /** @return iterable<string, array{mixed}> */
    public static function tiposIncorrectos(): iterable
    {
        yield 'arreglo' => [['$ne' => '']];
        yield 'número'  => [123];
        yield 'booleano' => [true];
    }

    #[DataProvider('tiposIncorrectos')]
    public function testRechazaTiposQueNoSonTexto(mixed $entrada): void
    {
        $e = $this->capturar(static fn () => Sanitizador::texto($entrada));

        self::assertSame(400, $e->estado());
        self::assertSame('PETICION_INVALIDA', $e->codigo());
    }

    public function testAceptaVelocidadesDelEnum(): void
    {
        self::assertSame(Velocidad::Lenta, Sanitizador::velocidad('lenta'));
        self::assertSame(Velocidad::Media, Sanitizador::velocidad('media'));
        self::assertSame(Velocidad::Rapida, Sanitizador::velocidad('rapida'));
    }

    public function testRechazaVelocidadFueraDelEnum(): void
    {
        $e = $this->capturar(static fn () => Sanitizador::velocidad('turbo'));

        self::assertSame(422, $e->estado());
        self::assertArrayHasKey('velocidad', $e->campos());
    }

    public function testNuevoMensajeJuntaLosErroresDeTodosLosCampos(): void
    {
        $e = $this->capturar(static fn () => Sanitizador::nuevoMensaje(['mensaje' => '', 'velocidad' => 'turbo']));

        self::assertSame(422, $e->estado());
        self::assertSame(['mensaje', 'velocidad'], array_keys($e->campos()));
    }

    public function testNuevoMensajeValido(): void
    {
        $entrada = Sanitizador::nuevoMensaje(['mensaje' => 'Hola UMG', 'velocidad' => 'rapida']);

        self::assertSame('HOLA UMG', $entrada['texto']);
        self::assertSame(Velocidad::Rapida, $entrada['velocidad']);
    }

    public function testObjectIdValido(): void
    {
        self::assertSame('66fb1c2e9a1f4b0012ab34cd', (string) Sanitizador::objectId('66fb1c2e9a1f4b0012ab34cd'));
    }

    /** @return iterable<string, array{mixed}> */
    public static function idsInvalidos(): iterable
    {
        yield 'inyección $ne' => [['$ne' => '']];
        yield 'corto'         => ['66fb1c2e'];
        yield 'no hex'        => ['zzzzzzzzzzzzzzzzzzzzzzzz'];
        yield 'nulo'          => [null];
    }

    #[DataProvider('idsInvalidos')]
    public function testRechazaIdsInvalidos(mixed $entrada): void
    {
        $e = $this->capturar(static fn () => Sanitizador::objectId($entrada));

        self::assertSame(400, $e->estado());
    }

    private function capturar(callable $accion): HttpException
    {
        try {
            $accion();
        } catch (HttpException $e) {
            return $e;
        }

        self::fail('Se esperaba una HttpException');
    }
}
