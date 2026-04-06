# Collar-Inteligente-para-Monitoreo-Canino
Este repositorio contiene el firmware y la documentación técnica del **Collar CanisHealth**, un dispositivo de hardware abierto diseñado para el seguimiento no invasivo de la salud y actividad física en perros. El sistema destaca por su integración en una arquitectura de múltiples capas que conecta el hardware con servicios en la nube y aplicaciones móviles [2, 3].

## 🌟 Características Principales
*   **Gestión Inteligente de Energía:** El firmware reduce la frecuencia de la CPU a **80MHz** para optimizar la autonomía [1].
*   **Control Dinámico de Sensores:** Utiliza el pin **GPIO 25** como fuente conmutada para alimentar el sensor de pulso, eliminando el consumo del LED cuando el sistema está en reposo [4, 5].
*   **Monitoreo Multimodal:** Captura temperatura corporal (DS18B20), ritmo cardíaco (HW-827) y niveles de actividad (MPU6050) [4].
*   **Conectividad BLE:** Transmisión de datos mediante Bluetooth Low Energy bajo el nombre "CollarPerro" [6].

## 🔌 Diagrama de Conexión (Pinout)
Para replicar el hardware, realice las siguientes conexiones en el **ESP32 DevKit**:

| Componente | Pin ESP32 | Función |
| :--- | :--- | :--- |
| **DS18B20** | GPIO 4 | Bus OneWire (Temperatura) |
| **MPU6050** | GPIO 21 (SDA) | Bus I2C (Movimiento) |
| **MPU6050** | GPIO 22 (SCL) | Bus I2C (Movimiento) |
| **HW-827 (VCC)** | **GPIO 25** | **Alimentación Controlada** (No conectar a 3.3V) |
| **HW-827 (SIG)** | GPIO 34 | Señal Analógica de Pulso |

> **IMPORTANTE:** El pin 25 es fundamental para la gestión de energía definida en el código. El sistema activa este pin y espera **2000ms** para estabilizar la lectura antes de procesar los datos [1, 7].

## 💻 Configuración del Software
### Librerías Requeridas
*   `OneWire` e `DallasTemperature` [4].
*   `Wire` (I2C) y librerías estándar de `BLE` para ESP32 [4].

### Parámetros de Compilación
1. Instalar el soporte para placas ESP32 en el IDE de Arduino.
2. Seleccionar la placa **ESP32 Dev Module**.
3. **Frecuencia de CPU:** Ajustar obligatoriamente a **80MHz** para asegurar el correcto funcionamiento de los umbrales de energía [1].

## 🏛️ Arquitectura del Proyecto
El hardware forma parte de un ecosistema integral dividido en cuatro niveles operativos [2]:
1.  **Capa de Dispositivos:** El collar inteligente y estaciones de alimentación IoT.
2.  **Capa de Conectividad:** Redes WiFi y Bluetooth para el transporte de datos.
3.  **Capa Cloud:** Centro de datos con bases de datos para usuarios y sensores.
4.  **Capa de Aplicación:** Plataforma de gestión y aplicación móvil para alertas de salud (fiebre, estrés, actividad).

## 📄 Licencia
Este proyecto es **Hardware de Código Abierto** y se distribuye bajo la licencia **MIT** (o la licencia que hayas seleccionado en tu informe) [8].

---
*Este proyecto sigue los lineamientos de publicación de la revista científica **HardwareX**.*
