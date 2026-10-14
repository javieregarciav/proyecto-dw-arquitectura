// Simulador de la matriz 8x8.
// Usa la misma fuente y el mismo algoritmo que el Arduino:
//   1) se arma una tira larga de columnas con el texto,
//   2) una ventana de 8 columnas recorre la tira, avanzando una por tick,
//   3) lo que cae dentro de la ventana es lo que se enciende.
import { FONT_5X7 } from './font.js';

const TAMANO = 8; // la matriz es de 8 x 8
const CLASE_ENCENDIDO = 'matriz__led--on';

let leds = null;
let temporizador = null;

// Mismas reglas que el servidor, solo para dar respuesta inmediata:
// mayusculas, acentos y enie a su letra base, espacios colapsados.
export function normalizar(texto) {
    return texto
        .toUpperCase()
        .normalize('NFD')
        .replace(/[\u0300-\u036f]/g, '')
        .replace(/\s+/g, ' ')
        .trim();
}

// 8 vacias + (5 columnas de cada letra + 1 vacia de separacion) + 8 vacias.
export function construirColumnas(texto) {
    const columnas = new Array(TAMANO).fill(0);

    for (const caracter of texto) {
        const letra = FONT_5X7[caracter] ?? FONT_5X7['?'];
        columnas.push(...letra, 0);
    }

    columnas.push(...new Array(TAMANO).fill(0));
    return columnas;
}

function obtenerLeds() {
    if (leds === null) {
        leds = document.querySelectorAll('.matriz__led');
    }
    return leds;
}

// Enciende o apaga los 64 LEDs segun lo que haya en la ventana.
// Cada byte es una columna; el bit 0 es la fila de arriba.
function dibujarVentana(columnas, inicio) {
    const todos = obtenerLeds();

    for (let col = 0; col < TAMANO; col++) {
        const byte = columnas[inicio + col] ?? 0;

        for (let fila = 0; fila < TAMANO; fila++) {
            const encendido = ((byte >> fila) & 1) === 1;
            todos[fila * TAMANO + col].classList.toggle(CLASE_ENCENDIDO, encendido);
        }
    }
}

export function detener() {
    if (temporizador !== null) {
        clearInterval(temporizador);
        temporizador = null;
    }
}

export function mostrarTexto(texto, velocidadMs) {
    detener();

    const limpio = normalizar(texto);
    if (limpio === '') {
        dibujarVentana([], 0); // todo apagado
        return;
    }

    const columnas = construirColumnas(limpio);
    const posiciones = columnas.length - TAMANO + 1;

    // En la vista previa se arranca con el texto ya visible (ventana en 8) para
    // que se vea la respuesta apenas se escribe. El Arduino arranca desde 0.
    let inicio = TAMANO;
    dibujarVentana(columnas, inicio);

    temporizador = setInterval(() => {
        inicio = (inicio + 1) % posiciones;
        dibujarVentana(columnas, inicio);
    }, velocidadMs);
}
