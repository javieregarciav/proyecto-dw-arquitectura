import { FONT_5X7 } from './font.js';

const TAMANO = 8;
const CLASE_ENCENDIDO = 'matriz__led--on';

const leds = document.querySelectorAll('.matriz__led');

let temporizador = null;

function normalizar(texto) {
    return texto
        .toUpperCase()
        .normalize('NFD')
        .replace(/[\u0300-\u036f]/g, '')
        .replace(/\s+/g, ' ')
        .trim();
}

function construirColumnas(texto) {
    const columnas = new Array(TAMANO).fill(0);

    for (const caracter of texto) {
        const letra = FONT_5X7[caracter] ?? FONT_5X7['?'];
        columnas.push(...letra, 0);
    }

    columnas.push(...new Array(TAMANO).fill(0));
    return columnas;
}

function dibujarVentana(columnas, inicio) {
    for (let col = 0; col < TAMANO; col++) {
        const byte = columnas[inicio + col] ?? 0;

        for (let fila = 0; fila < TAMANO; fila++) {
            const encendido = ((byte >> fila) & 1) === 1;
            leds[fila * TAMANO + col].classList.toggle(CLASE_ENCENDIDO, encendido);
        }
    }
}

function detener() {
    if (temporizador !== null) {
        clearInterval(temporizador);
        temporizador = null;
    }
}

export function mostrarTexto(texto, velocidadMs) {
    detener();

    const limpio = normalizar(texto);
    if (limpio === '') {
        dibujarVentana([], 0);
        return;
    }

    const columnas = construirColumnas(limpio);
    const posiciones = columnas.length - TAMANO + 1;

    let inicio = TAMANO;
    dibujarVentana(columnas, inicio);

    temporizador = setInterval(() => {
        inicio = (inicio + 1) % posiciones;
        dibujarVentana(columnas, inicio);
    }, velocidadMs);
}
