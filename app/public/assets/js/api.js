// Comunicacion con la API de Integrante B (ver docs/api.md).

// Envia el mensaje. Devuelve { estado, cuerpo }:
//   estado = codigo HTTP (201, 422, 400...), cuerpo = el JSON de la respuesta (o null).
// Si no hay red, fetch lanza una excepcion y la maneja quien llama.
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
        // la respuesta no trae JSON: se queda en null
    }

    return { estado: respuesta.status, cuerpo };
}

// Arma un texto legible a partir de un error de la API:
// { ok:false, error:{ codigo, mensaje, campos?:{...} } }
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
