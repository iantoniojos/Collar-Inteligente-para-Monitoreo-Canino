<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>CollarPerro — Iniciar sesión</title>
<style>
  *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
  :root {
    --bg:#0f1117; --card:#1a1d27; --border:#2e3354;
    --text:#e8eaf0; --muted:#7a80a0;
    --accent1:#6c63ff; --accent2:#00d4aa;
  }
  body {
    font-family: 'Segoe UI', system-ui, sans-serif;
    background: var(--bg); color: var(--text);
    min-height: 100vh; display: flex;
    align-items: center; justify-content: center;
    padding: 16px;
  }
  .box {
    background: var(--card); border: 1px solid var(--border);
    border-radius: 18px; padding: 40px 36px;
    width: 100%; max-width: 420px;
    box-shadow: 0 20px 60px rgba(0,0,0,.5);
  }
  .logo { text-align: center; margin-bottom: 32px; }
  .logo-icon {
    width: 64px; height: 64px; margin: 0 auto 14px;
    background: linear-gradient(135deg, var(--accent1), var(--accent2));
    border-radius: 18px; display: flex; align-items: center;
    justify-content: center; font-size: 30px;
  }
  .logo h1 { font-size: 1.5rem; font-weight: 700; }
  .logo h1 span { color: var(--accent2); }
  .logo p { font-size: .85rem; color: var(--muted); margin-top: 6px; }

  .tabs { display: flex; gap: 0; margin-bottom: 28px; border: 1px solid var(--border); border-radius: 10px; overflow: hidden; }
  .tab { flex: 1; padding: 10px; text-align: center; font-size: .85rem; font-weight: 600; cursor: pointer; background: transparent; border: none; color: var(--muted); transition: all .2s; }
  .tab.active { background: var(--accent1); color: #fff; }

  label { display: block; font-size: .78rem; text-transform: uppercase; letter-spacing: .7px; color: var(--muted); margin-bottom: 6px; }
  input[type=email], input[type=password], input[type=text] {
    width: 100%; padding: 12px 14px; border-radius: 10px;
    border: 1px solid var(--border); background: #12151e;
    color: var(--text); font-size: .95rem; margin-bottom: 18px;
    outline: none; transition: border .2s;
  }
  input:focus { border-color: var(--accent1); }

  .btn-submit {
    width: 100%; padding: 13px; border-radius: 10px; border: none;
    background: var(--accent1); color: #fff;
    font-size: 1rem; font-weight: 700; cursor: pointer;
    transition: filter .2s; letter-spacing: .3px;
  }
  .btn-submit:hover { filter: brightness(1.12); }

  .error {
    background: rgba(255,107,107,.12); border: 1px solid rgba(255,107,107,.3);
    color: #ff6b6b; border-radius: 10px; padding: 12px 14px;
    font-size: .85rem; margin-bottom: 18px; text-align: center;
  }
  .register-link { text-align: center; margin-top: 20px; font-size: .83rem; color: var(--muted); }
  .register-link a { color: var(--accent2); text-decoration: none; font-weight: 600; }
  .register-link a:hover { text-decoration: underline; }

  .demo-hint {
    margin-top: 22px; padding: 12px 14px;
    background: rgba(108,99,255,.08); border: 1px solid rgba(108,99,255,.2);
    border-radius: 10px; font-size: .78rem; color: var(--muted); line-height: 1.7;
  }
  .demo-hint strong { color: var(--text); }
</style>
</head>
<body>
<?php
require_once 'auth.php';
startSession();

// Si ya está logueado, redirigir
if (!empty($_SESSION['usuario_id'])) {
    header('Location: ' . ($_SESSION['rol'] === 'veterinario' ? 'dashboard_vet.php' : 'index.php'));
    exit;
}

$error = '';
$tab   = 'login';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = $_POST['accion'] ?? 'login';

    if ($accion === 'login') {
        $email = trim($_POST['email'] ?? '');
        $pass  = $_POST['password'] ?? '';
        if ($email && $pass) {
            $db   = getDB();
            $stmt = $db->prepare("SELECT id, nombre, email, password, rol FROM usuarios WHERE email = ?");
            $stmt->bind_param("s", $email);
            $stmt->execute();
            $user = $stmt->get_result()->fetch_assoc();
            $stmt->close(); $db->close();
            if ($user && password_verify($pass, $user['password'])) {
                $_SESSION['usuario_id'] = $user['id'];
                $_SESSION['nombre']     = $user['nombre'];
                $_SESSION['email']      = $user['email'];
                $_SESSION['rol']        = $user['rol'];
                header('Location: ' . ($user['rol'] === 'veterinario' ? 'dashboard_vet.php' : 'index.php'));
                exit;
            } else {
                $error = 'Correo o contraseña incorrectos.';
            }
        } else {
            $error = 'Completa todos los campos.';
        }
    }

    if ($accion === 'registro') {
        $tab    = 'registro';
        $nombre = trim($_POST['nombre'] ?? '');
        $email  = trim($_POST['email_reg'] ?? '');
        $pass   = $_POST['password_reg'] ?? '';
        $rol    = in_array($_POST['rol'] ?? '', ['dueno','veterinario']) ? $_POST['rol'] : 'dueno';

        if ($nombre && $email && $pass) {
            $db   = getDB();
            $hash = password_hash($pass, PASSWORD_DEFAULT);
            $stmt = $db->prepare("INSERT INTO usuarios (nombre, email, password, rol) VALUES (?,?,?,?)");
            $stmt->bind_param("ssss", $nombre, $email, $hash, $rol);
            if ($stmt->execute()) {
                $_SESSION['usuario_id'] = $db->insert_id;
                $_SESSION['nombre']     = $nombre;
                $_SESSION['email']      = $email;
                $_SESSION['rol']        = $rol;
                $stmt->close(); $db->close();
                header('Location: ' . ($rol === 'veterinario' ? 'dashboard_vet.php' : 'index.php'));
                exit;
            } else {
                $error = 'Ese correo ya está registrado.';
            }
            $stmt->close(); $db->close();
        } else {
            $error = 'Completa todos los campos.';
        }
    }
}
$tab = $_GET['tab'] ?? $tab;
?>

<div class="box">
  <div class="logo">
    <div class="logo-icon">🐾</div>
    <h1>Collar<span>Perro</span></h1>
    <p>Monitor IoT para tu mascota</p>
  </div>

  <div class="tabs">
    <button class="tab <?= $tab==='login'    ? 'active' : '' ?>" onclick="setTab('login')">Iniciar sesión</button>
    <button class="tab <?= $tab==='registro' ? 'active' : '' ?>" onclick="setTab('registro')">Registrarse</button>
  </div>

  <?php if ($error): ?>
    <div class="error"><?= htmlspecialchars($error) ?></div>
  <?php endif; ?>

  <!-- FORM LOGIN -->
  <form method="POST" id="form-login" style="display:<?= $tab==='login' ? 'block':'none' ?>">
    <input type="hidden" name="accion" value="login">
    <label>Correo electrónico</label>
    <input type="email" name="email" placeholder="tu@correo.com" required>
    <label>Contraseña</label>
    <input type="password" name="password" placeholder="••••••••" required>
    <button type="submit" class="btn-submit">Ingresar</button>
  </form>

  <!-- FORM REGISTRO -->
  <form method="POST" id="form-registro" style="display:<?= $tab==='registro' ? 'block':'none' ?>">
    <input type="hidden" name="accion" value="registro">
    <label>Nombre completo</label>
    <input type="text" name="nombre" placeholder="Juan Pérez" required>
    <label>Correo electrónico</label>
    <input type="email" name="email_reg" placeholder="tu@correo.com" required>
    <label>Contraseña</label>
    <input type="password" name="password_reg" placeholder="••••••••" required>
    <label>Tipo de cuenta</label>
    <select name="rol" style="width:100%;padding:12px 14px;border-radius:10px;border:1px solid var(--border);background:#12151e;color:var(--text);font-size:.95rem;margin-bottom:18px;outline:none;">
      <option value="dueno">🏠 Dueño de mascota</option>
      <option value="veterinario">🩺 Veterinario</option>
    </select>
    <button type="submit" class="btn-submit">Crear cuenta</button>
  </form>

  <div class="demo-hint">
    <strong>Cuentas de prueba:</strong><br>
    Dueño → dueno@demo.com / <strong>password</strong><br>
    Veterinario → vet@demo.com / <strong>password</strong>
  </div>
</div>

<script>
function setTab(t) {
  document.getElementById('form-login').style.display    = t==='login'    ? 'block' : 'none';
  document.getElementById('form-registro').style.display = t==='registro' ? 'block' : 'none';
  document.querySelectorAll('.tab').forEach((el,i)=>{
    el.classList.toggle('active', (i===0&&t==='login')||(i===1&&t==='registro'));
  });
}
</script>
</body>
</html>
