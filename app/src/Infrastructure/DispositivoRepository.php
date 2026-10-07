<?php

declare(strict_types=1);

namespace App\Infrastructure;

use App\Domain\Dispositivo;
use MongoDB\BSON\UTCDateTime;
use MongoDB\Collection;

final class DispositivoRepository
{
    public const COLECCION = 'dispositivos';

    private readonly Collection $coleccion;

    public function __construct(MongoAdapter $mongo)
    {
        $this->coleccion = $mongo->coleccion(self::COLECCION);
    }

    public function buscar(string $id): ?Dispositivo
    {
        $doc = $this->coleccion->findOne(['_id' => $id]);

        return $doc === null ? null : $this->aDispositivo($doc);
    }

    /** @return list<Dispositivo> */
    public function listar(): array
    {
        $dispositivos = [];
        foreach ($this->coleccion->find([], ['sort' => ['_id' => 1]]) as $doc) {
            $dispositivos[] = $this->aDispositivo($doc);
        }

        return $dispositivos;
    }

    public function registrarContacto(string $id): void
    {
        $this->coleccion->updateOne(['_id' => $id], ['$set' => ['ultimo_contacto' => new UTCDateTime()]]);
    }

    /** Alta o rotación de token. Solo se guarda el hash. */
    public function registrar(string $id, string $token): void
    {
        $this->coleccion->updateOne(
            ['_id' => $id],
            [
                '$set'         => ['token_hash' => Dispositivo::hashToken($token), 'activo' => true],
                '$setOnInsert' => ['ultimo_contacto' => null],
            ],
            ['upsert' => true],
        );
    }

    /** @param array<string, mixed> $doc */
    private function aDispositivo(array $doc): Dispositivo
    {
        return new Dispositivo(
            (string) $doc['_id'],
            (string) $doc['token_hash'],
            (bool) ($doc['activo'] ?? false),
            MongoAdapter::fecha($doc['ultimo_contacto'] ?? null),
        );
    }
}
