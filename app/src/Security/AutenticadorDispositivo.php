<?php

declare(strict_types=1);

namespace App\Security;

use App\Domain\Dispositivo;
use App\Http\HttpException;
use App\Infrastructure\DispositivoRepository;

final class AutenticadorDispositivo
{
    public function __construct(private readonly DispositivoRepository $dispositivos)
    {
    }

    public function autenticar(?string $id, ?string $token): Dispositivo
    {
        if ($id === null || $token === null || $token === '' || preg_match('/^[A-Za-z0-9_-]{1,64}$/', $id) !== 1) {
            throw HttpException::tokenInvalido();
        }

        $dispositivo = $this->dispositivos->buscar($id);
        if ($dispositivo === null) {
            hash_equals(str_repeat('0', 64), Dispositivo::hashToken($token));
            throw HttpException::tokenInvalido();
        }
        if (!$dispositivo->aceptaToken($token)) {
            throw HttpException::tokenInvalido();
        }

        $this->dispositivos->registrarContacto($id);

        return $dispositivo->autenticadoCon($token);
    }
}
