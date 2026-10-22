const INTERVALO_MS = 3000;
const filas = document.getElementById('filas-historial');
const dispositivos = document.getElementById('estado-dispositivos');

async function traer(parcial, destino) {
    if (destino === null) {
        return;
    }

    const parametros = new URLSearchParams(window.location.search);
    parametros.set('parcial', parcial);

    try {
        const respuesta = await fetch(`dashboard.php?${parametros}`, { cache: 'no-store' });
        if (respuesta.ok) {
            destino.innerHTML = await respuesta.text();
        }
    } catch {
    }
}

function refrescar() {
    if (document.hidden) {
        return;
    }
    traer('1', filas);
    traer('dispositivos', dispositivos);
}

setInterval(refrescar, INTERVALO_MS);
