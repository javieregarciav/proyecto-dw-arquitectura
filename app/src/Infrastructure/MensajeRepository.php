<?php

declare(strict_types=1);

namespace App\Infrastructure;

use App\Domain\Dispositivo;
use App\Domain\EstadoMensaje;
use App\Domain\Mensaje;
use App\Domain\MensajeNoEncontrado;
use App\Domain\TransicionInvalida;
use App\Domain\Velocidad;
use MongoDB\BSON\ObjectId;
use MongoDB\BSON\UTCDateTime;
use MongoDB\Collection;
use MongoDB\Operation\FindOneAndUpdate;

final class MensajeRepository
{
    public const COLECCION = 'mensajes_log';
    public const LIMITE_HISTORIAL = 50;

    private readonly Collection $coleccion;

    public function __construct(MongoAdapter $mongo)
    {
        $this->coleccion = $mongo->coleccion(self::COLECCION);
    }

    /** insertOne: el mensaje nace en PENDIENTE. */
    public function guardar(Mensaje $mensaje): Mensaje
    {
        $resultado = $this->coleccion->insertOne([
            'mensaje'      => $mensaje->texto(),
            'longitud'     => $mensaje->longitud(),
            'velocidad'    => $mensaje->velocidad()->value,
            'velocidad_ms' => $mensaje->velocidad()->ms(),
            'estado'       => $mensaje->estado()->value,
            'ip_origen'    => $mensaje->ipOrigen(),
            'fechas'       => [
                'creacion'   => new UTCDateTime($mensaje->creacion()),
                'procesando' => null,
                'emision'    => null,
            ],
            'dispositivo'  => null,
        ]);

        return $mensaje->conId((string) $resultado->getInsertedId());
    }

    /**
     * PENDIENTE → PROCESANDO en una sola operación atómica: toma el más antiguo y lo asigna
     * al dispositivo. Con find + updateOne por separado, dos requests podrían tomar el mismo.
     */
    public function tomarSiguientePendiente(Dispositivo $dispositivo): ?Mensaje
    {
        $doc = $this->coleccion->findOneAndUpdate(
            ['estado' => EstadoMensaje::Pendiente->value],
            ['$set' => [
                'estado'            => EstadoMensaje::Procesando->value,
                'fechas.procesando' => new UTCDateTime(),
                'dispositivo'       => ['id' => $dispositivo->id(), 'mac_token' => $dispositivo->tokenEnmascarado()],
            ]],
            [
                'sort'           => ['fechas.creacion' => 1],
                'returnDocument' => FindOneAndUpdate::RETURN_DOCUMENT_AFTER,
            ],
        );

        return $doc === null ? null : $this->aMensaje($doc);
    }

    /**
     * PROCESANDO → EMITIDO con updateOne. El filtro exige el estado y el dispositivo dueño,
     * así que la verificación y el cambio ocurren en la misma operación.
     */
    public function confirmarEmision(ObjectId $id, Dispositivo $dispositivo): Mensaje
    {
        $resultado = $this->coleccion->updateOne(
            [
                '_id'            => $id,
                'estado'         => EstadoMensaje::Procesando->value,
                'dispositivo.id' => $dispositivo->id(),
            ],
            ['$set' => [
                'estado'         => EstadoMensaje::Emitido->value,
                'fechas.emision' => new UTCDateTime(),
            ]],
        );

        $doc = $this->coleccion->findOne(['_id' => $id]);
        if ($doc === null) {
            throw new MensajeNoEncontrado();
        }
        if ($resultado->getModifiedCount() !== 1) {
            throw new TransicionInvalida('El mensaje no está en PROCESANDO para este dispositivo');
        }

        return $this->aMensaje($doc);
    }

    /**
     * Historial para el dashboard, del más nuevo al más antiguo.
     *
     * @return list<Mensaje>
     */
    public function listarHistorial(int $limite = self::LIMITE_HISTORIAL, ?EstadoMensaje $estado = null): array
    {
        $filtro = $estado === null ? [] : ['estado' => $estado->value];
        $cursor = $this->coleccion->find($filtro, [
            'sort'  => ['fechas.creacion' => -1],
            'limit' => max(1, min($limite, 200)),
        ]);

        $mensajes = [];
        foreach ($cursor as $doc) {
            $mensajes[] = $this->aMensaje($doc);
        }

        return $mensajes;
    }

    /** @param array<string, mixed> $doc */
    private function aMensaje(array $doc): Mensaje
    {
        return Mensaje::reconstituir(
            (string) $doc['_id'],
            (string) $doc['mensaje'],
            Velocidad::from((string) $doc['velocidad']),
            EstadoMensaje::from((string) $doc['estado']),
            (string) ($doc['ip_origen'] ?? ''),
            MongoAdapter::fecha($doc['fechas']['creacion']),
            MongoAdapter::fecha($doc['fechas']['procesando'] ?? null),
            MongoAdapter::fecha($doc['fechas']['emision'] ?? null),
            isset($doc['dispositivo']['id']) ? (string) $doc['dispositivo']['id'] : null,
        );
    }
}
