/**
 * Collar Perro Inteligente IoT - Monitor de Salud y Actividad Canina
 * 
 * Autores: Antonio José Lizcano Rojas, Lili Yasmin Salamanca Chito
 * Version: 2.0
 * Fecha: Abril 2026
 * Plataforma: ESP32 (Arduino IDE)
 * Licencia: MIT
 * 
 * DESCRIPCION:
 * Sistema de monitoreo continuo para perros que mide:
 * - Temperatura corporal (DS18B20)
 * - Frecuencia cardiaca (sensor optico HW-827)
 * - Nivel de actividad fisica (MPU6050)
 * - Estimacion de estres (algoritmo basado en BPM y temperatura)
 * 
 * COMUNICACION: Bluetooth Low Energy (BLE)
 * Cliente: App NFC Connect (Android/iOS)
 * 
 * CONEXIONES HARDWARE:
 * 
 * ESP32    | DS18B20    | MPU6050    | HW-827
 * ---------|------------|------------|--------
 * GPIO 4   | DQ (Data)  |            |
 * GPIO 21  |            | SDA        |
 * GPIO 22  |            | SCL        |
 * GPIO 34  |            |            | Signal
 * GPIO 25  |            |            | VCC Ctrl
 * 3.3V     | VCC        | VCC        | 
 * GND      | GND        | GND        | GND
 * 
 * CARACTERISTICAS:
 * - Maquina de estados: REPOSO / ACTIVO
 * - Optimizacion energetica automatica
 * - 7 caracteristicas BLE notificables
 * - Consumo: 0.1mA (reposo) / 15mA (activo)
 * 
 * REPOSITORIO: https://github.com/iantoniojos/Collar-Inteligente-para-Monitoreo-Canino.git
 */

#include <Wire.h>
#include <math.h>
#include <OneWire.h>
#include <DallasTemperature.h>
#include <BLEDevice.h>
#include <BLEServer.h>
#include <BLEUtils.h>
#include <BLE2902.h>
#include <BLEDescriptor.h>

// Pines de hardware
#define ONE_WIRE_BUS     4     // DS18B20 data
#define SDA_PIN          21    // MPU6050 I2C SDA
#define SCL_PIN          22    // MPU6050 I2C SCL
#define PULSE_PIN        34    // HW-827 signal (ADC)
#define PULSE_POWER_PIN  25    // HW-827 VCC control

// Configuracion MPU6050
#define MPU_ADDR         0x68
#define ACC_SENS         16384.0  // Sensibilidad ±2g
#define WINDOW_SIZE      3
#define UMBRAL_REPOSO    0.05
#define UMBRAL_MODERADO  0.20

// Rangos temperatura corporal perro (°C)
#define TEMP_HIPOTERMIA    36.0
#define TEMP_NORMAL_MIN    37.5
#define TEMP_NORMAL_MAX    39.2
#define TEMP_FIEBRE        39.5
#define TEMP_FIEBRE_ALTA   40.5

// Rangos frecuencia cardiaca perro (BPM)
#define BPM_MUY_BAJO          50
#define BPM_REPOSO_NORMAL_MIN 60
#define BPM_REPOSO_NORMAL_MAX 100
#define BPM_ACTIVO_MAX        140
#define BPM_ALTO_ESTRES       120
#define BPM_MUY_ALTO          160

// Configuracion sensor pulso
#define PULSE_SAMPLE_MS       5
#define PICOS_BUFFER          8
#define UMBRAL_AMPLITUD_MIN   80
#define BPM_TIMEOUT_MS        3000

// Temporizadores energia
#define INTERVALO_SONDEO_MS      5000
#define TIMEOUT_INACTIVIDAD_MS   10000
#define DELAY_ESTABILIZAR_MS     80
#define DELAY_PULSO_ARRANQUE     2000

// Intervalos reporte BLE
#define INTERVALO_ACTIVO_MS  1000
#define INTERVALO_REPOSO_MS  5000

// UUIDs servicio BLE
#define SERVICE_UUID           "12345678-1234-1234-1234-123456789abc"
#define CHAR_TEMPERATURA_UUID  "12345678-1234-1234-1234-123456789ab1"
#define CHAR_ACTIVIDAD_UUID    "12345678-1234-1234-1234-123456789ab2"
#define CHAR_PULSO_UUID        "12345678-1234-1234-1234-123456789ab3"
#define CHAR_ESTADO_TEMP_UUID  "12345678-1234-1234-1234-123456789ab4"
#define CHAR_ESTRES_UUID       "12345678-1234-1234-1234-123456789ab5"
#define CHAR_MODO_UUID         "12345678-1234-1234-1234-123456789ab6"

// Maquina de estados
enum EstadoSistema { MODO_REPOSO, MODO_ACTIVO };
EstadoSistema estadoSistema = MODO_REPOSO;

unsigned long tiempoUltimaActividad = 0;
unsigned long ultimoSondeo          = 0;
bool          pulsoCalibrando       = false;
unsigned long tiempoEncendidoPulso  = 0;

// Objetos sensores
OneWire           oneWire(ONE_WIRE_BUS);
DallasTemperature ds18b20(&oneWire);

BLEServer*          pServer = NULL;
BLECharacteristic*  pCharTemp = NULL;
BLECharacteristic*  pCharActividad = NULL;
BLECharacteristic*  pCharPulso = NULL;
BLECharacteristic*  pCharEstTemp = NULL;
BLECharacteristic*  pCharEstres = NULL;
BLECharacteristic*  pCharModo = NULL;
bool deviceConectado = false;
bool deviceAnterior = false;

// Variables MPU6050
int16_t axR, ayR, azR;
float   ax, ay, az;
float   bufferMag[WINDOW_SIZE];
int     bufferIdx = 0;
float   sumMag = 0.0;

// Variables sensor pulso
unsigned long ultimoMuestreoPulso = 0;
unsigned long tiempoUltimoPico = 0;
unsigned long intervalosPicos[PICOS_BUFFER];
int           indicePico = 0;
int           picosValidos = 0;
bool          encimaPico = false;
int           umbralPulso = 2048;
int           minSignal = 4095;
int           maxSignal = 0;

unsigned long lastReport = 0;

// Callbacks BLE
class ServerCallbacks: public BLEServerCallbacks {
    void onConnect(BLEServer* pServer) override {
        deviceConectado = true;
        Serial.println("BLE: Cliente conectado");
    }

    void onDisconnect(BLEServer* pServer) override {
        deviceConectado = false;
        Serial.println("BLE: Cliente desconectado");
    }
};

// Prototipos funciones
void mpuDormir();
void mpuDesperar();
void encenderPulso();
void apagarPulso();
void resetearBufferPulso();
void leerMPU();
float calcularMagnitud();
float aplicarPromedio(float nueva);
String clasificar(float din);
String evaluarTemperatura(float t);
void muestrearPulso();
int calcularBPM();
String evaluarPulso(int bpm, const String& actividad);
String evaluarEstres(int bpm, const String& actividad, float tempC);
bool sondeaMovimiento();
BLECharacteristic* crearCaracteristica(BLEService* svc, const char* uuid, const char* descripcion);

void setup() {
    Serial.begin(115200);
    delay(1000);
    setCpuFrequencyMhz(80);

    Serial.println("Collar Perro Inteligente IoT v2.0");
    Serial.println("=================================");

    // Configurar control energia sensor pulso
    pinMode(PULSE_POWER_PIN, OUTPUT);
    digitalWrite(PULSE_POWER_PIN, LOW);
    Serial.println("Pulso: Sensor apagado (ahorro energia)");

    // Inicializar MPU6050
    Wire.begin(SDA_PIN, SCL_PIN);
    Wire.beginTransmission(MPU_ADDR);
    Wire.write(0x6B);
    Wire.write(0x00);
    Wire.endTransmission(true);
    delay(50);
    mpuDormir();

    // Inicializar DS18B20
    ds18b20.begin();

    // Inicializar buffers
    for (int i = 0; i < WINDOW_SIZE; i++) bufferMag[i] = 1.0;
    sumMag = WINDOW_SIZE * 1.0;
    for (int i = 0; i < PICOS_BUFFER; i++) intervalosPicos[i] = 0;

    // Configurar ADC
    analogReadResolution(12);
    analogSetAttenuation(ADC_11db);

    // Inicializar BLE
    BLEDevice::init("CollarPerro");
    pServer = BLEDevice::createServer();
    pServer->setCallbacks(new ServerCallbacks());

    BLEService* pService = pServer->createService(SERVICE_UUID);
    
    pCharTemp = crearCaracteristica(pService, CHAR_TEMPERATURA_UUID, "Temperatura C");
    pCharActividad = crearCaracteristica(pService, CHAR_ACTIVIDAD_UUID, "Estado Actividad");
    pCharPulso = crearCaracteristica(pService, CHAR_PULSO_UUID, "Pulso Cardiaco");
    pCharEstTemp = crearCaracteristica(pService, CHAR_ESTADO_TEMP_UUID, "Estado Temperatura");
    pCharEstres = crearCaracteristica(pService, CHAR_ESTRES_UUID, "Nivel Estres");
    pCharModo = crearCaracteristica(pService, CHAR_MODO_UUID, "Modo Sistema");
    
    pService->start();

    BLEAdvertising* pAdv = BLEDevice::getAdvertising();
    pAdv->addServiceUUID(SERVICE_UUID);
    pAdv->setScanResponse(true);
    BLEDevice::startAdvertising();

    Serial.println("BLE activo | MODO REPOSO | Pulso apagado");
    Serial.println("=================================");
}

void loop() {
    // Gestion conexion BLE
    if (!deviceConectado && deviceAnterior) {
        delay(500);
        pServer->startAdvertising();
        Serial.println("BLE: Re-anunciando...");
        deviceAnterior = false;
    }
    if (deviceConectado && !deviceAnterior) deviceAnterior = true;

    unsigned long ahora = millis();

    // MODO REPOSO
    if (estadoSistema == MODO_REPOSO) {
        // Sondeo periodico MPU
        if (ahora - ultimoSondeo >= INTERVALO_SONDEO_MS) {
            ultimoSondeo = ahora;
            mpuDesperar();
            delay(DELAY_ESTABILIZAR_MS);
            bool hayMovimiento = sondeaMovimiento();
            mpuDormir();

            if (hayMovimiento) {
                Serial.println("Sistema: Movimiento detectado -> MODO ACTIVO");
                estadoSistema = MODO_ACTIVO;
                tiempoUltimaActividad = ahora;
                mpuDesperar();
                encenderPulso();
                pulsoCalibrando = true;
                tiempoEncendidoPulso = ahora;
            }
        }

        // Reporte temperatura en reposo
        if (ahora - lastReport >= INTERVALO_REPOSO_MS) {
            lastReport = ahora;
            ds18b20.requestTemperatures();
            float tempC = ds18b20.getTempCByIndex(0);
            String estadoTemp = evaluarTemperatura(tempC);

            Serial.print("REPOSO - Temp: ");
            Serial.print(tempC, 1);
            Serial.print(" C -> ");
            Serial.println(estadoTemp);

            if (deviceConectado) {
                char bufTemp[8];
                dtostrf(tempC, 4, 1, bufTemp);
                pCharTemp->setValue(bufTemp);
                pCharTemp->notify();
                pCharEstTemp->setValue(estadoTemp.c_str());
                pCharEstTemp->notify();
                pCharActividad->setValue("REPOSO - SENSORES AHORRANDO ENERGIA");
                pCharActividad->notify();
                pCharPulso->setValue("-- SENSOR APAGADO --");
                pCharPulso->notify();
                pCharEstres->setValue("SIN DATOS EN REPOSO");
                pCharEstres->notify();
                pCharModo->setValue("MODO REPOSO");
                pCharModo->notify();
            }
        }
        return;
    }

    // MODO ACTIVO
    if (pulsoCalibrando) {
        if (ahora - tiempoEncendidoPulso >= DELAY_PULSO_ARRANQUE) {
            pulsoCalibrando = false;
            Serial.println("Pulso: Listo para lectura");
        } else {
            leerMPU();
            return;
        }
    }

    muestrearPulso();

    if (ahora - lastReport >= INTERVALO_ACTIVO_MS) {
        lastReport = ahora;

        // Lectura MPU6050
        leerMPU();
        float accMag = calcularMagnitud();
        float accFiltrada = aplicarPromedio(accMag);
        float accelDin = fabs(accFiltrada - 1.0);
        String actividad = clasificar(accelDin);
        if (accelDin >= UMBRAL_REPOSO) tiempoUltimaActividad = ahora;

        // Lectura temperatura
        ds18b20.requestTemperatures();
        float tempC = ds18b20.getTempCByIndex(0);
        String estadoTemp = evaluarTemperatura(tempC);

        // Calculo pulso
        int bpm = calcularBPM();
        String estadoPulso = evaluarPulso(bpm, actividad);
        String estadoEstres = evaluarEstres(bpm, actividad, tempC);

        // Log consola
        Serial.print("ACTIVO - Temp: ");
        Serial.print(tempC, 1);
        Serial.print(" C -> ");
        Serial.print(estadoTemp);
        Serial.print(" | din: ");
        Serial.print(accelDin, 4);
        Serial.print("g -> ");
        Serial.print(actividad);
        Serial.print(" | BPM: ");
        Serial.print(bpm);
        Serial.print(" -> ");
        Serial.print(estadoPulso);
        Serial.print(" | ESTRES: ");
        Serial.println(estadoEstres);

        if (deviceConectado) {
            char bufTemp[8];
            dtostrf(tempC, 4, 1, bufTemp);
            pCharTemp->setValue(bufTemp);
            pCharTemp->notify();
            pCharActividad->setValue(actividad.c_str());
            pCharActividad->notify();

            char bufPulso[32];
            snprintf(bufPulso, sizeof(bufPulso), "%d BPM - %s", bpm, estadoPulso.c_str());
            pCharPulso->setValue(bufPulso);
            pCharPulso->notify();
            pCharEstTemp->setValue(estadoTemp.c_str());
            pCharEstTemp->notify();
            pCharEstres->setValue(estadoEstres.c_str());
            pCharEstres->notify();
            pCharModo->setValue("MODO ACTIVO");
            pCharModo->notify();
        }

        // Timeout inactividad
        if (ahora - tiempoUltimaActividad >= TIMEOUT_INACTIVIDAD_MS) {
            Serial.println("Sistema: Inactividad -> MODO REPOSO");
            estadoSistema = MODO_REPOSO;
            mpuDormir();
            apagarPulso();
            resetearBufferPulso();
            lastReport = 0;
        }
    }
}

// Gestion energia MPU6050
void mpuDormir() {
    Wire.beginTransmission(MPU_ADDR);
    Wire.write(0x6B);
    Wire.write(0x40);
    Wire.endTransmission(true);
    Serial.println("MPU: Sleep");
}

void mpuDesperar() {
    Wire.beginTransmission(MPU_ADDR);
    Wire.write(0x6B);
    Wire.write(0x00);
    Wire.endTransmission(true);
    Serial.println("MPU: Despertado");
}

bool sondeaMovimiento() {
    Wire.beginTransmission(MPU_ADDR);
    Wire.write(0x3B);
    Wire.endTransmission(false);
    Wire.requestFrom(MPU_ADDR, 6, true);
    
    int16_t sAxR = Wire.read() << 8 | Wire.read();
    int16_t sAyR = Wire.read() << 8 | Wire.read();
    int16_t sAzR = Wire.read() << 8 | Wire.read();
    
    float sAx = sAxR / ACC_SENS;
    float sAy = sAyR / ACC_SENS;
    float sAz = sAzR / ACC_SENS;
    
    float din = fabs(sqrt(sAx*sAx + sAy*sAy + sAz*sAz) - 1.0);
    Serial.print("Sondeo - Dinamica: ");
    Serial.println(din, 4);
    return din >= UMBRAL_REPOSO;
}

// Gestion sensor pulso
void encenderPulso() {
    digitalWrite(PULSE_POWER_PIN, HIGH);
    Serial.println("Pulso: Sensor encendido");
}

void apagarPulso() {
    digitalWrite(PULSE_POWER_PIN, LOW);
    Serial.println("Pulso: Sensor apagado");
}

void resetearBufferPulso() {
    for (int i = 0; i < PICOS_BUFFER; i++) intervalosPicos[i] = 0;
    indicePico = 0;
    picosValidos = 0;
    encimaPico = false;
    umbralPulso = 2048;
    minSignal = 4095;
    maxSignal = 0;
    tiempoUltimoPico = 0;
}

// MPU6050 - Procesamiento datos
void leerMPU() {
    Wire.beginTransmission(MPU_ADDR);
    Wire.write(0x3B);
    Wire.endTransmission(false);
    Wire.requestFrom(MPU_ADDR, 6, true);
    axR = Wire.read() << 8 | Wire.read();
    ayR = Wire.read() << 8 | Wire.read();
    azR = Wire.read() << 8 | Wire.read();
    ax = axR / ACC_SENS;
    ay = ayR / ACC_SENS;
    az = azR / ACC_SENS;
}

float calcularMagnitud() {
    return sqrt(ax*ax + ay*ay + az*az);
}

float aplicarPromedio(float nueva) {
    sumMag -= bufferMag[bufferIdx];
    bufferMag[bufferIdx] = nueva;
    sumMag += nueva;
    bufferIdx = (bufferIdx + 1) % WINDOW_SIZE;
    return sumMag / WINDOW_SIZE;
}

String clasificar(float din) {
    if (din < UMBRAL_REPOSO) return "REPOSO";
    if (din < UMBRAL_MODERADO) return "ACTIVIDAD MODERADA";
    return "ALTA ACTIVIDAD";
}

String evaluarTemperatura(float t) {
    if (t == DEVICE_DISCONNECTED_C) return "SIN SENSOR";
    if (t < TEMP_HIPOTERMIA) return "HIPOTERMIA - URGENTE";
    if (t < TEMP_NORMAL_MIN) return "TEMPERATURA BAJA";
    if (t <= TEMP_NORMAL_MAX) return "TEMPERATURA NORMAL";
    if (t <= TEMP_FIEBRE) return "TEMPERATURA ALTA";
    if (t <= TEMP_FIEBRE_ALTA) return "FIEBRE - VET URGENTE";
    return "FIEBRE PELIGROSA";
}

// Detector pulso cardiaco
void muestrearPulso() {
    if (millis() - ultimoMuestreoPulso < PULSE_SAMPLE_MS) return;
    ultimoMuestreoPulso = millis();

    int valor = analogRead(PULSE_PIN);

    if (valor < minSignal) minSignal = valor;
    if (valor > maxSignal) maxSignal = valor;

    int amplitud = maxSignal - minSignal;
    if (amplitud > UMBRAL_AMPLITUD_MIN) {
        umbralPulso = minSignal + (amplitud / 2) + (amplitud / 10);
    }
    minSignal += 1;
    maxSignal -= 1;

    unsigned long ahora = millis();
    if (!encimaPico && valor > umbralPulso) {
        encimaPico = true;
        unsigned long intervalo = ahora - tiempoUltimoPico;
        if (tiempoUltimoPico > 0 && intervalo > 300 && intervalo < 2000) {
            intervalosPicos[indicePico % PICOS_BUFFER] = intervalo;
            indicePico++;
            if (picosValidos < PICOS_BUFFER) picosValidos++;
        }
        tiempoUltimoPico = ahora;
    }
    if (encimaPico && valor < umbralPulso) encimaPico = false;
}

int calcularBPM() {
    if (picosValidos < 2) return 0;
    if (millis() - tiempoUltimoPico > BPM_TIMEOUT_MS) {
        picosValidos = 0;
        return 0;
    }
    unsigned long suma = 0;
    int n = min(picosValidos, PICOS_BUFFER);
    for (int i = 0; i < n; i++) suma += intervalosPicos[i];
    return (int)(60000.0 / ((float)suma / n));
}

String evaluarPulso(int bpm, const String& actividad) {
    if (bpm <= 0) return "SIN LECTURA";
    if (bpm < BPM_MUY_BAJO) return "BRADICARDIA - VET";
    if (bpm < BPM_REPOSO_NORMAL_MIN) return "PULSO BAJO";
    if (bpm <= BPM_REPOSO_NORMAL_MAX) return "PULSO NORMAL";
    if (bpm <= BPM_ACTIVO_MAX) return "PULSO ELEVADO";
    if (bpm <= BPM_MUY_ALTO) return "PULSO ALTO";
    return "TAQUICARDIA - VET";
}

String evaluarEstres(int bpm, const String& actividad, float tempC) {
    if (bpm <= 0) return "SIN DATOS PULSO";
    if (tempC >= TEMP_FIEBRE && bpm > BPM_ACTIVO_MAX)
        return "ALERTA MEDICA: FIEBRE + TAQUICARDIA";
        
    if (actividad == "REPOSO") {
        if (bpm > BPM_MUY_ALTO) return "ESTRES SEVERO - VET";
        if (bpm > BPM_ALTO_ESTRES) return "ESTRES ALTO";
        if (bpm > BPM_REPOSO_NORMAL_MAX) return "ESTRES LEVE";
        if (bpm >= BPM_REPOSO_NORMAL_MIN) return "SIN ESTRES";
        return "PULSO BAJO EN REPOSO";
    }
    if (actividad == "ACTIVIDAD MODERADA") {
        if (bpm > BPM_MUY_ALTO) return "ESTRES ALTO - SOBRECARGA";
        if (bpm > BPM_ACTIVO_MAX) return "ESTRES LEVE - PULSO EXCESIVO";
        return "ACTIVIDAD NORMAL";
    }
    if (actividad == "ALTA ACTIVIDAD") {
        if (bpm > BPM_MUY_ALTO) return "ESFUERZO MAXIMO - DESCANSO";
        return "ESFUERZO NORMAL";
    }
    return "SIN CLASIFICAR";
}

// Utilidades BLE
BLECharacteristic* crearCaracteristica(BLEService* svc, const char* uuid, const char* descripcion) {
    BLECharacteristic* pChar = svc->createCharacteristic(
        uuid,
        BLECharacteristic::PROPERTY_READ | BLECharacteristic::PROPERTY_NOTIFY
    );
    pChar->addDescriptor(new BLE2902());
    BLEDescriptor* pDesc = new BLEDescriptor(BLEUUID((uint16_t)0x2901));
    pDesc->setValue(descripcion);
    pChar->addDescriptor(pDesc);
    return pChar;
}
