// ============================================================
//  ENFASIS_WIFI.ino  —  con GPS NEO-6M
//  Sensores: DS18B20, MPU6050, Pulso, GPS NEO-6M
//  Conexión GPS: TX→GPIO16 (RX2), RX→GPIO17 (TX2)
// ============================================================

#include <Wire.h>
#include <math.h>
#include <OneWire.h>
#include <DallasTemperature.h>
#include <WiFi.h>
#include <HTTPClient.h>
#include <TinyGPSPlus.h>

// ──────────────── WIFI ────────────────
String deviceMAC = "";
const char* WIFI_SSID  = "Pomona";
const char* WIFI_PASS  = "3125440140";
const char* SERVER_URL = "http://192.168.1.30/collarperro/guardar.php";

// ──────────────── PINES ────────────────
#define ONE_WIRE_BUS     4
#define SDA_PIN          21
#define SCL_PIN          22
#define PULSE_PIN        34
#define PULSE_POWER_PIN  25
#define GPS_RX_PIN       16   // TX del NEO-6M → GPIO16
#define GPS_TX_PIN       17   // RX del NEO-6M → GPIO17

// ──────────────── GPS ────────────────
TinyGPSPlus    gps;
HardwareSerial gpsSerial(2);   // UART2

float  gpsLat      = 0.0;
float  gpsLng      = 0.0;
bool   gpsValido   = false;
unsigned long ultimaLecturaGPS = 0;
#define GPS_TIMEOUT_MS  3000   // si no hay fix en 3s, envía 0,0

// ──────────────── MPU ────────────────
#define MPU_ADDR        0x68
#define ACC_SENS        16384.0
#define WINDOW_SIZE     3
#define UMBRAL_REPOSO   0.05

// ──────────────── GESTIÓN DE ENERGÍA ────────────────
#define INTERVALO_SONDEO_MS    5000
#define TIMEOUT_INACTIVIDAD_MS 10000
#define DELAY_ESTABILIZAR_MS   80
#define DELAY_PULSO_ARRANQUE   2000

// ──────────────── INTERVALOS ────────────────
#define INTERVALO_ACTIVO_MS  1000
#define INTERVALO_REPOSO_MS  5000

// ──────────────── PULSO ────────────────
#define PULSE_SAMPLE_MS      5
#define PICOS_BUFFER         8
#define UMBRAL_AMPLITUD_MIN  80
#define BPM_TIMEOUT_MS       3000

// ══════════════════════════════════════════════
//  MÁQUINA DE ESTADOS
// ══════════════════════════════════════════════
enum EstadoSistema { MODO_REPOSO, MODO_ACTIVO };
EstadoSistema estadoSistema = MODO_REPOSO;

unsigned long tiempoUltimaActividad = 0;
unsigned long ultimoSondeo          = 0;
bool          pulsoCalibrando       = false;
unsigned long tiempoEncendidoPulso  = 0;

// ──────────────── OBJETOS ────────────────
OneWire           oneWire(ONE_WIRE_BUS);
DallasTemperature ds18b20(&oneWire);

// ──────────────── VARIABLES MPU ────────────────
int16_t axR, ayR, azR;
float   ax, ay, az;
float   bufferMag[WINDOW_SIZE];
int     bufferIdx = 0;
float   sumMag    = 0.0;

// ──────────────── VARIABLES PULSO ────────────────
unsigned long ultimoMuestreoPulso = 0;
unsigned long tiempoUltimoPico    = 0;
unsigned long intervalosPicos[PICOS_BUFFER];
int           indicePico          = 0;
int           picosValidos        = 0;
bool          encimaPico          = false;
int           umbralPulso         = 2048;
int           minSignal           = 4095;
int           maxSignal           = 0;

unsigned long lastReport = 0;

// ══════════════════════════════════════════════
//  PROTOTIPOS
// ══════════════════════════════════════════════
void  mpuDormir();
void  mpuDesperar();
void  encenderPulso();
void  apagarPulso();
void  resetearBufferPulso();
void  leerMPU();
float calcularMagnitud();
float aplicarPromedio(float nueva);
void  muestrearPulso();
int   calcularBPM();
bool  sondeaMovimiento();
void  leerGPS();
void  enviarDatos(float temp, float accel, int bpm, String modo);
void  conectarWiFi();

// ══════════════════════════════════════════════
//  SETUP
// ══════════════════════════════════════════════
void setup() {
  Serial.begin(115200);
  delay(1000);
  setCpuFrequencyMhz(80);

  // GPS Serial
  gpsSerial.begin(9600, SERIAL_8N1, GPS_RX_PIN, GPS_TX_PIN);
  Serial.println("[GPS] Serial iniciado en UART2");

  pinMode(PULSE_POWER_PIN, OUTPUT);
  digitalWrite(PULSE_POWER_PIN, LOW);

  Wire.begin(SDA_PIN, SCL_PIN);
  Wire.beginTransmission(MPU_ADDR);
  Wire.write(0x6B);
  Wire.write(0x00);
  Wire.endTransmission(true);
  delay(50);
  mpuDormir();

  ds18b20.begin();

  for (int i = 0; i < WINDOW_SIZE; i++) bufferMag[i] = 1.0;
  sumMag = WINDOW_SIZE * 1.0;
  for (int i = 0; i < PICOS_BUFFER; i++) intervalosPicos[i] = 0;

  analogReadResolution(12);
  analogSetAttenuation(ADC_11db);

  conectarWiFi();
  deviceMAC = WiFi.macAddress();
  Serial.print("[DISPOSITIVO] MAC: ");
  Serial.println(deviceMAC);
  Serial.println("[SISTEMA] Listo | MODO REPOSO");
}

// ══════════════════════════════════════════════
//  LOOP
// ══════════════════════════════════════════════
void loop() {

  // Leer GPS en cada ciclo (no bloquea)
  leerGPS();

  if (WiFi.status() != WL_CONNECTED) {
    Serial.println("[WiFi] Reconectando...");
    conectarWiFi();
  }

  unsigned long ahora = millis();

  // ════════════════════════════════
  //  MODO REPOSO
  // ════════════════════════════════
  if (estadoSistema == MODO_REPOSO) {

    if (ahora - ultimoSondeo >= INTERVALO_SONDEO_MS) {
      ultimoSondeo = ahora;

      mpuDesperar();
      delay(DELAY_ESTABILIZAR_MS);
      bool hayMovimiento = sondeaMovimiento();
      mpuDormir();

      if (hayMovimiento) {
        Serial.println("[SISTEMA] Movimiento → MODO ACTIVO");
        estadoSistema         = MODO_ACTIVO;
        tiempoUltimaActividad = ahora;
        mpuDesperar();
        encenderPulso();
        pulsoCalibrando      = true;
        tiempoEncendidoPulso = ahora;
      } else {
        ds18b20.requestTemperatures();
        float tempC = ds18b20.getTempCByIndex(0);
        Serial.print("[REPOSO] Temp:"); Serial.print(tempC);
        Serial.print(" | GPS:");
        if (gpsValido) { Serial.print(gpsLat,6); Serial.print(","); Serial.println(gpsLng,6); }
        else Serial.println("sin fix");
        enviarDatos(tempC, 0.0, 0, "REPOSO");
      }
    }
  }

  // ════════════════════════════════
  //  MODO ACTIVO
  // ════════════════════════════════
  else {

    muestrearPulso();

    if (pulsoCalibrando) {
      if (ahora - tiempoEncendidoPulso < DELAY_PULSO_ARRANQUE) return;
      pulsoCalibrando = false;
      Serial.println("[PULSO] Calibración lista");
    }

    if (ahora - lastReport >= INTERVALO_ACTIVO_MS) {
      lastReport = ahora;

      ds18b20.requestTemperatures();
      float tempC = ds18b20.getTempCByIndex(0);

      leerMPU();
      float mag    = calcularMagnitud();
      float din    = fabs(mag - 1.0);
      float avgDin = aplicarPromedio(din);

      int bpm = calcularBPM();

      Serial.print("[ACTIVO] Temp:"); Serial.print(tempC,1);
      Serial.print(" | Accel:");      Serial.print(avgDin,3);
      Serial.print(" | BPM:");        Serial.print(bpm);
      Serial.print(" | GPS:");
      if (gpsValido) { Serial.print(gpsLat,6); Serial.print(","); Serial.println(gpsLng,6); }
      else Serial.println("sin fix");

      enviarDatos(tempC, avgDin, bpm, "ACTIVO");

      if (avgDin >= UMBRAL_REPOSO) tiempoUltimaActividad = ahora;
      if (ahora - tiempoUltimaActividad >= TIMEOUT_INACTIVIDAD_MS) {
        Serial.println("[SISTEMA] Inactividad → MODO REPOSO");
        estadoSistema = MODO_REPOSO;
        mpuDormir();
        apagarPulso();
        resetearBufferPulso();
        lastReport = 0;
      }
    }
  }
}

// ══════════════════════════════════════════════
//  GPS — lectura no bloqueante
// ══════════════════════════════════════════════
void leerGPS() {
  while (gpsSerial.available() > 0) {
    char c = gpsSerial.read();
    gps.encode(c);
  }

  if (gps.location.isUpdated() && gps.location.isValid()) {
    gpsLat    = gps.location.lat();
    gpsLng    = gps.location.lng();
    gpsValido = true;
    ultimaLecturaGPS = millis();
  }

  // Si no llega fix en GPS_TIMEOUT_MS, marcar como inválido
  if (gpsValido && (millis() - ultimaLecturaGPS > GPS_TIMEOUT_MS)) {
    gpsValido = false;
  }
}

// ══════════════════════════════════════════════
//  ENVÍO HTTP
// ══════════════════════════════════════════════
void enviarDatos(float temp, float accel, int bpm, String modo) {

  if (WiFi.status() != WL_CONNECTED) {
    Serial.println("[HTTP] Sin WiFi, dato descartado");
    return;
  }

  float latEnviar = gpsValido ? gpsLat : 0.0;
  float lngEnviar = gpsValido ? gpsLng : 0.0;

  HTTPClient http;
  http.begin(SERVER_URL);
  http.addHeader("Content-Type", "application/x-www-form-urlencoded");

  char body[200];
  char macBuf[20];
  deviceMAC.toCharArray(macBuf, sizeof(macBuf));
  snprintf(body, sizeof(body),
    "temp=%.2f&accel=%.4f&bpm=%d&modo=%s&mac=%s&lat=%.6f&lng=%.6f",
    temp, accel, bpm, modo.c_str(), macBuf, latEnviar, lngEnviar
  );

  int httpCode = http.POST(body);
  if (httpCode > 0) {
    Serial.print("[HTTP] POST OK → "); Serial.println(httpCode);
  } else {
    Serial.print("[HTTP] ERROR: "); Serial.println(http.errorToString(httpCode));
  }
  http.end();
}

// ══════════════════════════════════════════════
//  WIFI
// ══════════════════════════════════════════════
void conectarWiFi() {
  WiFi.begin(WIFI_SSID, WIFI_PASS);
  Serial.print("[WiFi] Conectando");
  int intentos = 0;
  while (WiFi.status() != WL_CONNECTED && intentos < 20) {
    delay(500); Serial.print("."); intentos++;
  }
  if (WiFi.status() == WL_CONNECTED) {
    Serial.print("\n[WiFi] Conectado! IP: "); Serial.println(WiFi.localIP());
  } else {
    Serial.println("\n[WiFi] FALLO");
  }
}

// ══════════════════════════════════════════════
//  MPU6050
// ══════════════════════════════════════════════
void mpuDormir() {
  Wire.beginTransmission(MPU_ADDR); Wire.write(0x6B); Wire.write(0x40); Wire.endTransmission(true);
}
void mpuDesperar() {
  Wire.beginTransmission(MPU_ADDR); Wire.write(0x6B); Wire.write(0x00); Wire.endTransmission(true);
}
bool sondeaMovimiento() {
  Wire.beginTransmission(MPU_ADDR); Wire.write(0x3B); Wire.endTransmission(false);
  Wire.requestFrom(MPU_ADDR, 6, true);
  int16_t sAxR = Wire.read()<<8|Wire.read();
  int16_t sAyR = Wire.read()<<8|Wire.read();
  int16_t sAzR = Wire.read()<<8|Wire.read();
  float sAx=sAxR/ACC_SENS, sAy=sAyR/ACC_SENS, sAz=sAzR/ACC_SENS;
  return fabs(sqrt(sAx*sAx+sAy*sAy+sAz*sAz)-1.0) >= UMBRAL_REPOSO;
}
void leerMPU() {
  Wire.beginTransmission(MPU_ADDR); Wire.write(0x3B); Wire.endTransmission(false);
  Wire.requestFrom(MPU_ADDR, 6, true);
  axR=Wire.read()<<8|Wire.read(); ayR=Wire.read()<<8|Wire.read(); azR=Wire.read()<<8|Wire.read();
  ax=axR/ACC_SENS; ay=ayR/ACC_SENS; az=azR/ACC_SENS;
}
float calcularMagnitud() { return sqrt(ax*ax+ay*ay+az*az); }
float aplicarPromedio(float nueva) {
  sumMag -= bufferMag[bufferIdx];
  bufferMag[bufferIdx] = nueva;
  sumMag += nueva;
  bufferIdx = (bufferIdx+1)%WINDOW_SIZE;
  return sumMag/WINDOW_SIZE;
}

// ══════════════════════════════════════════════
//  SENSOR DE PULSO
// ══════════════════════════════════════════════
void encenderPulso() { digitalWrite(PULSE_POWER_PIN, HIGH); }
void apagarPulso()   { digitalWrite(PULSE_POWER_PIN, LOW);  }
void resetearBufferPulso() {
  for (int i=0;i<PICOS_BUFFER;i++) intervalosPicos[i]=0;
  indicePico=0; picosValidos=0; encimaPico=false;
  umbralPulso=2048; minSignal=4095; maxSignal=0; tiempoUltimoPico=0;
}
void muestrearPulso() {
  if (millis()-ultimoMuestreoPulso < PULSE_SAMPLE_MS) return;
  ultimoMuestreoPulso = millis();
  int valor = analogRead(PULSE_PIN);
  if (valor<minSignal) minSignal=valor;
  if (valor>maxSignal) maxSignal=valor;
  int amplitud = maxSignal-minSignal;
  if (amplitud>UMBRAL_AMPLITUD_MIN) umbralPulso=minSignal+(amplitud/2)+(amplitud/10);
  minSignal+=1; maxSignal-=1;
  unsigned long ahora=millis();
  if (!encimaPico && valor>umbralPulso) {
    encimaPico=true;
    unsigned long intervalo=ahora-tiempoUltimoPico;
    if (tiempoUltimoPico>0 && intervalo>300 && intervalo<2000) {
      intervalosPicos[indicePico%PICOS_BUFFER]=intervalo;
      indicePico++;
      if (picosValidos<PICOS_BUFFER) picosValidos++;
    }
    tiempoUltimoPico=ahora;
  }
  if (encimaPico && valor<umbralPulso) encimaPico=false;
}
int calcularBPM() {
  if (picosValidos<2) return 0;
  if (millis()-tiempoUltimoPico>BPM_TIMEOUT_MS) { picosValidos=0; return 0; }
  unsigned long suma=0;
  int n=min(picosValidos,PICOS_BUFFER);
  for (int i=0;i<n;i++) suma+=intervalosPicos[i];
  return (int)(60000.0/((float)suma/n));
}
