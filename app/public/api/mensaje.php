<?php

declare(strict_types=1);

use App\Bootstrap;
use App\Domain\Mensaje;
use App\Http\JsonResponse;
use App\Http\Peticion;
use App\Security\Sanitizador;

require dirname(__DIR__, 2) . '/vendor/autoload.php';

// POST /api/mensaje.php — crea un mensaje en PENDIENTE (lo usa el formulario).
Bootstrap::iniciar()->ejecutar(static function (Bootstrap $app): JsonResponse {
    Peticion::exigirMetodo('POST');
    $entrada = Sanitizador::nuevoMensaje(Peticion::cuerpoJson());

    $mensaje = $app->mensajes()->guardar(
        Mensaje::crear($entrada['texto'], $entrada['velocidad'], Peticion::ipOrigen()),
    );

    return JsonResponse::ok([
        'id'       => $mensaje->id(),
        'mensaje'  => $mensaje->texto(),
        'estado'   => $mensaje->estado()->value,
        'creacion' => JsonResponse::fecha($mensaje->creacion()),
    ], 201);
});
