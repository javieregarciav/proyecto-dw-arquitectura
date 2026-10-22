<?php

declare(strict_types=1);

namespace App\Security;

use App\Domain\Mensaje;
use App\Domain\Velocidad;
use App\Http\HttpException;
use MongoDB\BSON\ObjectId;
use MongoDB\Driver\Exception\InvalidArgumentException as ObjectIdInvalido;

final class Sanitizador
{
    private const TRANSLITERACION = [
        'á' => 'A', 'à' => 'A', 'ä' => 'A', 'â' => 'A', 'ã' => 'A',
        'Á' => 'A', 'À' => 'A', 'Ä' => 'A', 'Â' => 'A', 'Ã' => 'A',
        'é' => 'E', 'è' => 'E', 'ë' => 'E', 'ê' => 'E',
        'É' => 'E', 'È' => 'E', 'Ë' => 'E', 'Ê' => 'E',
        'í' => 'I', 'ì' => 'I', 'ï' => 'I', 'î' => 'I',
        'Í' => 'I', 'Ì' => 'I', 'Ï' => 'I', 'Î' => 'I',
        'ó' => 'O', 'ò' => 'O', 'ö' => 'O', 'ô' => 'O', 'õ' => 'O',
        'Ó' => 'O', 'Ò' => 'O', 'Ö' => 'O', 'Ô' => 'O', 'Õ' => 'O',
        'ú' => 'U', 'ù' => 'U', 'ü' => 'U', 'û' => 'U',
        'Ú' => 'U', 'Ù' => 'U', 'Ü' => 'U', 'Û' => 'U',
        'ñ' => 'N', 'Ñ' => 'N',
        'ç' => 'C', 'Ç' => 'C',
    ];

    public static function nuevoMensaje(array $cuerpo): array
    {
        $campos = [];
        $texto = null;
        $velocidad = null;

        try {
            $texto = self::texto($cuerpo['mensaje'] ?? null);
        } catch (HttpException $e) {
            $campos += self::camposDe($e);
        }

        try {
            $velocidad = self::velocidad($cuerpo['velocidad'] ?? null);
        } catch (HttpException $e) {
            $campos += self::camposDe($e);
        }

        if ($texto === null || $velocidad === null) {
            throw HttpException::validacion($campos);
        }

        return ['texto' => $texto, 'velocidad' => $velocidad];
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

        try {
            return new ObjectId($valor);
        } catch (ObjectIdInvalido) {
            throw HttpException::peticionInvalida('El id no es un ObjectId válido');
        }
    }

    private static function camposDe(HttpException $e): array
    {
        if ($e->estado() !== 422) {
            throw $e;
        }

        return $e->campos();
    }
}
