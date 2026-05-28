<?php
// ============================================================
//  umbrales.php  —  GET devuelve config actual | POST guarda
// ============================================================
header("Content-Type: application/json; charset=utf-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST");

define('CONFIG_FILE', __DIR__ . '/umbrales.json');

$defaults = [
    // Temperatura (°C)
    "temp_hipotermia"  => 36.0,
    "temp_baja"        => 37.5,
    "temp_normal_max"  => 39.2,
    "temp_alta"        => 39.5,
    "temp_fiebre"      => 40.5,
    // Pulso (BPM)
    "bpm_bradicardia"  => 50,
    "bpm_bajo"         => 60,
    "bpm_normal_max"   => 100,
    "bpm_elevado"      => 140,
    "bpm_alto"         => 160,
    // Estrés BPM por contexto
    "bpm_estres_leve_reposo"   => 100,
    "bpm_estres_alto_reposo"   => 120,
    "bpm_estres_severo_reposo" => 160,
    "bpm_estres_leve_moderado" => 140,
    "bpm_estres_alto_moderado" => 160,
    "bpm_estres_alto_activo"   => 160,
    // Aceleración (g)
    "accel_reposo"   => 0.05,
    "accel_moderado" => 0.20
];

// ── GET: devolver config actual ───────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $cfg = $defaults;
    if (file_exists(CONFIG_FILE)) {
        $json = json_decode(file_get_contents(CONFIG_FILE), true);
        if ($json) $cfg = array_merge($defaults, $json);
    }
    echo json_encode(["ok" => true, "config" => $cfg]);
    exit;
}

// ── POST: guardar nueva config ────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $body = json_decode(file_get_contents('php://input'), true);
    if (!$body) {
        http_response_code(400);
        echo json_encode(["ok" => false, "error" => "JSON inválido"]);
        exit;
    }

    // Validar y castear tipos
    $cfg = [];
    $floats = ['temp_hipotermia','temp_baja','temp_normal_max','temp_alta','temp_fiebre','accel_reposo','accel_moderado'];
    $ints   = ['bpm_bradicardia','bpm_bajo','bpm_normal_max','bpm_elevado','bpm_alto',
               'bpm_estres_leve_reposo','bpm_estres_alto_reposo','bpm_estres_severo_reposo',
               'bpm_estres_leve_moderado','bpm_estres_alto_moderado','bpm_estres_alto_activo'];

    foreach ($floats as $k) {
        $cfg[$k] = isset($body[$k]) ? (float)$body[$k] : $defaults[$k];
    }
    foreach ($ints as $k) {
        $cfg[$k] = isset($body[$k]) ? (int)$body[$k] : $defaults[$k];
    }

    if (file_put_contents(CONFIG_FILE, json_encode($cfg, JSON_PRETTY_PRINT)) !== false) {
        echo json_encode(["ok" => true, "config" => $cfg]);
    } else {
        http_response_code(500);
        echo json_encode(["ok" => false, "error" => "No se pudo guardar el archivo. Verifica permisos en la carpeta."]);
    }
    exit;
}

http_response_code(405);
echo json_encode(["ok" => false, "error" => "Método no permitido"]);
