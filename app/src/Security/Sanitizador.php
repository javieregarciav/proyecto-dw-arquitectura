<?php

declare(strict_types=1);

namespace App\Security;

use App\Domain\Mensaje;
use App\Domain\Velocidad;
use App\Http\HttpException;
use MongoDB\BSON\ObjectId;

final class Sanitizador
{
    private const TRANSLITERACION = [
        'á' => 'A', 'Á' => 'A',
        'é' => 'E', 'É' => 'E',
        'í' => 'I', 'Í' => 'I',
        'ó' => 'O', 'Ó' => 'O',
        'ú' => 'U', 'Ú' => 'U', 'ü' => 'U', 'Ü' => 'U',
        'ñ' => 'N', 'Ñ' => 'N',
    ];

    public static function nuevoMensaje(array $cuerpo): array
    {
        return [
            'texto'     => self::texto($cuerpo['mensaje'] ?? null),
            'velocidad' => self::velocidad($cuerpo['velocidad'] ?? null),
        ];
    }

    public static function texto(mixed $valor): string
    {
        if ($valor === null) {
            throw HttpException::validacion(['mensaje' => 'El mensaje es obligatorio']);
        }
        if (!is_string($valor)) {
            throw HttpException::peticionInvalida('El campo mensaje debe ser texto');
        }

        $texto = preg_replace('/\p{C}/u', '', $valor);
        if ($texto === null) {
            throw HttpException::validacion(['mensaje' => 'El mensaje no es UTF-8 válido']);
        }

        $texto = strtr($texto, self::TRANSLITERACION);

        $texto = (string) preg_replace('/ {2,}/', ' ', trim(strtoupper($texto)));

        if (preg_match('/[^\x20-\x7E]/', $texto) === 1) {
            throw HttpException::validacion(['mensaje' => 'El mensaje contiene caracteres no permitidos']);
        }

        $longitud = strlen($texto);
        if ($longitud < 1 || $longitud > Mensaje::LONGITUD_MAXIMA) {
            throw HttpException::validacion([
                'mensaje' => 'El mensaje debe tener entre 1 y ' . Mensaje::LONGITUD_MAXIMA . ' caracteres',
            ]);
        }

        return $texto;
    }

    public static function velocidad(mixed $valor): Velocidad
    {
        if ($valor === null) {
            throw HttpException::validacion(['velocidad' => 'La velocidad es obligatoria']);
        }
        if (!is_string($valor)) {
            throw HttpException::peticionInvalida('El campo velocidad debe ser texto');
        }

        return Velocidad::tryFrom($valor)
            ?? throw HttpException::validacion(['velocidad' => 'Valores permitidos: lenta, media, rapida']);
    }

    public static function objectId(mixed $valor): ObjectId
    {
        if (!is_string($valor) || preg_match('/^[a-f0-9]{24}$/i', $valor) !== 1) {
            throw HttpException::peticionInvalida('El id no es un ObjectId válido');
        }

        return new ObjectId($valor);
    }
}
