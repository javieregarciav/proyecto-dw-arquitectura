<?php

declare(strict_types=1);

/**
 * Genera, a partir de shared/font5x7.json (la unica fuente de verdad):
 *   - app/public/assets/js/font.js        (modulo ES para el simulador)
 *   - hardware/matriz_led/font.h          (arreglo PROGMEM para el Arduino)
 *
 * Uso (desde la raiz del repo):
 *   docker run --rm -v "$PWD":/work -w /work php:8.3-cli php tools/gen-font.php
 * o, si tenes PHP instalado:  php tools/gen-font.php
 *
 * Los archivos generados NUNCA se editan a mano.
 */

const PRIMER_CODIGO = 0x20;
const ULTIMO_CODIGO = 0x7E;
const COLUMNAS_POR_CARACTER = 5;

$raiz = dirname(__DIR__);
$origen = $raiz . '/shared/font5x7.json';

if (!is_file($origen)) {
    fwrite(STDERR, "No existe $origen\n");
    exit(1);
}

try {
    /** @var array<string, list<int>> $fuente */
    $fuente = json_decode((string) file_get_contents($origen), true, 4, JSON_THROW_ON_ERROR);
} catch (JsonException $e) {
    fwrite(STDERR, 'JSON invalido: ' . $e->getMessage() . "\n");
    exit(1);
}

// Validacion: 95 caracteres ASCII imprimibles, 5 columnas cada uno, bytes de 0 a 255.
$caracteres = [];
for ($codigo = PRIMER_CODIGO; $codigo <= ULTIMO_CODIGO; $codigo++) {
    $caracter = chr($codigo);
    $columnas = $fuente[$caracter] ?? null;

    if (!is_array($columnas) || count($columnas) !== COLUMNAS_POR_CARACTER) {
        fwrite(STDERR, sprintf("Falta o es invalido el caracter 0x%02X\n", $codigo));
        exit(1);
    }
    foreach ($columnas as $byte) {
        if (!is_int($byte) || $byte < 0 || $byte > 255) {
            fwrite(STDERR, sprintf("Byte fuera de rango en el caracter 0x%02X\n", $codigo));
            exit(1);
        }
    }
    $caracteres[$codigo] = $columnas;
}

$aviso = 'GENERADO por tools/gen-font.php desde shared/font5x7.json. NO EDITAR A MANO.';

// ---------- JavaScript (modulo ES) ----------
$js = "// $aviso\n";
$js .= "// Cada caracter ASCII 0x20-0x7E tiene 5 columnas; el bit 0 es la fila superior.\n";
$js .= "export const FONT_5X7 = {\n";
$lineasJs = [];
foreach ($caracteres as $codigo => $columnas) {
    $clave = json_encode(chr($codigo), JSON_THROW_ON_ERROR);
    $lineasJs[] = '  ' . $clave . ': [' . implode(', ', $columnas) . ']';
}
$js .= implode(",\n", $lineasJs) . "\n};\n";

// ---------- C para Arduino ----------
$h = "// $aviso\n";
$h .= "// Indice = codigo ASCII - 0x20. 5 bytes por caracter, uno por columna; bit 0 = fila superior.\n";
$h .= "#pragma once\n#include <Arduino.h>\n#include <avr/pgmspace.h>\n\n";
$h .= "const uint8_t FONT5X7[95][5] PROGMEM = {\n";
$filas = [];
foreach ($caracteres as $codigo => $columnas) {
    $bytes = implode(', ', array_map(static fn (int $b): string => sprintf('0x%02X', $b), $columnas));
    $filas[] = sprintf("  {%s}", $bytes) . '@@' . sprintf(" // 0x%02X '%s'", $codigo, chr($codigo));
}
// La coma va antes del comentario; el ultimo elemento no lleva coma.
$ultimo = count($filas) - 1;
foreach ($filas as $i => $fila) {
    $coma = $i < $ultimo ? ',' : '';
    $h .= str_replace('@@', $coma, $fila) . "\n";
}
$h .= "};\n";

$destinos = [
    $raiz . '/app/public/assets/js/font.js' => $js,
    $raiz . '/hardware/matriz_led/font.h' => $h,
];

foreach ($destinos as $ruta => $contenido) {
    $carpeta = dirname($ruta);
    if (!is_dir($carpeta) && !mkdir($carpeta, 0775, true) && !is_dir($carpeta)) {
        fwrite(STDERR, "No se pudo crear $carpeta\n");
        exit(1);
    }
    file_put_contents($ruta, $contenido);
    echo 'Generado: ' . substr($ruta, strlen($raiz) + 1) . "\n";
}
