#include <WiFiS3.h>
#include <ArduinoJson.h>
#include "config.h"
#include "font.h"
#include "max7219.h"

#if USE_HTTPS
WiFiSSLClient cliente;
#else
WiFiClient cliente;
#endif

const unsigned long POLL_MS = 5000;
unsigned long ultimoPoll = 0;

struct Mensaje {
  char id[32];
  char texto[64];
  unsigned int velocidadMs;
};

uint8_t columnaTexto(const char* t, uint16_t n, uint16_t i) {
  if (i < 8) return 0;
  uint16_t j = i - 8;
  if (j >= (uint16_t)(n * 6)) return 0;
  uint8_t k = j % 6;
  if (k == 5) return 0;
  return fuenteColumna(t[j / 6], k);
}

void matrizScroll(const char* texto, unsigned int velocidadMs) {
  uint16_t n = strlen(texto);
  uint16_t total = 8 + n * 6 + 8;
  uint8_t cols[8], filas[8];

  for (uint16_t s = 0; s + 8 <= total; s++) {
    for (uint8_t c = 0; c < 8; c++) cols[c] = columnaTexto(texto, n, s + c);
    max7219Transponer(cols, filas);
    max7219MostrarFilas(filas);
    delay(velocidadMs);
  }
  max7219Limpiar();
}

void parpadearError() {
  pinMode(LED_BUILTIN, OUTPUT);
  for (uint8_t i = 0; i < 5; i++) {
    digitalWrite(LED_BUILTIN, HIGH); delay(150);
    digitalWrite(LED_BUILTIN, LOW);  delay(150);
  }
}

void conectarWiFi() {
  if (WiFi.status() == WL_NO_MODULE) {
    Serial.println(F("No se detecta el modulo WiFi"));
    while (true) {}
  }
  while (WiFi.status() != WL_CONNECTED) {
    Serial.print(F("Conectando a "));
    Serial.println(WIFI_SSID);
    WiFi.begin(WIFI_SSID, WIFI_PASS);
    delay(8000);
  }
  Serial.print(F("WiFi OK, IP: "));
  Serial.println(WiFi.localIP());
}

int httpPeticion(const char* metodo, const char* ruta, const char* cuerpo,
                 char* resp, size_t respMax) {
  resp[0] = '\0';
  if (!cliente.connect(API_HOST, API_PORT)) return -1;

  cliente.print(metodo); cliente.print(' '); cliente.print(ruta);
  cliente.println(F(" HTTP/1.0"));
  cliente.print(F("Host: "));            cliente.println(API_HOST);
  cliente.print(F("X-Device-Id: "));     cliente.println(DEVICE_ID);
  cliente.print(F("X-Device-Token: "));  cliente.println(DEVICE_TOKEN);
  cliente.println(F("Accept: application/json"));
  if (cuerpo) {
    cliente.println(F("Content-Type: application/json"));
    cliente.print(F("Content-Length: ")); cliente.println(strlen(cuerpo));
  }
  cliente.println(F("Connection: close"));
  cliente.println();
  if (cuerpo) cliente.print(cuerpo);

  unsigned long t0 = millis();
  while (!cliente.available()) {
    if (!cliente.connected() || millis() - t0 > 8000) { cliente.stop(); return -2; }
  }

  char linea[64];
  size_t n = cliente.readBytesUntil('\n', linea, sizeof(linea) - 1);
  linea[n] = '\0';
  int status = (n >= 12) ? atoi(linea + 9) : -3;

  while (true) {
    n = cliente.readBytesUntil('\n', linea, sizeof(linea) - 1);
    linea[n] = '\0';
    if (n == 0 || (n == 1 && linea[0] == '\r')) break;
  }

  size_t len = 0;
  t0 = millis();
  while ((cliente.connected() || cliente.available()) && len < respMax - 1) {
    if (cliente.available()) { resp[len++] = (char)cliente.read(); t0 = millis(); }
    else if (millis() - t0 > 3000) break;
  }
  resp[len] = '\0';
  cliente.stop();
  return status;
}

int pedirMensaje(Mensaje& m) {
  char resp[512];
  int status = httpPeticion("GET", "/api/dispositivo.php", nullptr, resp, sizeof(resp));
  if (status != 200) return status;

  JsonDocument doc;
  if (deserializeJson(doc, resp)) return -4;
  const char* id  = doc["data"]["id"];
  const char* msg = doc["data"]["mensaje"];
  if (!id || !msg) return -4;

  strncpy(m.id, id, sizeof(m.id));
  strncpy(m.texto, msg, sizeof(m.texto));
  m.velocidadMs = doc["data"]["velocidad_ms"] | 90;
  return 200;
}

bool confirmarConReintentos(const char* id, uint8_t intentos) {
  char cuerpo[64];
  char resp[256];
  snprintf(cuerpo, sizeof(cuerpo), "{\"id\":\"%s\"}", id);

  for (uint8_t i = 0; i < intentos; i++) {
    int status = httpPeticion("POST", "/api/confirmar.php", cuerpo, resp, sizeof(resp));
    Serial.print(F("ACK intento ")); Serial.print(i + 1);
    Serial.print(F(" -> ")); Serial.println(status);
    if (status == 200) return true;
    if (status == 401 || status == 404 || status == 409) return false;
    delay(1000UL * (i + 1));
  }
  return false;
}

void setup() {
  Serial.begin(115200);
  max7219Init();
  cliente.setTimeout(3000);
#if !MODO_SOLO_SCROLL
  conectarWiFi();
#endif
}

void loop() {
#if MODO_SOLO_SCROLL
  matrizScroll("HOLA UMG", 90);
  delay(1000);
#else
  if (millis() - ultimoPoll < POLL_MS) return;
  ultimoPoll = millis();

  if (WiFi.status() != WL_CONNECTED) {
    Serial.println(F("WiFi caido, reconectando..."));
    WiFi.begin(WIFI_SSID, WIFI_PASS);
    return;
  }

  Mensaje m;
  int status = pedirMensaje(m);

  if (status == 200) {
    Serial.print(F("Mensaje: ")); Serial.println(m.texto);
    matrizScroll(m.texto, m.velocidadMs);
    if (!confirmarConReintentos(m.id, 3)) {
      Serial.println(F("No se pudo confirmar el ACK"));
      parpadearError();
    }
  } else if (status == 204) {
    Serial.println(F("Sin pendientes"));
  } else if (status == 401) {
    Serial.println(F("401: revisa DEVICE_ID / DEVICE_TOKEN"));
    parpadearError();
  } else {
    Serial.print(F("Error de red o respuesta: ")); Serial.println(status);
  }
#endif
}
