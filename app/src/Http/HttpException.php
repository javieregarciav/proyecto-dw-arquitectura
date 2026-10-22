<?php

declare(strict_types=1);

namespace App\Http;

use RuntimeException;

final class HttpException extends RuntimeException
{
    public function __construct(
        private readonly int $estado,
        private readonly string $codigo,
        string $mensaje,
        private readonly array $campos = [],
        private readonly array $cabeceras = [],
    ) {
        parent::__construct($mensaje);
    }

    public static function peticionInvalida(string $mensaje = 'Petición inválida'): self
    {
        return new self(400, 'PETICION_INVALIDA', $mensaje);
    }

    public static function tokenInvalido(): self
    {
        return new self(401, 'TOKEN_INVALIDO', 'Token no válido');
    }

    public static function metodoNoPermitido(array $permitidos): self
    {
        return new self(405, 'METODO_NO_PERMITIDO', 'Método no permitido', [], ['Allow' => implode(', ', $permitidos)]);
    }

    public static function tipoNoSoportado(): self
    {
        return new self(415, 'TIPO_NO_SOPORTADO', 'El cuerpo debe ser application/json');
    }

    public static function validacion(array $campos): self
    {
        return new self(422, 'VALIDACION', 'Datos no válidos', $campos);
    }

    public function estado(): int
    {
        return $this->estado;
    }

    public function codigo(): string
    {
        return $this->codigo;
    }

    public function campos(): array
    {
        return $this->campos;
    }

    public function cabeceras(): array
    {
        return $this->cabeceras;
    }
}
