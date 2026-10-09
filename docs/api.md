# API del sistema IoT de mensajes LED 8x8

API REST en PHP 8.3 (sin framework) con persistencia en MongoDB. Este documento refleja el
contrato congelado en M1: si el código y este documento no coinciden, el código está mal.

## Levantar el entorno local

```bash
cp app/.env.example app/.env
docker compose up -d --build
docker compose exec web php bin/semilla.php ARDUINO_LAB_01   # crea índices y muestra el token
```

- API: `http://localhost:8080/api/`
- Pruebas unitarias: `docker compose exec web vendor/bin/phpunit`
- Pruebas del contrato con curl: `TOKEN=<token> ./tools/pruebas-api.sh`
- Dispositivo simulado: `TOKEN=<token> ./tools/fake-device.sh`
- Demo del CRUD en mongosh: `docker compose exec -T mongo mongosh iot_led --quiet < tools/demo-crud.js`

## Convenciones

- Peticiones y respuestas en `application/json; charset=utf-8`.
- Fechas en ISO 8601 UTC (`2026-10-25T10:15:12Z`). El dashboard las muestra en `APP_TZ`.
- Cuerpo máximo de 1 KB.
- Envoltura de respuesta:

```json
{ "ok": true, "data": { } }
```

```json
{ "ok": false, "error": { "codigo": "TOKEN_INVALIDO", "mensaje": "Token no válido" } }
```

| HTTP | Código                | Cuándo                                                                  |
| ---- | --------------------- | ----------------------------------------------------------------------- |
| 400  | `PETICION_INVALIDA`   | JSON mal formado, `id` que no es ObjectId, tipo incorrecto, cuerpo > 1 KB |
| 401  | `TOKEN_INVALIDO`      | Falta `X-Device-Token` / `X-Device-Id` o no coinciden con un dispositivo activo |
| 404  | `NO_ENCONTRADO`       | El mensaje del ACK no existe                                            |
| 405  | `METODO_NO_PERMITIDO` | Método distinto al del endpoint (incluye cabecera `Allow`)              |
| 409  | `ESTADO_INVALIDO`     | ACK de un mensaje que no está en PROCESANDO o es de otro dispositivo    |
| 415  | `TIPO_NO_SOPORTADO`   | `Content-Type` distinto de JSON en un POST                              |
| 422  | `VALIDACION`          | No pasa las reglas de validación; incluye `"campos": { ... }`           |
| 500  | `ERROR_INTERNO`       | Excepción no controlada. Nunca se expone el stack trace                 |

## `POST /api/mensaje.php`

Crea un mensaje en **PENDIENTE**. Lo usa el formulario; no requiere token.

```json
{ "mensaje": "Bienvenidos a UMG", "velocidad": "media" }
```

`velocidad` ∈ `lenta` (150 ms), `media` (90 ms), `rapida` (50 ms).

Respuesta `201 Created`:

```json
{
  "ok": true,
  "data": {
    "id": "66fb1c2e9a1f4b0012ab34cd",
    "mensaje": "BIENVENIDOS A UMG",
    "estado": "PENDIENTE",
    "creacion": "2026-10-25T10:15:00Z"
  }
}
```

Errores: 400, 405, 415, 422.

### Reglas de validación del mensaje (en este orden)

1. Debe ser `string` (arreglo u objeto → 400).
2. Se eliminan caracteres de control y no imprimibles (`\p{C}`).
3. Acentos y eñe a su letra base (`á → A`, `ñ → N`).
4. Mayúsculas, `trim` y espacios repetidos colapsados.
5. Solo ASCII imprimible `0x20–0x7E`; si queda algo fuera → 422.
6. Longitud de 1 a 50 **después** de normalizar → si no, 422.
7. `velocidad` debe ser un valor del enum → si no, 422.

## `GET /api/dispositivo.php`

Cabeceras: `X-Device-Id: ARDUINO_LAB_01` y `X-Device-Token: <token>`.

Entrega el PENDIENTE **más antiguo** y, en la misma operación atómica (`findOneAndUpdate`),
lo pasa a **PROCESANDO** y lo asocia al dispositivo.

Respuesta `200 OK`:

```json
{ "ok": true, "data": { "id": "66fb1c2e9a1f4b0012ab34cd", "mensaje": "BIENVENIDOS A UMG", "velocidad_ms": 90 } }
```

Respuesta `204 No Content`: no hay pendientes, cuerpo vacío.

Errores: 401, 405.

El texto para el Arduino **no** va escapado con `htmlspecialchars()`: la protección es la
validación de entrada (solo ASCII imprimible), que ya se aplicó al guardar.

## `POST /api/confirmar.php`

Mismas cabeceras que `dispositivo.php`. Se llama al terminar la animación.

```json
{ "id": "66fb1c2e9a1f4b0012ab34cd" }
```

Respuesta `200 OK`:

```json
{ "ok": true, "data": { "id": "66fb1c2e9a1f4b0012ab34cd", "estado": "EMITIDO", "emision": "2026-10-25T10:15:12Z" } }
```

Errores: 400, 401, 404, 405, 409, 415.

Si el Arduino reintenta un ACK cuya primera respuesta se perdió, el mensaje ya está en
EMITIDO y recibe **409**. En `confirmarConReintentos` un 409 se trata como confirmado y se
dejan de hacer reintentos.

## Para el dashboard (Integrante C)

```php
$app = App\Bootstrap::iniciar();                         // también envía las cabeceras de seguridad
$historial = $app->mensajes()->listarHistorial(50);       // list<App\Domain\Mensaje>, más nuevo primero
$pendientes = $app->mensajes()->listarHistorial(50, App\Domain\EstadoMensaje::Pendiente);
$dispositivos = $app->dispositivos()->listar();           // ->enLinea() = visto hace menos de 30 s
$zona = $app->zonaHoraria();                              // para mostrar las fechas en hora de Guatemala
```

Cada valor que se imprima va por `htmlspecialchars($v, ENT_QUOTES | ENT_HTML5, 'UTF-8')`.

## Modelo de datos (`iot_led`)

### `mensajes_log`

```js
{
  _id: ObjectId("66fb1c2e..."),
  mensaje: "BIENVENIDOS A UMG",
  longitud: 17,
  velocidad: "media",
  velocidad_ms: 90,
  estado: "EMITIDO",                  // PENDIENTE → PROCESANDO → EMITIDO
  ip_origen: "190.56.253.12",
  fechas: {
    creacion:   ISODate("2026-10-25T10:15:00Z"),
    procesando: ISODate("2026-10-25T10:15:04Z"),
    emision:    ISODate("2026-10-25T10:15:12Z")
  },
  dispositivo: { id: "ARDUINO_LAB_01", mac_token: "****9988" }   // null en PENDIENTE
}
```

El token nunca se guarda completo: solo enmascarado (`****` + últimos 4).

### `dispositivos`

```js
{ _id: "ARDUINO_LAB_01", token_hash: "<sha256 del token>", activo: true, ultimo_contacto: ISODate("...") }
```

### Índices

```js
db.mensajes_log.createIndex({ estado: 1, "fechas.creacion": 1 })   // cola de pendientes
db.mensajes_log.createIndex({ "fechas.creacion": -1 })             // historial
```

## Seguridad

| Amenaza                     | Control                                                                         |
| --------------------------- | ------------------------------------------------------------------------------- |
| Acceso no autorizado        | `hash('sha256', $token)` contra `token_hash` con `hash_equals()` → 401          |
| Inyección NoSQL             | `is_string()` + `new ObjectId($id)` dentro de `try`; nunca entrada cruda en un filtro |
| Entradas inválidas          | Reglas de validación, cuerpo ≤ 1 KB, `json_decode(..., JSON_THROW_ON_ERROR)`    |
| Fuga de información         | `display_errors=Off`, error genérico al cliente, detalle solo en el log         |
| Cabeceras                   | CSP `default-src 'self'`, `nosniff`, `X-Frame-Options: DENY`, `Referrer-Policy: no-referrer` |
| Secretos                    | `.env` fuera del repo; tokens solo hasheados en la base                         |
