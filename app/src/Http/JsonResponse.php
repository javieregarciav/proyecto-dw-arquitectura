<?php

declare(strict_types=1);

namespace App\Http;

use DateTimeInterface;
use DateTimeZone;

final class JsonResponse
{
    private function __construct(
        private readonly int $estado,
        private readonly ?array $cuerpo,
        private readonly array $cabeceras = [],
    ) {
    }

    public static function ok(array $data, int $estado = 200): self
    {
        return new self($estado, ['ok' => true, 'data' => $data]);
    }

    public static function sinContenido(): self
    {
        return new self(204, null);
    }

    public static function error(int $estado, string $codigo, string $mensaje, array $campos = [], array $cabeceras = []): self
    {
        $error = ['codigo' => $codigo, 'mensaje' => $mensaje];
        if ($campos !== []) {
            $error['campos'] = $campos;
        }

        return new self($estado, ['ok' => false, 'error' => $error], $cabeceras);
    }

    public static function desdeExcepcion(HttpException $e): self
    {
        return self::error($e->estado(), $e->codigo(), $e->getMessage(), $e->campos(), $e->cabeceras());
    }

    public static function fecha(?DateTimeInterface $fecha): ?string
    {
        if ($fecha === null) {
            return null;
        }

        return \DateTimeImmutable::createFromInterface($fecha)
            ->setTimezone(new DateTimeZone('UTC'))
            ->format('Y-m-d\TH:i:s\Z');
    }

    public function enviar(): void
    {
        http_response_code($this->estado);
        header('Cache-Control: no-store');
        foreach ($this->cabeceras as $nombre => $valor) {
            header($nombre . ': ' . $valor);
        }

        if ($this->cuerpo === null) {
            return;
        }

        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($this->cuerpo, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    }
}
