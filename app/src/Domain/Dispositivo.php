<?php

declare(strict_types=1);

namespace App\Domain;

use DateTimeImmutable;
use DateTimeZone;

final class Dispositivo
{
    public const SEGUNDOS_EN_LINEA = 30;

    public function __construct(
        private readonly string $id,
        private readonly string $tokenHash,
        private readonly bool $activo,
        private readonly ?DateTimeImmutable $ultimoContacto = null,
        private readonly ?string $sufijoToken = null,
    ) {
    }

    public static function hashToken(string $token): string
    {
        return hash('sha256', $token);
    }

    /** Compara en tiempo constante para no filtrar información por tiempos de respuesta. */
    public function aceptaToken(string $token): bool
    {
        return hash_equals($this->tokenHash, self::hashToken($token)) && $this->activo;
    }

    /** Copia del dispositivo que recuerda los últimos 4 caracteres del token ya verificado. */
    public function autenticadoCon(string $token): self
    {
        return new self($this->id, $this->tokenHash, $this->activo, $this->ultimoContacto, substr($token, -4));
    }

    /** El token nunca se guarda completo en el log: solo ****XXXX. */
    public function tokenEnmascarado(): string
    {
        return '****' . ($this->sufijoToken ?? '');
    }

    public function enLinea(?DateTimeImmutable $ahora = null): bool
    {
        if ($this->ultimoContacto === null) {
            return false;
        }
        $ahora ??= new DateTimeImmutable('now', new DateTimeZone('UTC'));

        return $ahora->getTimestamp() - $this->ultimoContacto->getTimestamp() < self::SEGUNDOS_EN_LINEA;
    }

    public function id(): string
    {
        return $this->id;
    }

    public function activo(): bool
    {
        return $this->activo;
    }

    public function ultimoContacto(): ?DateTimeImmutable
    {
        return $this->ultimoContacto;
    }
}
