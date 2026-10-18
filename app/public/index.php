<?php

declare(strict_types=1);

// Cabeceras de seguridad (contrato, seccion 8.2). Con esta CSP no se permite
// ningun <script> ni <style> en linea: todo el JS y CSS va en assets/.
header("Content-Security-Policy: default-src 'self'");
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Referrer-Policy: no-referrer');

const MAX_CARACTERES = 50;
const TOTAL_LEDS = 64; // rejilla 8 x 8
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Letrero LED UMG</title>
    <link rel="stylesheet" href="assets/css/styles.css">
    <script type="module" src="assets/js/app.js"></script>
</head>
<body>
    <main class="pagina">
        <header class="pagina__encabezado">
            <h1 class="pagina__titulo">Sistema Web IoT</h1>
            <p class="pagina__subtitulo">Escribe un mensaje, mira cómo se verá en la matriz y envíalo al display.</p>
            <a class="pagina__enlace" href="dashboard.php">Ver historial de mensajes →</a>
        </header>

        <div class="pagina__contenido">
            <section class="panel" aria-labelledby="titulo-formulario">
                <h2 class="panel__titulo" id="titulo-formulario">Nuevo mensaje</h2>

                <form class="formulario" id="form-mensaje" action="#" method="post">
                    <div class="formulario__campo">
                        <label class="formulario__etiqueta" for="mensaje">Mensaje</label>
                        <input class="formulario__entrada" type="text" id="mensaje" name="mensaje"
                               maxlength="<?= MAX_CARACTERES ?>" required autocomplete="off"
                               aria-describedby="contador">
                        <p class="formulario__contador" id="contador" aria-live="polite">
                            <span id="contador-actual">0</span>/<?= MAX_CARACTERES ?> caracteres
                        </p>
                    </div>

                    <div class="formulario__campo">
                        <label class="formulario__etiqueta" for="velocidad">Velocidad de desplazamiento</label>
                        <select class="formulario__entrada" id="velocidad" name="velocidad">
                            <option value="lenta">Lenta (150 ms por columna)</option>
                            <option value="media" selected>Media (90 ms por columna)</option>
                            <option value="rapida">Rápida (50 ms por columna)</option>
                        </select>
                    </div>

                    <button class="formulario__boton" type="submit">Enviar al arduino</button>
                    <p class="formulario__estado" id="estado-envio" role="status"></p>
                </form>
            </section>

            <section class="panel" aria-labelledby="titulo-simulador">
                <h2 class="panel__titulo" id="titulo-simulador">Vista previa</h2>

                <div class="matriz" id="matriz" role="img" aria-label="Simulador de matriz LED de 8 por 8">
                    <?php for ($i = 0; $i < TOTAL_LEDS; $i++) : ?>
                        <div class="matriz__led"></div>
                    <?php endfor; ?>
                </div>
            </section>
        </div>
    </main>
</body>
</html>