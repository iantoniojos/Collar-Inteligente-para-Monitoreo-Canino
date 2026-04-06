# Collar-Inteligente-para-Monitoreo-Canino
Collar Inteligente para el Monitoreo del Bienestar Canino
Este repositorio contiene el firmware y la documentación técnica para el Collar CanisHealth, un dispositivo de hardware abierto diseñado para el monitoreo constante de la salud y actividad física en perros. El sistema integra sensores de temperatura, pulso cardíaco y movimiento dentro de un ecosistema IoT
.
📋 Características Principales
Monitoreo Multimodal: Captura de temperatura corporal, ritmo cardíaco y niveles de actividad física
.
Gestión Eficiente de Energía: El firmware reduce la frecuencia de la CPU a 80MHz y utiliza un pin de control (GPIO 25) para apagar el sensor de pulso durante periodos de inactividad
.
Conectividad BLE: Transmisión de datos en tiempo real mediante Bluetooth Low Energy con servicios dedicados para cada métrica de salud
.
Análisis en el Borde: Clasificación automática de estados de estrés y actividad directamente en el microcontrolador
.
🛠️ Requisitos de Hardware
Microcontrolador: ESP32 DevKit V1
.
Sensores:
Inercial: MPU6050 (Acelerómetro y Giroscopio)
.
Temperatura: DS18B20 (Sumergible/Protegido)
.
Pulso: HW-827 (compatible con XD 58C)
.
Base: Collar de cuero sintético para montaje artesanal.
🔌 Diagrama de Conexión (Pinout)
Para garantizar la replicación exacta del dispositivo, siga este mapeo de pines definido en el código
:
Componente
Pin ESP32
Función
DS18B20
GPIO 4
Bus OneWire (Datos)
MPU6050
GPIO 21 (SDA)
Bus I2C
MPU6050
GPIO 22 (SCL)
Bus I2C
HW-827
GPIO 25
VCC (Alimentación Controlada)
HW-827
GPIO 34
Señal Analógica
Nota Crítica: El sensor de pulso debe conectarse al GPIO 25. Esto permite que el sistema corte la energía del LED del sensor para ahorrar batería cuando el perro está en reposo
.
💻 Configuración del Software
Librerías Necesarias
Asegúrese de instalar las siguientes librerías en su IDE de Arduino:
OneWire
DallasTemperature
Wire
BLEDevice, BLEServer, BLEUtils, BLE2902 (incluidas en el paquete de ESP32)
Compilación e Instalación
Renombre el archivo codigo collar.txt a CanisHealth_Firmware.ino.
Configure la placa como ESP32 Dev Module.
Ajuste la CPU Frequency a 80MHz en las herramientas del IDE para optimizar el consumo
.
Cargue el código al ESP32.
📊 Arquitectura del Sistema
El collar es parte de una arquitectura de cuatro capas:
Dispositivos (Edge): El collar y sensores ambientales.
Conectividad: Redes BLE y WiFi
.
Cloud: Base de datos para almacenamiento de patrones de salud
.
Aplicación: Interfaz para dueños y veterinarios con alertas críticas
.
⚖️ Licencia
Este proyecto se distribuye bajo la licencia MIT
