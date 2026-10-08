<?php

declare(strict_types=1);

namespace App\Http;

use DateTimeInterface;
use DateTimeZone;

/** Envoltura única del contrato: { ok: true, data } o { ok: false, error }. */
final class JsonResponse
{
    /**
     * @param array<string, mixed>|null $cuerpo
     * @param array<string, string> $cabeceras
     */
    private function __construct(
        private readonly int $estado,
        private readonly ?array $cuerpo,
        private readonly array $cabeceras = [],
    ) {
    }

    /** @param array<string, mixed> $data */
    public static function ok(array $data, int $estado = 200): self
    {
        return new self($estado, ['ok' => true, 'data' => $data]);
    }

    public static function sinContenido(): self
    {
        return new self(204, null);
    }

    /**
     * @param array<string, string> $campos
     * @param array<string, string> $cabeceras
     */
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

    /** ISO 8601 en UTC: 2026-10-25T10:15:12Z */
    public static function fecha(?DateTimeInterface $fecha): ?string
    {
        if ($fecha === null) {
            return null;
        }

        return \DateTimeImmutable::createFromInterface($fecha)
            ->setTimezone(new DateTimeZone('UTC'))
            ->format('Y-m-d\TH:i:s\Z');
    }

    public function estado(): int
    {
        return $this->estado;
    }

    /** @return array<string, mixed>|null */
    public function cuerpo(): ?array
    {
        return $this->cuerpo;
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
