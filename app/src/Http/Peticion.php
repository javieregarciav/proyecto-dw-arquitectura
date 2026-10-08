<?php

declare(strict_types=1);

namespace App\Http;

use JsonException;

/** Lectura defensiva de la petición actual. */
final class Peticion
{
    public const LIMITE_CUERPO = 1024;

    public static function exigirMetodo(string ...$permitidos): void
    {
        if (!in_array($_SERVER['REQUEST_METHOD'] ?? '', $permitidos, true)) {
            throw HttpException::metodoNoPermitido($permitidos);
        }
    }

    public static function cabecera(string $nombre): ?string
    {
        $valor = $_SERVER['HTTP_' . strtoupper(str_replace('-', '_', $nombre))] ?? null;

        return is_string($valor) ? trim($valor) : null;
    }

    /** @return array<string, mixed> */
    public static function cuerpoJson(): array
    {
        $tipo = strtolower(trim(explode(';', (string) ($_SERVER['CONTENT_TYPE'] ?? ''))[0]));
        if ($tipo !== 'application/json') {
            throw HttpException::tipoNoSoportado();
        }

        // Se lee un byte de más para detectar cuerpos que superan el límite.
        $crudo = file_get_contents('php://input', false, null, 0, self::LIMITE_CUERPO + 1);
        if ($crudo === false || strlen($crudo) > self::LIMITE_CUERPO) {
            throw HttpException::peticionInvalida('El cuerpo supera ' . self::LIMITE_CUERPO . ' bytes');
        }

        try {
            $datos = json_decode($crudo, true, 8, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw HttpException::peticionInvalida('JSON mal formado');
        }

        if (!is_array($datos) || ($datos !== [] && array_is_list($datos))) {
            throw HttpException::peticionInvalida('Se esperaba un objeto JSON');
        }

        return $datos;
    }

    /** No se confía en X-Forwarded-For: no hay proxy propio delante. */
    public static function ipOrigen(): string
    {
        $ip = $_SERVER['REMOTE_ADDR'] ?? '';

        return filter_var($ip, FILTER_VALIDATE_IP) !== false ? $ip : 'desconocida';
    }
}
