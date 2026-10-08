#!/usr/bin/env bash
# Dispositivo simulado: hace exactamente lo que hará el Arduino.
# Uso: TOKEN=<token> ./tools/fake-device.sh
set -euo pipefail

API="${API:-http://localhost:8080/api}"
DEVICE_ID="${DEVICE_ID:-ARDUINO_LAB_01}"
H=(-H "X-Device-Id: ${DEVICE_ID}" -H "X-Device-Token: ${TOKEN:?definir TOKEN}")

resp=$(curl -s -w '\n%{http_code}' "${H[@]}" "$API/dispositivo.php")
code=$(tail -n1 <<<"$resp"); body=$(sed '$d' <<<"$resp")

[[ "$code" == "204" ]] && { echo "Sin pendientes"; exit 0; }
[[ "$code" != "200" ]] && { echo "Error $code: $body"; exit 1; }

id=$(jq -r '.data.id' <<<"$body")
echo "Mostrando: $(jq -r '.data.mensaje' <<<"$body") ($(jq -r '.data.velocidad_ms' <<<"$body") ms/columna)"
sleep 3

curl -s "${H[@]}" -H 'Content-Type: application/json' \
  -d "{\"id\":\"$id\"}" "$API/confirmar.php" | jq
