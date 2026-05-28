# 🐾 Collar Inteligente para Monitoreo Canino

> Sistema IoT de monitoreo de salud y actividad física en perros, con análisis en servidor local, geolocalización en tiempo real y panel web multi-rol con inteligencia artificial.

---

## 🌟 Características Principales

- **Gestión Inteligente de Energía:** El firmware reduce la frecuencia de la CPU a **80MHz** y alterna entre modo activo y reposo según el nivel de movimiento detectado por el MPU6050.
- **Control Dinámico de Sensores:** Utiliza el pin **GPIO 25** como fuente conmutada para alimentar el sensor de pulso, eliminando el consumo del LED cuando el sistema está en reposo.
- **Monitoreo Multimodal:** Captura temperatura corporal (DS18B20), ritmo cardíaco (HW-827), niveles de actividad (MPU6050) y ubicación GPS (NEO-6M).
- **Conectividad WiFi:** Transmisión de datos al servidor local mediante HTTP POST. El dispositivo se identifica automáticamente por su **dirección MAC**, asociándose a la mascota registrada en el sistema.
- **Análisis en Servidor:** Todo el procesamiento de clasificación (temperatura, pulso, estrés, actividad) ocurre en el servidor PHP, no en el dispositivo.
- **Inteligencia Artificial local:** Motor de Machine Learning en Python que analiza patrones históricos de temperatura, estrés crónico y hábitos de actividad, con reentrenamiento automático diario.

---

## 🔌 Diagrama de Conexión (Pinout)

| Componente | Pin ESP32 | Función |
| :--- | :--- | :--- |
| **DS18B20** | GPIO 4 | Bus OneWire (Temperatura) |
| **MPU6050** | GPIO 21 (SDA) | Bus I2C (Acelerómetro) |
| **MPU6050** | GPIO 22 (SCL) | Bus I2C (Acelerómetro) |
| **HW-827 (VCC)** | GPIO 25 | Alimentación controlada del sensor de pulso |
| **HW-827 (SIG)** | GPIO 34 | Señal analógica de pulso |
| **NEO-6M (TX)** | GPIO 16 (RX2) | UART2 — datos GPS entrantes |
| **NEO-6M (RX)** | GPIO 17 (TX2) | UART2 — datos GPS salientes |
| **NEO-6M (VCC)** | 3.3V | Alimentación del módulo GPS |
| **NEO-6M (GND)** | GND | Tierra común |

> **IMPORTANTE:** El pin GPIO 25 es fundamental para la gestión de energía. El sistema activa este pin y espera **2000ms** para estabilizar la señal antes de procesar el pulso. No conectar el sensor de pulso directamente a 3.3V fijo.

---

## 🏛️ Arquitectura del Sistema

El sistema está dividido en cuatro capas operativas:

```
┌─────────────────────────────────────────────────┐
│  CAPA DE DISPOSITIVO                            │
│  ESP32 + DS18B20 + MPU6050 + HW-827 + NEO-6M   │
│  Firmware: ENFASIS_WIFI.ino                     │
└────────────────────┬────────────────────────────┘
                     │ HTTP POST (WiFi)
                     │ temp, accel, bpm, modo, mac, lat, lng
┌────────────────────▼────────────────────────────┐
│  CAPA DE SERVIDOR LOCAL (XAMPP)                 │
│  guardar.php  → Recibe datos, analiza y guarda  │
│  leer.php     → Sirve datos al dashboard        │
│  umbrales.php → Umbrales configurables          │
│  ml_api.php   → API del motor de IA             │
│  ml_engine.py → Motor ML (scikit-learn)         │
└────────────────────┬────────────────────────────┘
                     │
┌────────────────────▼────────────────────────────┐
│  CAPA DE BASE DE DATOS (MySQL)                  │
│  sensores_db                                    │
│  ├── usuarios  (dueños y veterinarios)          │
│  ├── mascotas  (vinculadas por MAC del ESP32)   │
│  └── sensores  (lecturas con mascota_id)        │
└────────────────────┬────────────────────────────┘
                     │
┌────────────────────▼────────────────────────────┐
│  CAPA DE APLICACIÓN (Dashboard Web)             │
│  login.php         → Autenticación multi-rol    │
│  index.php         → Panel del dueño            │
│  dashboard_vet.php → Panel del veterinario      │
└─────────────────────────────────────────────────┘
```

---

## 💻 Configuración del Firmware

### Librerías requeridas (Arduino IDE)

| Librería | Fuente |
| :--- | :--- |
| `OneWire` | Gestor de librerías Arduino |
| `DallasTemperature` | Gestor de librerías Arduino |
| `Wire` (I2C) | Incluida en el núcleo ESP32 |
| `WiFi` | Incluida en el núcleo ESP32 |
| `HTTPClient` | Incluida en el núcleo ESP32 |
| `TinyGPSPlus` | Mikal Hart — Gestor de librerías Arduino |

### Parámetros de compilación

1. Instalar soporte para placas ESP32 en Arduino IDE.
2. Seleccionar placa: **ESP32 Dev Module**.
3. Frecuencia de CPU: **80MHz** (obligatorio).

### Variables a configurar antes de subir

```cpp
const char* WIFI_SSID  = "TU_RED_WIFI";
const char* WIFI_PASS  = "TU_CONTRASENA";
const char* SERVER_URL = "http://TU_IP_LOCAL/collarperro/guardar.php";
```

> La IP local se obtiene ejecutando `ipconfig` en CMD y buscando la **Dirección IPv4** del adaptador WiFi.

---

## 🖥️ Configuración del Servidor

### Requisitos

- **XAMPP** (Apache + MySQL + PHP 8.x)
- **Python 3.x** con las librerías: `pymysql`, `scikit-learn`, `pandas`, `numpy`

```bash
pip install pymysql scikit-learn pandas numpy
```

### Estructura de archivos

```
C:\xampp\htdocs\collarperro\
├── index.php              # Dashboard del dueño
├── login.php              # Inicio de sesión
├── logout.php             # Cierre de sesión
├── auth.php               # Gestión de sesiones PHP
├── guardar.php            # Recibe datos del ESP32
├── leer.php               # Sirve datos al dashboard
├── umbrales.php           # API de umbrales configurables
├── api_mascotas.php       # CRUD de mascotas
├── dashboard_vet.php      # Dashboard del veterinario
├── ml_engine.py           # Motor de Machine Learning
├── ml_api.php             # API del motor ML
├── ml_entrenar.php        # Reentrenamiento diario
└── ml_models/             # Modelos entrenados (auto-generado)
```

### Instalación de la base de datos

1. Abrir `http://localhost/phpmyadmin`
2. Crear base de datos: `sensores_db`
3. Ejecutar `setup_db.sql` en la pestaña SQL
4. Ejecutar `agregar_gps.sql` para agregar columnas de geolocalización

### Reentrenamiento automático diario

Configurar en el **Programador de tareas de Windows** una tarea que se ejecute cada día a las 3:00 AM:

- **Programa:** `C:\xampp\php\php.exe`
- **Argumentos:** `C:\xampp\htdocs\collarperro\ml_entrenar.php`

O disparar manualmente desde el navegador:
```
http://localhost/collarperro/ml_entrenar.php?clave=collar2025ml
```

---

## 👥 Roles de Usuario

### Dueño de mascota
- Registra su cuenta y agrega mascotas con el nombre, raza, edad, MAC del ESP32 y correo del veterinario asignado.
- Visualiza en tiempo real: temperatura (gauge circular con color dinámico), nivel de estrés, actividad y última lectura.
- Accede al historial reciente, mapa GPS en tiempo real y análisis de patrones con IA.
- Configura umbrales de temperatura, pulso y estrés desde el dashboard.

### Veterinario
- Accede al panel clínico con historial completo paginado (hasta 200 registros).
- Filtra registros por estado clínico (fiebre, hipotermia, taquicardia, etc.) y por fecha.
- Recibe alertas automáticas si la mascota presenta fiebre, taquicardia o estrés severo.
- Visualiza análisis clínico detallado: pendiente de tendencia térmica, desviación estándar, proyección, lecturas atípicas y distribución de estrés.
- Puede reentrenar los modelos ML manualmente desde el panel.

---

## 🧠 Motor de Inteligencia Artificial

El sistema incluye un motor de ML local (`ml_engine.py`) basado en `scikit-learn` que analiza el historial acumulado de cada mascota individualmente. No requiere conexión a internet.

### Modelos implementados

| Modelo | Técnica | Qué detecta |
| :--- | :--- | :--- |
| **Tendencia térmica** | Regresión lineal | Tendencia sostenida al alza o baja en temperatura, proyección futura y lecturas atípicas |
| **Estrés crónico** | Clasificación por frecuencia | Porcentaje histórico de episodios de estrés leve, alto y severo; tendencia reciente |
| **Hábitos de actividad** | Detección de anomalías | Cambios significativos en la proporción de reposo, actividad moderada y alta actividad |

### Requisitos mínimos para análisis

- **20 registros** para ejecutar el análisis
- **50 registros** para entrenar/reentrenar los modelos

---

## 🗺️ Geolocalización GPS

El módulo **NEO-6M** envía coordenadas en tiempo real al servidor. En el dashboard del dueño, el botón **"Mapa GPS"** abre un mapa interactivo (OpenStreetMap + Leaflet) que muestra la posición actual del collar y se actualiza automáticamente cada 3 segundos. No requiere API key.

> El módulo GPS puede tardar entre 1 y 5 minutos en obtener el primer fix, especialmente en interiores.

---

## 📋 Umbrales Configurables

Los umbrales de clasificación son ajustables desde el dashboard sin necesidad de modificar el código. Se almacenan en `umbrales.json` en el servidor.

| Parámetro | Valor por defecto |
| :--- | :--- |
| Hipotermia | < 36.0°C |
| Temperatura baja | 36.0 – 37.5°C |
| Temperatura normal | 37.5 – 39.2°C |
| Temperatura alta | 39.2 – 39.5°C |
| Fiebre | 39.5 – 40.5°C |
| Fiebre peligrosa | > 40.5°C |
| Bradicardia | < 50 BPM |
| Pulso normal | 60 – 100 BPM |
| Taquicardia | > 160 BPM |

---

## 📄 Licencia

Este proyecto es **Hardware y Software de Código Abierto** y se distribuye bajo la licencia **MIT**.
