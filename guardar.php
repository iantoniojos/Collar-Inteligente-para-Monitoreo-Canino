<?php
// ============================================================
//  guardar.php — Análisis con umbrales dinámicos desde config
// ============================================================

header("Content-Type: application/json; charset=utf-8");
header("Access-Control-Allow-Origin: *");

define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'sensores_db');
define('CONFIG_FILE', __DIR__ . '/umbrales.json');

$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
if ($conn->connect_error) {
    http_response_code(500);
    echo json_encode(["ok" => false, "error" => $conn->connect_error]);
    exit;
}
$conn->set_charset("utf8mb4");

// ── Cargar umbrales ──────────────────────────────────────────
$defaults = [
    "temp_hipotermia"  => 36.0,  "temp_baja"        => 37.5,
    "temp_normal_max"  => 39.2,  "temp_alta"        => 39.5,
    "temp_fiebre"      => 40.5,
    "bpm_bradicardia"  => 50,    "bpm_bajo"         => 60,
    "bpm_normal_max"   => 100,   "bpm_elevado"      => 140,  "bpm_alto" => 160,
    "bpm_estres_leve_reposo"   => 100, "bpm_estres_alto_reposo"   => 120,
    "bpm_estres_severo_reposo" => 160,
    "bpm_estres_leve_moderado" => 140, "bpm_estres_alto_moderado" => 160,
    "bpm_estres_alto_activo"   => 160,
    "accel_reposo"  => 0.05,     "accel_moderado"   => 0.20
];

$cfg = $defaults;
if (file_exists(CONFIG_FILE)) {
    $json = json_decode(file_get_contents(CONFIG_FILE), true);
    if ($json) $cfg = array_merge($defaults, $json);
}

// ── Parámetros crudos del ESP32 ──────────────────────────────
function getParam($k, $d='') {
    $v = isset($_POST[$k]) ? $_POST[$k] : (isset($_GET[$k]) ? $_GET[$k] : $d);
    return trim(strip_tags((string)$v));
}

$temperatura = (float) getParam('temp',  0);
$accel       = (float) getParam('accel', 0);
$bpm         = (int)   getParam('bpm',   0);
$modo        =         getParam('modo',  'N/A');
$lat         = (float) getParam('lat', 0);
$lng         = (float) getParam('lng', 0);
$mac         =         getParam('mac',   '');

// ── Buscar mascota por MAC del ESP32 ─────────────────────────
$mascota_id = 1; // fallback por defecto
if ($mac) {
    $sm = $conn->prepare("SELECT id FROM mascotas WHERE mac_esp32 = ? LIMIT 1");
    $sm->bind_param("s", $mac);
    $sm->execute();
    $mr = $sm->get_result()->fetch_assoc();
    if ($mr) $mascota_id = (int)$mr['id'];
    $sm->close();
}

// ════════════════════════════════════════════════════════════
//  ANÁLISIS CON UMBRALES DINÁMICOS
// ════════════════════════════════════════════════════════════

function clasificarActividad($accel, $cfg) {
    if ($accel < $cfg['accel_reposo'])   return "REPOSO";
    if ($accel < $cfg['accel_moderado']) return "ACTIVIDAD MODERADA";
    return "ALTA ACTIVIDAD";
}

function evaluarTemperatura($t, $cfg) {
    if ($t <= -127) return "SIN SENSOR";
    if ($t < $cfg['temp_hipotermia'])  return "HIPOTERMIA - URGENTE";
    if ($t < $cfg['temp_baja'])        return "TEMPERATURA BAJA";
    if ($t <= $cfg['temp_normal_max']) return "TEMPERATURA NORMAL";
    if ($t <= $cfg['temp_alta'])       return "TEMPERATURA ALTA";
    if ($t <= $cfg['temp_fiebre'])     return "FIEBRE - VET URGENTE";
    return "FIEBRE PELIGROSA";
}

function evaluarPulso($bpm, $cfg) {
    if ($bpm <= 0)                      return "SIN LECTURA";
    if ($bpm < $cfg['bpm_bradicardia']) return "BRADICARDIA - VET";
    if ($bpm < $cfg['bpm_bajo'])        return "PULSO BAJO";
    if ($bpm <= $cfg['bpm_normal_max']) return "PULSO NORMAL";
    if ($bpm <= $cfg['bpm_elevado'])    return "PULSO ELEVADO";
    if ($bpm <= $cfg['bpm_alto'])       return "PULSO ALTO";
    return "TAQUICARDIA - VET";
}

function evaluarEstres($bpm, $actividad, $tempC, $cfg) {
    if ($bpm <= 0) return "SIN DATOS PULSO";
    if ($tempC >= $cfg['temp_fiebre'] && $bpm > $cfg['bpm_elevado'])
        return "ALERTA MEDICA: FIEBRE + TAQUICARDIA";

    if ($actividad === "REPOSO") {
        if ($bpm > $cfg['bpm_estres_severo_reposo']) return "ESTRES SEVERO - VET";
        if ($bpm > $cfg['bpm_estres_alto_reposo'])   return "ESTRES ALTO";
        if ($bpm > $cfg['bpm_estres_leve_reposo'])   return "ESTRES LEVE";
        if ($bpm >= $cfg['bpm_bajo'])                return "SIN ESTRES";
        return "PULSO BAJO EN REPOSO";
    }
    if ($actividad === "ACTIVIDAD MODERADA") {
        if ($bpm > $cfg['bpm_estres_alto_moderado'])  return "ESTRES ALTO - SOBRECARGA";
        if ($bpm > $cfg['bpm_estres_leve_moderado'])  return "ESTRES LEVE - PULSO EXCESIVO";
        return "ACTIVIDAD NORMAL";
    }
    if ($actividad === "ALTA ACTIVIDAD") {
        if ($bpm > $cfg['bpm_estres_alto_activo']) return "ESFUERZO MAXIMO - DESCANSO";
        return "ESFUERZO NORMAL";
    }
    return "SIN CLASIFICAR";
}

$actividad    = clasificarActividad($accel, $cfg);
$estado_temp  = evaluarTemperatura($temperatura, $cfg);
$estado_pulso = evaluarPulso($bpm, $cfg);
$estres       = evaluarEstres($bpm, $actividad, $temperatura, $cfg);

$stmt = $conn->prepare(
    "INSERT INTO sensores (temperatura, actividad, bpm, estado_pulso, estado_temp, estres, modo, mascota_id, lat, lng)
     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
);
$stmt->bind_param("dssisssidd", $temperatura, $actividad, $bpm, $estado_pulso, $estado_temp, $estres, $modo, $mascota_id, $lat, $lng);

if ($stmt->execute()) {
    echo json_encode(["ok" => true, "id" => $stmt->insert_id,
        "actividad" => $actividad, "estado_temp" => $estado_temp,
        "estado_pulso" => $estado_pulso, "estres" => $estres]);
} else {
    http_response_code(500);
    echo json_encode(["ok" => false, "error" => $stmt->error]);
}

$stmt->close();
$conn->close();
