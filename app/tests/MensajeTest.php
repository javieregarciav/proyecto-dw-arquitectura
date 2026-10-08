<?php

declare(strict_types=1);

namespace Tests;

use App\Domain\Dispositivo;
use App\Domain\EstadoMensaje;
use App\Domain\Mensaje;
use App\Domain\Velocidad;
use DateTimeImmutable;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class MensajeTest extends TestCase
{
    public function testUnMensajeNuevoNaceEnPendiente(): void
    {
        $ahora = new DateTimeImmutable('2026-10-25T10:15:00Z');
        $mensaje = Mensaje::crear('HOLA UMG', Velocidad::Media, '190.56.253.12', $ahora);

        self::assertNull($mensaje->id());
        self::assertSame('HOLA UMG', $mensaje->texto());
        self::assertSame(8, $mensaje->longitud());
        self::assertSame(EstadoMensaje::Pendiente, $mensaje->estado());
        self::assertSame(Velocidad::Media, $mensaje->velocidad());
        self::assertSame('190.56.253.12', $mensaje->ipOrigen());
        self::assertSame($ahora, $mensaje->creacion());
        self::assertNull($mensaje->procesando());
        self::assertNull($mensaje->emision());
        self::assertNull($mensaje->dispositivoId());
    }

    public function testConIdNoModificaElOriginal(): void
    {
        $mensaje = Mensaje::crear('HOLA', Velocidad::Lenta, '127.0.0.1');
        $guardado = $mensaje->conId('66fb1c2e9a1f4b0012ab34cd');

        self::assertNull($mensaje->id());
        self::assertSame('66fb1c2e9a1f4b0012ab34cd', $guardado->id());
        self::assertSame($mensaje->texto(), $guardado->texto());
    }

    /** @return iterable<string, array{string}> */
    public static function textosInvalidos(): iterable
    {
        yield 'vacío'      => [''];
        yield '51'         => [str_repeat('A', 51)];
        yield 'no ASCII'   => ['AÑO'];
        yield 'control'    => ["HOLA\n"];
    }

    #[DataProvider('textosInvalidos')]
    public function testProtegeSusInvariantes(string $texto): void
    {
        $this->expectException(InvalidArgumentException::class);

        Mensaje::crear($texto, Velocidad::Media, '127.0.0.1');
    }

    public function testVelocidadEnMilisegundos(): void
    {
        self::assertSame(150, Velocidad::Lenta->ms());
        self::assertSame(90, Velocidad::Media->ms());
        self::assertSame(50, Velocidad::Rapida->ms());
    }

    public function testDispositivoVerificaTokenYLoEnmascara(): void
    {
        $dispositivo = new Dispositivo('ARDUINO_LAB_01', Dispositivo::hashToken('TOKEN_SEC_9988'), true);

        self::assertTrue($dispositivo->aceptaToken('TOKEN_SEC_9988'));
        self::assertFalse($dispositivo->aceptaToken('otro'));
        self::assertSame('****9988', $dispositivo->autenticadoCon('TOKEN_SEC_9988')->tokenEnmascarado());
    }

    public function testDispositivoInactivoNoAutentica(): void
    {
        $dispositivo = new Dispositivo('ARDUINO_LAB_01', Dispositivo::hashToken('TOKEN'), false);

        self::assertFalse($dispositivo->aceptaToken('TOKEN'));
    }

    public function testDispositivoEnLinea(): void
    {
        $ahora = new DateTimeImmutable('2026-10-25T10:15:30Z');
        $reciente = new Dispositivo('A', 'h', true, new DateTimeImmutable('2026-10-25T10:15:10Z'));
        $viejo = new Dispositivo('A', 'h', true, new DateTimeImmutable('2026-10-25T10:14:00Z'));

        self::assertTrue($reciente->enLinea($ahora));
        self::assertFalse($viejo->enLinea($ahora));
        self::assertFalse((new Dispositivo('A', 'h', true))->enLinea($ahora));
    }
}
