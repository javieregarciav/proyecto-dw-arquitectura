// Refresco automatico del dashboard cada 3 segundos.
// dashboard.php?parcial=... devuelve solo un fragmento de HTML, ya escapado por el
// servidor (htmlspecialchars), asi que es seguro insertarlo tal cual.
const INTERVALO_MS = 3000;
const filas = document.getElementById('filas-historial');
const dispositivos = document.getElementById('estado-dispositivos');

async function traer(parcial, destino) {
    if (destino === null) {
        return; // esa parte no se dibujo (por ejemplo, hubo un error al cargar la pagina)
    }

    // Se conserva el filtro actual (?estado=...) y se agrega parcial=...
    const parametros = new URLSearchParams(window.location.search);
    parametros.set('parcial', parcial);

    try {
        const respuesta = await fetch(`dashboard.php?${parametros}`, { cache: 'no-store' });
        if (respuesta.ok) {
            destino.innerHTML = await respuesta.text();
        }
    } catch {
        // Sin red: se deja lo que ya se ve y se reintenta en el proximo ciclo.
    }
}

function refrescar() {
    if (document.hidden) {
        return; // pestana en segundo plano: no se gasta red
    }
    traer('1', filas);
    traer('dispositivos', dispositivos);
}

setInterval(refrescar, INTERVALO_MS);