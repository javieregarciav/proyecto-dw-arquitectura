<?php

declare(strict_types=1);

namespace App\Http;

use RuntimeException;

/** Error esperado que se traduce directo a la envoltura de error del contrato. */
final class HttpException extends RuntimeException
{
    /**
     * @param array<string, string> $campos
     * @param array<string, string> $cabeceras
     */
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

    /** @param list<string> $permitidos */
    public static function metodoNoPermitido(array $permitidos): self
    {
        return new self(405, 'METODO_NO_PERMITIDO', 'Método no permitido', [], ['Allow' => implode(', ', $permitidos)]);
    }

    public static function tipoNoSoportado(): self
    {
        return new self(415, 'TIPO_NO_SOPORTADO', 'El cuerpo debe ser application/json');
    }

    /** @param array<string, string> $campos */
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

    /** @return array<string, string> */
    public function campos(): array
    {
        return $this->campos;
    }

    /** @return array<string, string> */
    public function cabeceras(): array
    {
        return $this->cabeceras;
    }
}
