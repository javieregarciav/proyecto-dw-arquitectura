<?php

declare(strict_types=1);

namespace App\Infrastructure;

use DateTimeImmutable;
use MongoDB\BSON\UTCDateTime;
use MongoDB\Client;
use MongoDB\Collection;

final class MongoAdapter
{
    private readonly Client $cliente;

    public function __construct(string $uri, private readonly string $baseDatos)
    {
        $this->cliente = new Client($uri, [], [
            'typeMap' => ['root' => 'array', 'document' => 'array', 'array' => 'array'],
        ]);
    }

    public function coleccion(string $nombre): Collection
    {
        return $this->cliente->getCollection($this->baseDatos, $nombre);
    }

    public function crearIndices(): void
    {
        $mensajes = $this->coleccion(MensajeRepository::COLECCION);
        $mensajes->createIndex(['estado' => 1, 'fechas.creacion' => 1], ['name' => 'cola_pendientes']);
        $mensajes->createIndex(['fechas.creacion' => -1], ['name' => 'historial']);
    }

    public static function fecha(?UTCDateTime $fecha): ?DateTimeImmutable
    {
        return $fecha === null ? null : DateTimeImmutable::createFromInterface($fecha->toDateTime());
    }
}
