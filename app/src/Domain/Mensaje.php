<?php

declare(strict_types=1);

namespace App\Domain;

use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;

final class Mensaje
{
    public const LONGITUD_MAXIMA = 50;

    private function __construct(
        private readonly ?string $id,
        private readonly string $texto,
        private readonly Velocidad $velocidad,
        private readonly EstadoMensaje $estado,
        private readonly string $ipOrigen,
        private readonly DateTimeImmutable $creacion,
        private readonly ?DateTimeImmutable $procesando = null,
        private readonly ?DateTimeImmutable $emision = null,
        private readonly ?string $dispositivoId = null,
    ) {
    }

    /**
     * Crea un mensaje nuevo en PENDIENTE. Espera el texto ya normalizado por el Sanitizador;
     * aquí solo se protegen las invariantes del dominio.
     */
    public static function crear(
        string $texto,
        Velocidad $velocidad,
        string $ipOrigen,
        ?DateTimeImmutable $ahora = null,
    ): self {
        $longitud = strlen($texto);
        if ($longitud < 1 || $longitud > self::LONGITUD_MAXIMA) {
            throw new InvalidArgumentException('El mensaje debe tener entre 1 y ' . self::LONGITUD_MAXIMA . ' caracteres');
        }
        if (preg_match('/[^\x20-\x7E]/', $texto) === 1) {
            throw new InvalidArgumentException('El mensaje solo admite ASCII imprimible');
        }

        return new self(
            null,
            $texto,
            $velocidad,
            EstadoMensaje::Pendiente,
            $ipOrigen,
            $ahora ?? new DateTimeImmutable('now', new DateTimeZone('UTC')),
        );
    }

    /** Reconstruye un mensaje leído de la base de datos. */
    public static function reconstituir(
        string $id,
        string $texto,
        Velocidad $velocidad,
        EstadoMensaje $estado,
        string $ipOrigen,
        DateTimeImmutable $creacion,
        ?DateTimeImmutable $procesando,
        ?DateTimeImmutable $emision,
        ?string $dispositivoId,
    ): self {
        return new self($id, $texto, $velocidad, $estado, $ipOrigen, $creacion, $procesando, $emision, $dispositivoId);
    }

    public function conId(string $id): self
    {
        return new self(
            $id,
            $this->texto,
            $this->velocidad,
            $this->estado,
            $this->ipOrigen,
            $this->creacion,
            $this->procesando,
            $this->emision,
            $this->dispositivoId,
        );
    }

    public function id(): ?string
    {
        return $this->id;
    }

    public function texto(): string
    {
        return $this->texto;
    }

    public function longitud(): int
    {
        return strlen($this->texto);
    }

    public function velocidad(): Velocidad
    {
        return $this->velocidad;
    }

    public function estado(): EstadoMensaje
    {
        return $this->estado;
    }

    public function ipOrigen(): string
    {
        return $this->ipOrigen;
    }

    public function creacion(): DateTimeImmutable
    {
        return $this->creacion;
    }

    public function procesando(): ?DateTimeImmutable
    {
        return $this->procesando;
    }

    public function emision(): ?DateTimeImmutable
    {
        return $this->emision;
    }

    public function dispositivoId(): ?string
    {
        return $this->dispositivoId;
    }
}
