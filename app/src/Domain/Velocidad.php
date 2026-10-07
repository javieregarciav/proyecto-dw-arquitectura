<?php

declare(strict_types=1);

namespace App\Domain;

enum Velocidad: string
{
    case Lenta  = 'lenta';
    case Media  = 'media';
    case Rapida = 'rapida';

    /** Milisegundos por columna; el simulador y el Arduino usan el mismo valor. */
    public function ms(): int
    {
        return match ($this) {
            self::Lenta  => 150,
            self::Media  => 90,
            self::Rapida => 50,
        };
    }
}
