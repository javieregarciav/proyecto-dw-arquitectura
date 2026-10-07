<?php

declare(strict_types=1);

namespace App\Domain;

use DomainException;

final class MensajeNoEncontrado extends DomainException
{
    public function __construct()
    {
        parent::__construct('El mensaje no existe');
    }
}
