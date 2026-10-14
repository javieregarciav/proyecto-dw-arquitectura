<?php

declare(strict_types=1);

use App\Bootstrap;
use App\Domain\EstadoMensaje;

require __DIR__ . '/../vendor/autoload.php';

const LIMITE_HISTORIAL = 50;

/** Escapa un valor antes de imprimirlo en HTML: defensa contra XSS. */
function e(string $valor): string
{
    return htmlspecialchars($valor, ENT_QUOTES | ENT_HTML5, 'UTF-8');
}

/** Fecha en la zona horaria configurada (Guatemala), o un guion si no existe. */
function fecha(?DateTimeImmutable $fecha, DateTimeZone $zona): string
{
    return $fecha === null ? '—' : $fecha->setTimezone($zona)->format('d/m/Y H:i:s');
}

/** Imprime las filas de la tabla (sirve para la pagina completa y para el refresco parcial). */
function imprimirFilas(array $mensajes, DateTimeZone $zona): void
{
    if ($mensajes === []) {
        echo '<tr><td colspan="5">Todavía no hay mensajes.</td></tr>';
        return;
    }

    foreach ($mensajes as $mensaje) {
        $estado = $mensaje->estado()->value;
        echo '<tr>',
            '<td class="tabla__id">', e($mensaje->id() ?? ''), '</td>',
            '<td>', e($mensaje->texto()), '</td>',
            '<td><span class="estado estado--', e(strtolower($estado)), '">', e($estado), '</span></td>',
            '<td>', e(fecha($mensaje->creacion(), $zona)), '</td>',
            '<td>', e(fecha($mensaje->emision(), $zona)), '</td>',
            '</tr>';
    }
}

/** Imprime el estado de cada dispositivo: en linea (visto hace menos de 30 s) o sin conexion. */
function imprimirDispositivos(array $dispositivos, DateTimeZone $zona): void
{
    if ($dispositivos === []) {
        echo '<p class="dispositivo__vacio">No hay dispositivos registrados.</p>';
        return;
    }

    foreach ($dispositivos as $dispositivo) {
        $enLinea = $dispositivo->enLinea();
        echo '<p class="dispositivo dispositivo--', $enLinea ? 'en-linea' : 'sin-conexion', '">',
            '<span class="dispositivo__punto" aria-hidden="true"></span>',
            '<strong>', e($dispositivo->id()), '</strong>: ',
            $enLinea ? 'en línea' : 'sin conexión',
            ' <span class="dispositivo__detalle">(último contacto: ',
            e(fecha($dispositivo->ultimoContacto(), $zona)), ')</span>',
            '</p>';
    }
}

$app = Bootstrap::iniciar(); // tambien envia las cabeceras de seguridad

// Filtro opcional: ?estado=PENDIENTE | PROCESANDO | EMITIDO.
// El parametro podria llegar como arreglo (?estado[$ne]=x): solo se acepta un string.
$filtro = null;
$parametro = $_GET['estado'] ?? null;
if (is_string($parametro)) {
    $filtro = EstadoMensaje::tryFrom($parametro); // si no es un estado valido, queda en null
}

$mensajes = [];
$hayError = false;
try {
    $mensajes = $filtro === null
        ? $app->mensajes()->listarHistorial(LIMITE_HISTORIAL)
        : $app->mensajes()->listarHistorial(LIMITE_HISTORIAL, $filtro);
} catch (Throwable $e) {
    $app->registrarError($e); // el detalle va al log, no a la pantalla
    $hayError = true;
    http_response_code(500);
}

$zona = $app->zonaHoraria();


// Estado de los dispositivos (si la consulta falla, solo esa parte avisa del error).
$dispositivos = [];
$hayErrorDispositivos = false;
try {
    $dispositivos = $app->dispositivos()->listar();
} catch (Throwable $e) {
    $app->registrarError($e);
    $hayErrorDispositivos = true;
}

// Refresco parcial: solo un fragmento, ya escapado en el servidor, para que el JS lo reemplace.
//   ?parcial=1            -> las filas de la tabla
//   ?parcial=dispositivos -> el estado de los dispositivos
$parcial = is_string($_GET['parcial'] ?? null) ? $_GET['parcial'] : null;
if ($parcial === '1' || $parcial === 'dispositivos') {
    $falla = $parcial === '1' ? $hayError : $hayErrorDispositivos;
    if ($falla) {
        http_response_code(500); // cuerpo vacio: el JS deja lo que ya se ve
    } else {
        header('Content-Type: text/html; charset=utf-8');
        if ($parcial === '1') {
            imprimirFilas($mensajes, $zona);
        } else {
            imprimirDispositivos($dispositivos, $zona);
        }
    }
    exit;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Historial de mensajes</title>
    <link rel="stylesheet" href="assets/css/styles.css">
    <script type="module" src="assets/js/dashboard.js"></script>
</head>
<body>
    <main class="pagina">
        <header class="pagina__encabezado">
            <h1 class="pagina__titulo">Historial de mensajes</h1>
            <p class="pagina__subtitulo">Últimos <?= LIMITE_HISTORIAL ?> mensajes enviados al arduino.</p>
            <a class="pagina__enlace" href="index.php">← Volver al formulario</a>
        </header>

                <section class="panel" aria-labelledby="titulo-dispositivos">
            <h2 class="panel__titulo" id="titulo-dispositivos">Dispositivo</h2>
            <div id="estado-dispositivos">
                <?php if ($hayErrorDispositivos) : ?>
                    <p class="formulario__estado--error" role="alert">No se pudo consultar el estado del dispositivo.</p>
                <?php else : ?>
                    <?php imprimirDispositivos($dispositivos, $zona); ?>
                <?php endif; ?>
            </div>
        </section>
        
        <section class="panel" aria-labelledby="titulo-historial">
            <h2 class="panel__titulo" id="titulo-historial">Mensajes</h2>

            <nav class="filtros" aria-label="Filtrar por estado">
                <a class="filtros__opcion<?= $filtro === null ? ' filtros__opcion--activa' : '' ?>"
                   href="dashboard.php">Todos</a>
                <?php foreach (EstadoMensaje::cases() as $estado) : ?>
                    <a class="filtros__opcion<?= $filtro === $estado ? ' filtros__opcion--activa' : '' ?>"
                       href="dashboard.php?estado=<?= e($estado->value) ?>"><?= e($estado->value) ?></a>
                <?php endforeach; ?>
            </nav>

            <?php if ($hayError) : ?>
                <p class="formulario__estado--error" role="alert">No se pudo cargar el historial. Intenta de nuevo en unos segundos.</p>
            <?php else : ?>
                <div class="tabla__contenedor">
                    <table class="tabla">
                        <thead>
                            <tr>
                                <th scope="col">ID</th>
                                <th scope="col">Texto</th>
                                <th scope="col">Estado</th>
                                <th scope="col">Creado</th>
                                <th scope="col">Emitido</th>
                            </tr>
                        </thead>
                        <tbody id="filas-historial">
                            <?php imprimirFilas($mensajes, $zona); ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </section>
    </main>
</body>
</html>
