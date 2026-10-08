<?php

declare(strict_types=1);

use App\Bootstrap;
use App\Http\JsonResponse;
use App\Http\Peticion;
use App\Security\Sanitizador;

require dirname(__DIR__, 2) . '/vendor/autoload.php';

// POST /api/confirmar.php — ACK del Arduino al terminar la animación: PROCESANDO → EMITIDO.
Bootstrap::iniciar()->ejecutar(static function (Bootstrap $app): JsonResponse {
    Peticion::exigirMetodo('POST');
    $dispositivo = $app->autenticador()->autenticar(
        Peticion::cabecera('X-Device-Id'),
        Peticion::cabecera('X-Device-Token'),
    );

    $cuerpo = Peticion::cuerpoJson();
    $mensaje = $app->mensajes()->confirmarEmision(Sanitizador::objectId($cuerpo['id'] ?? null), $dispositivo);

    return JsonResponse::ok([
        'id'      => $mensaje->id(),
        'estado'  => $mensaje->estado()->value,
        'emision' => JsonResponse::fecha($mensaje->emision()),
    ]);
});
