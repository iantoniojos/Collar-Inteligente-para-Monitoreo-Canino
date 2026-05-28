<?php
// ================================================================
//  ml_entrenar.php  —  Reentrenamiento diario de modelos ML
//  Configurar en el Programador de tareas de Windows para ejecutar
//  cada día a las 3:00 AM:
//
//  Acción: C:\xampp\php\php.exe
//  Argumentos: C:\xampp\htdocs\collarperro\ml_entrenar.php
// ================================================================

// Permitir ejecución solo desde CLI o con clave secreta
$es_cli  = php_sapi_name() === 'cli';
$clave   = $_GET['clave'] ?? '';
$clave_ok = $clave === 'collar2025ml';   // cambia esta clave

if (!$es_cli && !$clave_ok) {
    http_response_code(403);
    echo json_encode(["ok"=>false,"error"=>"Acceso no autorizado"]);
    exit;
}

header("Content-Type: application/json; charset=utf-8");

define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'sensores_db');

$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
$conn->set_charset("utf8mb4");

// Obtener todas las mascotas con datos suficientes
$res  = $conn->query("SELECT DISTINCT mascota_id FROM sensores WHERE mascota_id IS NOT NULL GROUP BY mascota_id HAVING COUNT(*) >= 50");
$ids  = [];
while ($r = $res->fetch_assoc()) $ids[] = $r['mascota_id'];
$conn->close();

if (empty($ids)) {
    echo json_encode(["ok"=>true,"mensaje"=>"No hay mascotas con datos suficientes aún."]);
    exit;
}

$python  = 'python';
$script  = __DIR__ . '/ml_engine.py';
$cmd     = "$python $script entrenar all 2>&1";
$output  = shell_exec($cmd);

// Log de entrenamiento
$log_dir  = __DIR__ . '/ml_models';
if (!is_dir($log_dir)) mkdir($log_dir, 0755, true);
$log_file = $log_dir . '/entrenamiento.log';
file_put_contents($log_file,
    date('Y-m-d H:i:s') . " | Mascotas: " . implode(',', $ids) . " | " . trim($output) . "\n",
    FILE_APPEND
);

// Respuesta
$lines = explode("\n", trim($output));
$json_line = '';
foreach (array_reverse($lines) as $line) {
    $line = trim($line);
    if (str_starts_with($line, '{')) { $json_line = $line; break; }
}

echo $json_line ?: json_encode(["ok"=>true,"log"=>$output]);
