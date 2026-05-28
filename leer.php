<?php
// ============================================================
//  leer.php — filtra datos por mascota_id validando que
//  pertenezca al usuario en sesión (dueño o veterinario)
// ============================================================
require_once 'auth.php';
startSession();

header("Content-Type: application/json; charset=utf-8");
header("Access-Control-Allow-Origin: *");
header("Cache-Control: no-cache, no-store, must-revalidate");

if (empty($_SESSION['usuario_id'])) {
    echo json_encode(["ok"=>false,"error"=>"No autenticado"]); exit;
}

$uid = (int)$_SESSION['usuario_id'];
$rol = $_SESSION['rol'];
$mid = (int)($_GET['mascota_id'] ?? 0);
$limite = min((int)($_GET['limite'] ?? 50), 200);

$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
if ($conn->connect_error) { echo json_encode(["ok"=>false,"error"=>$conn->connect_error]); exit; }
$conn->set_charset("utf8mb4");

// Verificar que la mascota pertenece al usuario
if ($mid > 0) {
    if ($rol === 'veterinario') {
        $chk = $conn->prepare("SELECT id FROM mascotas WHERE id=? AND vet_id=?");
    } else {
        $chk = $conn->prepare("SELECT id FROM mascotas WHERE id=? AND dueno_id=?");
    }
    $chk->bind_param("ii", $mid, $uid);
    $chk->execute();
    if (!$chk->get_result()->fetch_assoc()) {
        echo json_encode(["ok"=>false,"error"=>"Mascota no encontrada"]); exit;
    }
    $chk->close();
} else {
    // Si no viene mascota_id, buscar la primera mascota del usuario
    if ($rol === 'veterinario') {
        $fm = $conn->prepare("SELECT id FROM mascotas WHERE vet_id=? ORDER BY id ASC LIMIT 1");
    } else {
        $fm = $conn->prepare("SELECT id FROM mascotas WHERE dueno_id=? ORDER BY id ASC LIMIT 1");
    }
    $fm->bind_param("i", $uid);
    $fm->execute();
    $fmr = $fm->get_result()->fetch_assoc();
    $fm->close();
    if (!$fmr) {
        echo json_encode(["ok"=>true,"latest"=>null,"history"=>[]]); exit;
    }
    $mid = (int)$fmr['id'];
}

// Último registro
$latest = null;
$res = $conn->prepare(
    "SELECT id, temperatura, actividad, bpm, estado_pulso, estado_temp, estres, modo, lat, lng,
            DATE_FORMAT(fecha_hora, '%d/%m/%Y %H:%i:%s') AS fecha_hora
     FROM sensores WHERE mascota_id=? ORDER BY id DESC LIMIT 1"
);
$res->bind_param("i", $mid);
$res->execute();
$latest = $res->get_result()->fetch_assoc();
$res->close();

// Historial
$history = [];
$res2 = $conn->prepare(
    "SELECT temperatura, bpm, actividad, estado_pulso, estado_temp, estres, modo,
            DATE_FORMAT(fecha_hora, '%H:%i:%s') AS hora,
            DATE_FORMAT(fecha_hora, '%Y-%m-%d %H:%i:%s') AS fecha_hora
     FROM sensores WHERE mascota_id=? ORDER BY id DESC LIMIT ?"
);
$res2->bind_param("ii", $mid, $limite);
$res2->execute();
$rows = $res2->get_result()->fetch_all(MYSQLI_ASSOC);
$history = array_reverse($rows);
$res2->close();
$conn->close();

echo json_encode(["ok"=>true,"latest"=>$latest,"history"=>$history,"mascota_id"=>$mid], JSON_UNESCAPED_UNICODE);
