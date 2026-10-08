<?php

declare(strict_types=1);

use App\Bootstrap;
use App\Http\JsonResponse;
use App\Http\Peticion;

require dirname(__DIR__, 2) . '/vendor/autoload.php';

// GET /api/dispositivo.php — el Arduino toma el PENDIENTE más antiguo y lo pasa a PROCESANDO.
Bootstrap::iniciar()->ejecutar(static function (Bootstrap $app): JsonResponse {
    Peticion::exigirMetodo('GET');
    $dispositivo = $app->autenticador()->autenticar(
        Peticion::cabecera('X-Device-Id'),
        Peticion::cabecera('X-Device-Token'),
    );

    $mensaje = $app->mensajes()->tomarSiguientePendiente($dispositivo);
    if ($mensaje === null) {
        return JsonResponse::sinContenido();
    }

    // Sin htmlspecialchars(): el Arduino no es un navegador. La protección fue la validación de entrada.
    return JsonResponse::ok([
        'id'           => $mensaje->id(),
        'mensaje'      => $mensaje->texto(),
        'velocidad_ms' => $mensaje->velocidad()->ms(),
    ]);
});
