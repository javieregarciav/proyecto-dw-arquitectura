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
        private readonly ?DateTimeImmutable $emision = null,
    ) {
    }

    public static function crear(
        string $texto,
        Velocidad $velocidad,
        string $ipOrigen,
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
            new DateTimeImmutable('now', new DateTimeZone('UTC')),
        );
    }

    public static function reconstituir(
        string $id,
        string $texto,
        Velocidad $velocidad,
        EstadoMensaje $estado,
        string $ipOrigen,
        DateTimeImmutable $creacion,
        ?DateTimeImmutable $emision,
    ): self {
        return new self($id, $texto, $velocidad, $estado, $ipOrigen, $creacion, $emision);
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
            $this->emision,
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

    public function emision(): ?DateTimeImmutable
    {
        return $this->emision;
    }
}
