<?php
// ============================================================
//  api_mascotas.php — CRUD mascotas del usuario en sesión
// ============================================================
require_once 'auth.php';
startSession();
header("Content-Type: application/json; charset=utf-8");

if (empty($_SESSION['usuario_id'])) {
    http_response_code(401);
    echo json_encode(["ok"=>false,"error"=>"No autenticado"]); exit;
}

$uid    = (int)$_SESSION['usuario_id'];
$rol    = $_SESSION['rol'];
$method = $_SERVER['REQUEST_METHOD'];
$db     = getDB();

// ── GET: listar mascotas ──────────────────────────────────────
if ($method === 'GET') {
    if ($rol === 'veterinario') {
        $stmt = $db->prepare(
            "SELECT m.id, m.nombre, m.raza, m.edad, m.mac_esp32, u.nombre AS dueno_nombre
             FROM mascotas m JOIN usuarios u ON u.id = m.dueno_id
             WHERE m.vet_id = ? ORDER BY m.id DESC"
        );
        $stmt->bind_param("i", $uid);
    } else {
        $stmt = $db->prepare(
            "SELECT m.id, m.nombre, m.raza, m.edad, m.mac_esp32, u.nombre AS vet_nombre
             FROM mascotas m LEFT JOIN usuarios u ON u.id = m.vet_id
             WHERE m.dueno_id = ? ORDER BY m.id DESC"
        );
        $stmt->bind_param("i", $uid);
    }
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    echo json_encode(["ok"=>true,"mascotas"=>$rows]); exit;
}

// ── POST: crear mascota ───────────────────────────────────────
if ($method === 'POST' && $rol === 'dueno') {
    $body   = json_decode(file_get_contents('php://input'), true) ?: [];
    $nombre = trim($body['nombre'] ?? '');
    $raza   = trim($body['raza']   ?? '');
    $edad   = (int)($body['edad']  ?? 0);
    $mac    = strtoupper(trim($body['mac_esp32'] ?? ''));
    $vet_email = trim($body['vet_email'] ?? '');

    if (!$nombre) { echo json_encode(["ok"=>false,"error"=>"Nombre requerido"]); exit; }

    $vet_id = null;
    if ($vet_email) {
        $sv = $db->prepare("SELECT id FROM usuarios WHERE email=? AND rol='veterinario'");
        $sv->bind_param("s", $vet_email);
        $sv->execute();
        $vr = $sv->get_result()->fetch_assoc();
        if (!$vr) { echo json_encode(["ok"=>false,"error"=>"Veterinario no encontrado con ese correo"]); exit; }
        $vet_id = $vr['id'];
        $sv->close();
    }

    $stmt = $db->prepare("INSERT INTO mascotas (nombre,raza,edad,dueno_id,vet_id,mac_esp32) VALUES (?,?,?,?,?,?)");
    $stmt->bind_param("ssiiss", $nombre, $raza, $edad, $uid, $vet_id, $mac);
    if ($stmt->execute()) {
        echo json_encode(["ok"=>true,"id"=>$db->insert_id]);
    } else {
        echo json_encode(["ok"=>false,"error"=>$stmt->error]);
    }
    exit;
}

// ── DELETE: eliminar mascota ──────────────────────────────────
if ($method === 'DELETE' && $rol === 'dueno') {
    $body = json_decode(file_get_contents('php://input'), true) ?: [];
    $mid  = (int)($body['id'] ?? 0);
    $stmt = $db->prepare("DELETE FROM mascotas WHERE id=? AND dueno_id=?");
    $stmt->bind_param("ii", $mid, $uid);
    echo json_encode(["ok"=>$stmt->execute()]);
    exit;
}

http_response_code(405);
echo json_encode(["ok"=>false,"error"=>"Método no permitido"]);
$db->close();
