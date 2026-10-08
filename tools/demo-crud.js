// Demostración del CRUD en vivo sobre mensajes_log.
// Uso: docker compose exec -T mongo mongosh iot_led --quiet < tools/demo-crud.js

const col = db.mensajes_log;

print('\n1) insertOne: un mensaje nuevo nace en PENDIENTE');
const { insertedId } = col.insertOne({
  mensaje: 'DEMO MONGOSH',
  longitud: 12,
  velocidad: 'media',
  velocidad_ms: 90,
  estado: 'PENDIENTE',
  ip_origen: 'demo-mongosh',
  fechas: { creacion: new Date(), procesando: null, emision: null },
  dispositivo: null,
});
printjson(insertedId);

print('\n2) find filtrado: la cola de pendientes, del más antiguo al más nuevo');
printjson(
  col.find({ estado: 'PENDIENTE' }, { mensaje: 1, 'fechas.creacion': 1 })
    .sort({ 'fechas.creacion': 1 })
    .toArray()
);

print('\n3) updateOne: PROCESANDO → EMITIDO (solo si está en PROCESANDO)');
col.updateOne(
  { _id: insertedId },
  { $set: { estado: 'PROCESANDO', 'fechas.procesando': new Date(), dispositivo: { id: 'DEMO', mac_token: '****0000' } } }
);
printjson(
  col.updateOne(
    { _id: insertedId, estado: 'PROCESANDO' },
    { $set: { estado: 'EMITIDO', 'fechas.emision': new Date() } }
  )
);

print('\n4) El documento con sus tres fechas y el dispositivo que lo emitió');
printjson(col.findOne({ _id: insertedId }));

print('\n5) Historial: los últimos 50, del más nuevo al más antiguo');
printjson(
  col.find({}, { mensaje: 1, estado: 1, 'fechas.creacion': 1 })
    .sort({ 'fechas.creacion': -1 })
    .limit(50)
    .toArray()
);

print('\n6) Índices de la colección');
printjson(col.getIndexes());
