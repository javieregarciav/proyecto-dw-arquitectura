import { FONT_5X7 } from './font.js';

const TAMANO = 8;
const CLASE_ENCENDIDO = 'matriz__led--on';

let leds = null;
let temporizador = null;

export function normalizar(texto) {
    return texto
        .toUpperCase()
        .normalize('NFD')
        .replace(/[\u0300-\u036f]/g, '')
        .replace(/\s+/g, ' ')
        .trim();
}

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
