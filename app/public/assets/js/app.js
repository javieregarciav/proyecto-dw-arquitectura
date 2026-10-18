// Formulario: contador, simulador de la matriz y envio a la API.
import { mostrarTexto } from './simulador.js';
import { crearMensaje, textoError } from './api.js';

// Mismos valores que el enum Velocidad del backend (ms por columna).
const VELOCIDADES_MS = { lenta: 150, media: 90, rapida: 50 };

const formulario = document.getElementById('form-mensaje');
const entrada = document.getElementById('mensaje');
const contadorActual = document.getElementById('contador-actual');
const selectorVelocidad = document.getElementById('velocidad');
const boton = formulario.querySelector('button[type="submit"]');
const estadoEnvio = document.getElementById('estado-envio');

function actualizar() {
    // textContent: nunca innerHTML con texto del usuario.
    contadorActual.textContent = String(entrada.value.length);
    mostrarTexto(entrada.value, VELOCIDADES_MS[selectorVelocidad.value] ?? 90);
}

function mostrarEstado(texto, esError) {
    estadoEnvio.textContent = texto;
    estadoEnvio.classList.toggle('formulario__estado--error', esError);
    estadoEnvio.classList.toggle('formulario__estado--ok', !esError && texto !== '');
}

async function enviar(evento) {
    evento.preventDefault(); // que el navegador no recargue la pagina

    boton.disabled = true;
    mostrarEstado('Enviando...', false);

    try {
        const { estado, cuerpo } = await crearMensaje(entrada.value, selectorVelocidad.value);

        if (estado === 201 && cuerpo?.ok) {
            // El servidor devuelve el texto ya normalizado (mayusculas, sin acentos).
            mostrarEstado(`Mensaje enviado: "${cuerpo.data.mensaje}" (${cuerpo.data.estado}).`, false);
            entrada.value = '';
            actualizar();
        } else {
            mostrarEstado(textoError(cuerpo, estado), true);
        }
    } catch {
        mostrarEstado('No se pudo conectar con el servidor.', true);
    } finally {
        boton.disabled = false;
    }
}

entrada.addEventListener('input', actualizar);
selectorVelocidad.addEventListener('change', actualizar);
formulario.addEventListener('submit', enviar);
actualizar();
