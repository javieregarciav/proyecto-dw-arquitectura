export async function crearMensaje(mensaje, velocidad) {
    const respuesta = await fetch('api/mensaje.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ mensaje, velocidad }),
    });

    let cuerpo = null;
    try {
        cuerpo = await respuesta.json();
    } catch {
    }

    return { estado: respuesta.status, cuerpo };
}

export function textoError(cuerpo, estado) {
    const error = cuerpo?.error;
    if (!error) {
        return `Error ${estado}: la respuesta del servidor no se entendio.`;
    }

    const partes = [error.mensaje ?? 'Error desconocido'];

    if (error.campos && typeof error.campos === 'object') {
        for (const detalle of Object.values(error.campos)) {
            if (typeof detalle === 'string') {
                partes.push(detalle);
            }
        }
    }

    return partes.join(' ');
}
