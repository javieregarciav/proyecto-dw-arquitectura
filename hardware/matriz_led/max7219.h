// max7219.h
// Driver propio del MAX7219 sobre SPI.h (sin LedControl ni MD_Parola).
// Trama de 16 bits: [ byte alto = registro ][ byte bajo = dato ], el flanco de subida de CS la latchea.
#pragma once
#include <Arduino.h>
#include <SPI.h>
#include "font.h"   // FONT5X7[95][5] PROGMEM, generado por tools/gen-font.php

#ifndef MAX7219_CS
#define MAX7219_CS 10
#endif
#ifndef MAX7219_INVERTIR_X
#define MAX7219_INVERTIR_X 0
#endif
#ifndef MAX7219_INVERTIR_Y
#define MAX7219_INVERTIR_Y 0
#endif

// Registros del MAX7219
#define MAX_REG_NOOP         0x00
#define MAX_REG_DIGIT0       0x01   // Digit 0..7 = 0x01..0x08 (una fila cada uno)
#define MAX_REG_DECODE_MODE  0x09
#define MAX_REG_INTENSITY    0x0A
#define MAX_REG_SCAN_LIMIT   0x0B
#define MAX_REG_SHUTDOWN     0x0C
#define MAX_REG_DISPLAY_TEST 0x0F

// Escribe un registro: una trama SPI de 16 bits.
inline void max7219Write(uint8_t reg, uint8_t valor) {
  SPI.beginTransaction(SPISettings(1000000, MSBFIRST, SPI_MODE0));
  digitalWrite(MAX7219_CS, LOW);    // inicia la trama
  SPI.transfer(reg);                // byte alto: direccion del registro
  SPI.transfer(valor);              // byte bajo: dato
  digitalWrite(MAX7219_CS, HIGH);   // flanco de subida: latchea el dato
  SPI.endTransaction();
}

inline void max7219Limpiar() {
  for (uint8_t r = 0; r < 8; r++) max7219Write(MAX_REG_DIGIT0 + r, 0x00);
}

inline void max7219Init(uint8_t intensidad = 0x03) {
  pinMode(MAX7219_CS, OUTPUT);
  digitalWrite(MAX7219_CS, HIGH);
  SPI.begin();

  max7219Write(MAX_REG_DISPLAY_TEST, 0x00);   // Display Test apagado
  max7219Write(MAX_REG_SCAN_LIMIT,   0x07);   // las 8 filas
  max7219Write(MAX_REG_DECODE_MODE,  0x00);   // sin decodificacion BCD
  max7219Write(MAX_REG_INTENSITY,    intensidad & 0x0F);
  max7219Limpiar();
  max7219Write(MAX_REG_SHUTDOWN,     0x01);   // operacion normal
}

// Escribe 8 bytes de fila (filas[0] = fila superior).
inline void max7219MostrarFilas(const uint8_t filas[8]) {
  for (uint8_t f = 0; f < 8; f++) {
    uint8_t reg = MAX7219_INVERTIR_Y ? (MAX_REG_DIGIT0 + 7 - f) : (MAX_REG_DIGIT0 + f);
    max7219Write(reg, filas[f]);
  }
}

// TRANSPOSICION: la fuente esta en columnas (bit 0 = fila superior) y el MAX7219 se escribe por filas.
// Entra una ventana de 8 columnas y salen 8 bytes de fila.
// Por defecto la columna 0 es el bit 7 (D7) del byte de fila.
inline void max7219Transponer(const uint8_t cols[8], uint8_t filas[8]) {
  for (uint8_t f = 0; f < 8; f++) {
    uint8_t b = 0;
    for (uint8_t c = 0; c < 8; c++) {
      if ((cols[c] >> f) & 0x01) {
        b |= MAX7219_INVERTIR_X ? (uint8_t)(0x01 << c) : (uint8_t)(0x80 >> c);
      }
    }
    filas[f] = b;
  }
}

// Columna k (0..4) del caracter c, leida desde PROGMEM. Fuera de 0x20..0x7E se dibuja '?'.
inline uint8_t fuenteColumna(char c, uint8_t k) {
  uint8_t u = (uint8_t)c;
  if (u < 0x20 || u > 0x7E) u = '?';
  return pgm_read_byte(&FONT5X7[u - 0x20][k]);
}