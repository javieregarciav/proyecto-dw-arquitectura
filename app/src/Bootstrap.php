<?php

declare(strict_types=1);

namespace App;

use App\Domain\MensajeNoEncontrado;
use App\Domain\TransicionInvalida;
use App\Http\HttpException;
use App\Http\JsonResponse;
use App\Infrastructure\DispositivoRepository;
use App\Infrastructure\MensajeRepository;
use App\Infrastructure\MongoAdapter;
use App\Security\AutenticadorDispositivo;
use DateTimeZone;
use ErrorException;
use Throwable;

final class Bootstrap
{
    private ?MongoAdapter $mongo = null;
    private ?MensajeRepository $mensajes = null;
    private ?DispositivoRepository $dispositivos = null;

    private function __construct(
        private readonly string $mongoUri,
        private readonly string $mongoDb,
        private readonly DateTimeZone $zonaHoraria,
    ) {
    }

    public static function iniciar(bool $enviarCabeceras = true): self
    {
        date_default_timezone_set('UTC');
        ini_set('display_errors', '0');

        set_error_handler(static function (int $nivel, string $mensaje, string $archivo, int $linea): bool {
            if ((error_reporting() & $nivel) === 0) {
                return false;
            }
            throw new ErrorException($mensaje, 0, $nivel, $archivo, $linea);
        });

        if ($enviarCabeceras && PHP_SAPI !== 'cli') {
            self::enviarCabecerasSeguridad();
        }

        return new self(
            self::env('MONGO_URI', 'mongodb://mongo:27017'),
            self::env('MONGO_DB', 'iot_led'),
            new DateTimeZone(self::env('APP_TZ', 'America/Guatemala')),
        );
    }

    public static function enviarCabecerasSeguridad(): void
    {
        header("Content-Security-Policy: default-src 'self'");
        header('X-Content-Type-Options: nosniff');
        header('X-Frame-Options: DENY');
        header('Referrer-Policy: no-referrer');
    }

    public function ejecutar(callable $accion): void
    {
        try {
            $respuesta = $accion($this);
        } catch (HttpException $e) {
            $respuesta = JsonResponse::desdeExcepcion($e);
        } catch (MensajeNoEncontrado $e) {
            $respuesta = JsonResponse::error(404, 'NO_ENCONTRADO', $e->getMessage());
        } catch (TransicionInvalida $e) {
            $respuesta = JsonResponse::error(409, 'ESTADO_INVALIDO', $e->getMessage());
        } catch (Throwable $e) {
            $this->registrarError($e);
            $respuesta = JsonResponse::error(500, 'ERROR_INTERNO', 'Error interno del servidor');
        }

        $respuesta->enviar();
    }

    public function registrarError(Throwable $e): void
    {
        error_log(sprintf('[%s] %s en %s:%d', $e::class, $e->getMessage(), $e->getFile(), $e->getLine()));
    }

    public function mongo(): MongoAdapter
    {
        return $this->mongo ??= new MongoAdapter($this->mongoUri, $this->mongoDb);
    }

    public function mensajes(): MensajeRepository
    {
        return $this->mensajes ??= new MensajeRepository($this->mongo());
    }

    public function dispositivos(): DispositivoRepository
    {
        return $this->dispositivos ??= new DispositivoRepository($this->mongo());
    }

    public function autenticador(): AutenticadorDispositivo
    {
        return new AutenticadorDispositivo($this->dispositivos());
    }

    public function zonaHoraria(): DateTimeZone
    {
        return $this->zonaHoraria;
    }

    private static function env(string $nombre, string $porDefecto): string
    {
        $valor = getenv($nombre);

        return $valor === false || $valor === '' ? $porDefecto : $valor;
    }
}
