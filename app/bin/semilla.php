<?php

declare(strict_types=1);

use App\Bootstrap;

require dirname(__DIR__) . '/vendor/autoload.php';

// Crea los índices y registra (o rota el token de) un dispositivo.
// Uso: docker compose exec web php bin/semilla.php [ID_DISPOSITIVO] [TOKEN]
// Sin TOKEN se genera uno aleatorio. Solo se guarda su sha256.

$id = $argv[1] ?? 'ARDUINO_LAB_01';
$token = $argv[2] ?? bin2hex(random_bytes(24));

if (preg_match('/^[A-Za-z0-9_-]{1,64}$/', $id) !== 1) {
    fwrite(STDERR, "Id de dispositivo inválido: solo letras, números, _ y -\n");
    exit(1);
}
if (strlen($token) < 16) {
    fwrite(STDERR, "El token debe tener al menos 16 caracteres\n");
    exit(1);
}

$app = Bootstrap::iniciar(false);
$app->mongo()->crearIndices();
$app->dispositivos()->registrar($id, $token);

fwrite(STDOUT, "Índices creados.\n");
fwrite(STDOUT, "Dispositivo {$id} registrado.\n");
fwrite(STDOUT, "Token (compártelo por un canal privado, no se vuelve a mostrar): {$token}\n");
