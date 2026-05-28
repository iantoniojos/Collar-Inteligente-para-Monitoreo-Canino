<?php
// ================================================================
//  ml_api.php  —  Endpoint que ejecuta ml_engine.py y devuelve JSON
// ================================================================
require_once 'auth.php';
startSession();
header("Content-Type: application/json; charset=utf-8");

if (empty($_SESSION['usuario_id'])) {
    http_response_code(401);
    echo json_encode(["ok"=>false,"error"=>"No autenticado"]); exit;
}

$uid = (int)$_SESSION['usuario_id'];
$rol = $_SESSION['rol'];
$mid = (int)($_GET['mascota_id'] ?? 0);

if (!$mid) {
    echo json_encode(["ok"=>false,"error"=>"mascota_id requerido"]); exit;
}

// Verificar que la mascota pertenece al usuario
$db = getDB();
if ($rol === 'veterinario') {
    $chk = $db->prepare("SELECT id FROM mascotas WHERE id=? AND vet_id=?");
} else {
    $chk = $db->prepare("SELECT id FROM mascotas WHERE id=? AND dueno_id=?");
}
$chk->bind_param("ii", $mid, $uid);
$chk->execute();
if (!$chk->get_result()->fetch_assoc()) {
    echo json_encode(["ok"=>false,"error"=>"Mascota no autorizada"]); exit;
}
$db->close();

// ── Ejecutar ml_engine.py ─────────────────────────────────────
$python     = 'python';          // En Windows XAMPP usar 'python' o ruta completa
$script     = __DIR__ . '/ml_engine.py';
$accion     = escapeshellarg('analizar');
$mascota    = escapeshellarg((string)$mid);
$cmd        = "$python $script $accion $mascota 2>&1";

$output = shell_exec($cmd);

if (!$output) {
    echo json_encode(["ok"=>false,"error"=>"El motor ML no devolvió respuesta. Verifica que Python esté instalado y en el PATH."]); exit;
}

// Extraer solo el JSON (ignorar warnings de Python)
$lines = explode("\n", trim($output));
$json_line = '';
foreach (array_reverse($lines) as $line) {
    $line = trim($line);
    if (str_starts_with($line, '{')) { $json_line = $line; break; }
}

if (!$json_line) {
    echo json_encode(["ok"=>false,"error"=>"Respuesta inválida del motor ML","raw"=>$output]); exit;
}

// Reenviar directamente al cliente
echo $json_line;
