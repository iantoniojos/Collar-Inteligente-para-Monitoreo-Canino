-- ============================================================
--  setup_db.sql
--  Ejecutar en phpMyAdmin sobre la base de datos sensores_db
--  Orden: primero este archivo, luego agregar_gps.sql
-- ============================================================

CREATE DATABASE IF NOT EXISTS sensores_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE sensores_db;

-- Tabla de usuarios (duenos y veterinarios)
CREATE TABLE IF NOT EXISTS usuarios (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    nombre     VARCHAR(100) NOT NULL,
    email      VARCHAR(150) NOT NULL UNIQUE,
    password   VARCHAR(255) NOT NULL,
    rol        ENUM('dueno','veterinario') NOT NULL DEFAULT 'dueno',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- Tabla de mascotas
CREATE TABLE IF NOT EXISTS mascotas (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    nombre     VARCHAR(100) NOT NULL,
    raza       VARCHAR(100),
    edad       INT,
    mac_esp32  VARCHAR(20),
    dueno_id   INT NOT NULL,
    vet_id     INT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (dueno_id) REFERENCES usuarios(id),
    FOREIGN KEY (vet_id)   REFERENCES usuarios(id)
);

-- Tabla de sensores (lecturas del collar)
CREATE TABLE IF NOT EXISTS sensores (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    temperatura  FLOAT,
    actividad    VARCHAR(50),
    bpm          INT,
    estado_pulso VARCHAR(100),
    estado_temp  VARCHAR(100),
    estres       VARCHAR(100),
    modo         VARCHAR(20),
    mascota_id   INT DEFAULT 1,
    lat          DOUBLE DEFAULT 0,
    lng          DOUBLE DEFAULT 0,
    fecha_hora   DATETIME DEFAULT CURRENT_TIMESTAMP
);
