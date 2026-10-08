#!/usr/bin/env bash
# Colección de pruebas con curl contra el contrato de la API.
# Uso: TOKEN=<token> ./tools/pruebas-api.sh
set -uo pipefail

API="${API:-http://localhost:8080/api}"
DEVICE_ID="${DEVICE_ID:-ARDUINO_LAB_01}"
TOKEN="${TOKEN:?definir TOKEN}"
JSON=(-H 'Content-Type: application/json')
DISP=(-H "X-Device-Id: ${DEVICE_ID}" -H "X-Device-Token: ${TOKEN}")
fallos=0

probar() {
  local nombre="$1" esperado="$2"; shift 2
  local codigo
  codigo=$(curl -s -o /dev/null -w '%{http_code}' "$@")
  if [[ "$codigo" == "$esperado" ]]; then
    printf '  OK    %-55s %s\n' "$nombre" "$codigo"
  else
    printf '  FALLA %-55s %s (esperado %s)\n' "$nombre" "$codigo" "$esperado"
    fallos=$((fallos + 1))
  fi
}

echo "POST /mensaje.php"
probar "mensaje válido → 201"                 201 "${JSON[@]}" -d '{"mensaje":"Hola UMG","velocidad":"media"}' "$API/mensaje.php"
probar "XSS se guarda como texto → 201"       201 "${JSON[@]}" -d '{"mensaje":"<script>alert(1)</script>","velocidad":"rapida"}' "$API/mensaje.php"
probar "51 caracteres → 422"                  422 "${JSON[@]}" -d "{\"mensaje\":\"$(printf 'A%.0s' {1..51})\",\"velocidad\":\"media\"}" "$API/mensaje.php"
probar "velocidad fuera del enum → 422"       422 "${JSON[@]}" -d '{"mensaje":"Hola","velocidad":"turbo"}' "$API/mensaje.php"
probar "mensaje como arreglo → 400"           400 "${JSON[@]}" -d '{"mensaje":["x"],"velocidad":"media"}' "$API/mensaje.php"
probar "JSON mal formado → 400"               400 "${JSON[@]}" -d '{"mensaje":' "$API/mensaje.php"
probar "cuerpo de más de 1 KB → 400"          400 "${JSON[@]}" -d "{\"mensaje\":\"$(printf 'A%.0s' {1..1100})\"}" "$API/mensaje.php"
probar "sin Content-Type JSON → 415"          415 -H 'Content-Type: text/plain' -d 'hola' "$API/mensaje.php"
probar "GET no permitido → 405"               405 "$API/mensaje.php"

echo "GET /dispositivo.php"
probar "sin token → 401"                      401 "$API/dispositivo.php"
probar "token incorrecto → 401"               401 -H "X-Device-Id: ${DEVICE_ID}" -H 'X-Device-Token: malo' "$API/dispositivo.php"
probar "POST no permitido → 405"              405 -X POST "${DISP[@]}" "$API/dispositivo.php"
probar "toma el más antiguo → 200"            200 "${DISP[@]}" "$API/dispositivo.php"

echo "POST /confirmar.php"
probar "sin token → 401"                      401 "${JSON[@]}" -d '{"id":"66fb1c2e9a1f4b0012ab34cd"}' "$API/confirmar.php"
probar "id inválido → 400"                    400 "${DISP[@]}" "${JSON[@]}" -d '{"id":"123"}' "$API/confirmar.php"
probar "inyección id[\$ne] → 400"             400 "${DISP[@]}" "${JSON[@]}" -d '{"id":{"$ne":""}}' "$API/confirmar.php"
probar "id inexistente → 404"                 404 "${DISP[@]}" "${JSON[@]}" -d '{"id":"000000000000000000000000"}' "$API/confirmar.php"
probar "sin Content-Type JSON → 415"          415 "${DISP[@]}" -d 'id=1' "$API/confirmar.php"

id=$(curl -s "${JSON[@]}" -d '{"mensaje":"ack","velocidad":"rapida"}' "$API/mensaje.php" | jq -r '.data.id')
probar "ACK de un PENDIENTE → 409"            409 "${DISP[@]}" "${JSON[@]}" -d "{\"id\":\"$id\"}" "$API/confirmar.php"

echo
if (( fallos > 0 )); then
  echo "$fallos prueba(s) fallaron"; exit 1
fi
echo "Todas las pruebas pasaron"
