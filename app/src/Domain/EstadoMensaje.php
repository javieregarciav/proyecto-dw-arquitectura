<?php

declare(strict_types=1);

namespace App\Domain;

enum EstadoMensaje: string
{
    case Pendiente  = 'PENDIENTE';
    case Procesando = 'PROCESANDO';
    case Emitido    = 'EMITIDO';
}
