<?php
require_once 'auth.php';
requireLogin('dueno');
$usuario = usuarioActual();
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>CollarPerro — Mi mascota</title>
<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.1/chart.umd.min.js"></script>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.min.css"/>
<script src="https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.min.js"></script>
<style>
  *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
  :root {
    --bg:#0f1117; --surface:#1a1d27; --card:#21253a; --border:#2e3354;
    --text:#e8eaf0; --muted:#7a80a0; --radius:14px;
    --accent1:#6c63ff; --accent2:#00d4aa; --shadow:0 4px 24px rgba(0,0,0,.4);
  }
  body { font-family:'Segoe UI',system-ui,sans-serif; background:var(--bg); color:var(--text); min-height:100vh; padding:24px 16px 40px; }

  header { display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:12px; margin-bottom:32px; }
  .logo { display:flex; align-items:center; gap:12px; }
  .logo-icon { width:44px; height:44px; background:linear-gradient(135deg,var(--accent1),var(--accent2)); border-radius:12px; display:flex; align-items:center; justify-content:center; font-size:22px; }
  h1 { font-size:1.5rem; font-weight:700; }
  h1 span { color:var(--accent2); }
  .status-badge { display:flex; align-items:center; gap:8px; background:var(--card); border:1px solid var(--border); border-radius:99px; padding:8px 16px; font-size:.82rem; color:var(--muted); }
  .dot { width:8px; height:8px; border-radius:50%; background:#555; transition:background .3s; }
  .dot.online { background:var(--accent2); box-shadow:0 0 6px var(--accent2); }
  #last-update { font-size:.78rem; color:var(--muted); }

  .gauges-grid { display:grid; grid-template-columns:repeat(auto-fit,minmax(200px,1fr)); gap:20px; margin-bottom:28px; }
  .gauge-card { background:var(--card); border:1px solid var(--border); border-radius:var(--radius); padding:24px 16px 20px; display:flex; flex-direction:column; align-items:center; gap:12px; box-shadow:var(--shadow); }
  .gauge-label { font-size:.72rem; text-transform:uppercase; letter-spacing:.9px; color:var(--muted); }
  .gauge-wrap { position:relative; width:140px; height:140px; }
  .gauge-center { position:absolute; inset:0; display:flex; flex-direction:column; align-items:center; justify-content:center; gap:2px; pointer-events:none; }
  .gauge-val { font-size:1.6rem; font-weight:700; line-height:1; }
  .gauge-unit { font-size:.75rem; color:var(--muted); }
  .gauge-status { font-size:.78rem; font-weight:600; padding:4px 12px; border-radius:99px; text-align:center; min-width:100px; }

  .info-grid { display:grid; grid-template-columns:repeat(auto-fit,minmax(200px,1fr)); gap:20px; margin-bottom:28px; }
  .info-card { background:var(--card); border:1px solid var(--border); border-radius:var(--radius); padding:22px 20px; display:flex; align-items:center; gap:16px; box-shadow:var(--shadow); }
  .info-icon { font-size:2rem; flex-shrink:0; }
  .info-label { font-size:.72rem; text-transform:uppercase; letter-spacing:.8px; color:var(--muted); margin-bottom:6px; }
  .info-val { font-size:1.05rem; font-weight:700; line-height:1.3; }

  .charts-grid { display:grid; grid-template-columns:repeat(auto-fit,minmax(300px,1fr)); gap:20px; margin-bottom:28px; }
  .chart-card { background:var(--card); border:1px solid var(--border); border-radius:var(--radius); padding:20px; box-shadow:var(--shadow); }
  .chart-title { font-size:.82rem; font-weight:600; text-transform:uppercase; letter-spacing:.7px; color:var(--muted); margin-bottom:16px; }

  .action-buttons { display:flex; gap:12px; flex-wrap:wrap; }
  .btn { display:flex; align-items:center; gap:9px; padding:13px 22px; border-radius:10px; border:none; font-size:.9rem; font-weight:600; cursor:pointer; transition:all .2s; }
  .btn:hover { transform:translateY(-2px); filter:brightness(1.1); }
  .btn:active { transform:translateY(0); }
  .btn-historial { background:var(--accent1); color:#fff; box-shadow:0 4px 16px rgba(108,99,255,.35); }
  .btn-mapa      { background:var(--accent2); color:#0f1117; box-shadow:0 4px 16px rgba(0,212,170,.3); }
  .btn-config    { background:#2e3354; color:var(--text); border:1px solid var(--border); }
  .btn-config:hover { background:#3a4070; }

  /* ── MODALES ── */
  .overlay { display:none; position:fixed; inset:0; background:rgba(0,0,0,.65); z-index:100; align-items:center; justify-content:center; padding:16px; }
  .overlay.open { display:flex; }
  .modal { background:var(--surface); border:1px solid var(--border); border-radius:18px; width:100%; max-width:860px; max-height:88vh; display:flex; flex-direction:column; box-shadow:0 20px 60px rgba(0,0,0,.6); }
  .modal-config { max-width:640px; }
  .modal-header { display:flex; align-items:center; justify-content:space-between; padding:20px 24px 16px; border-bottom:1px solid var(--border); flex-shrink:0; }
  .modal-title { font-size:1.05rem; font-weight:700; }
  .btn-close { background:rgba(255,255,255,.08); border:none; color:var(--muted); width:34px; height:34px; border-radius:8px; font-size:1.1rem; cursor:pointer; transition:background .2s; display:flex; align-items:center; justify-content:center; }
  .btn-close:hover { background:rgba(255,255,255,.18); color:var(--text); }
  .modal-body { padding:20px 24px 24px; overflow-y:auto; flex:1; }

  /* ── CONFIGURACIÓN ── */
  .cfg-section { margin-bottom:24px; }
  .cfg-section-title { font-size:.78rem; text-transform:uppercase; letter-spacing:.9px; color:var(--accent2); font-weight:700; margin-bottom:14px; padding-bottom:8px; border-bottom:1px solid var(--border); }
  .cfg-grid { display:grid; grid-template-columns:repeat(auto-fit,minmax(220px,1fr)); gap:12px; }
  .cfg-field { display:flex; flex-direction:column; gap:6px; }
  .cfg-field label { font-size:.78rem; color:var(--muted); }
  .cfg-field input[type=number] {
    background:var(--card); border:1px solid var(--border); border-radius:8px;
    color:var(--text); padding:9px 12px; font-size:.9rem; width:100%;
    outline:none; transition:border-color .2s;
  }
  .cfg-field input[type=number]:focus { border-color:var(--accent1); }
  .cfg-field input[type=number]::-webkit-inner-spin-button { opacity:.5; }
  .cfg-actions { display:flex; gap:10px; justify-content:flex-end; margin-top:20px; padding-top:16px; border-top:1px solid var(--border); }
  .btn-save { background:var(--accent1); color:#fff; padding:11px 28px; border-radius:9px; border:none; font-size:.9rem; font-weight:600; cursor:pointer; transition:all .2s; }
  .btn-save:hover { filter:brightness(1.12); }
  .btn-reset { background:transparent; color:var(--muted); padding:11px 20px; border-radius:9px; border:1px solid var(--border); font-size:.9rem; cursor:pointer; transition:all .2s; }
  .btn-reset:hover { color:var(--text); border-color:var(--text); }
  .cfg-toast { display:none; font-size:.82rem; color:var(--accent2); align-items:center; gap:6px; }
  .cfg-toast.show { display:flex; }

  /* ── TABLA ── */
  table { width:100%; border-collapse:collapse; font-size:.82rem; }
  th { text-align:left; padding:8px 12px; border-bottom:1px solid var(--border); color:var(--muted); font-weight:500; white-space:nowrap; }
  td { padding:9px 12px; border-bottom:1px solid rgba(46,51,84,.5); white-space:nowrap; }
  tr:last-child td { border-bottom:none; }
  tr:hover td { background:rgba(108,99,255,.07); }
  .loading { text-align:center; padding:40px; color:var(--muted); font-size:.9rem; }

  #map { height:420px; width:100%; border-radius:12px; }
  .gps-pending { display:flex; flex-direction:column; align-items:center; justify-content:center; height:300px; gap:16px; color:var(--muted); text-align:center; }
  .gps-pending .icon { font-size:3rem; opacity:.35; }
  .gps-pending p { font-size:.88rem; max-width:300px; line-height:1.6; }
  .gps-info { margin-top:14px; background:rgba(0,212,170,.08); border:1px solid rgba(0,212,170,.22); border-radius:10px; padding:14px 18px; font-size:.82rem; color:var(--accent2); line-height:1.8; }

  @media(max-width:480px){
    h1{font-size:1.2rem;} .gauge-wrap{width:120px;height:120px;} .gauge-val{font-size:1.3rem;}
    .btn{padding:11px 16px;font-size:.84rem;} .modal{max-height:94vh;}
  }

  .overlay-sm { display:none; position:fixed; inset:0; background:rgba(0,0,0,.65); z-index:200; align-items:center; justify-content:center; padding:16px; }
  .overlay-sm.open { display:flex; }
  .modal-sm { background:var(--surface); border:1px solid var(--border); border-radius:18px; width:100%; max-width:440px; box-shadow:0 20px 60px rgba(0,0,0,.6); }
  .mfield label { display:block; font-size:.75rem; text-transform:uppercase; letter-spacing:.7px; color:var(--muted); margin-bottom:6px; }
  .mfield input, .mfield select { width:100%; padding:10px 14px; border-radius:8px; border:1px solid var(--border); background:#12151e; color:var(--text); font-size:.9rem; margin-bottom:14px; outline:none; }
  .mfield input:focus { border-color:var(--accent1); }
  .btn-guardar { width:100%; padding:12px; border-radius:10px; border:none; background:var(--accent1); color:#fff; font-size:.95rem; font-weight:700; cursor:pointer; }

  /* ── Sección ML ─────────────────────────────────────────── */
  .ml-section { margin-top: 28px; }
  .ml-header { display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:10px; margin-bottom:16px; }
  .ml-title { font-size:.9rem; font-weight:700; text-transform:uppercase; letter-spacing:.8px; color:var(--muted); display:flex; align-items:center; gap:8px; }
  .ml-badge { font-size:.72rem; padding:3px 10px; border-radius:99px; background:rgba(108,99,255,.15); color:#a09aff; border:1px solid rgba(108,99,255,.25); }
  .btn-ml { display:flex; align-items:center; gap:8px; padding:9px 18px; border-radius:9px; border:none; background:linear-gradient(135deg,var(--accent1),#9b59b6); color:#fff; font-size:.85rem; font-weight:600; cursor:pointer; transition:all .2s; }
  .btn-ml:hover { filter:brightness(1.12); transform:translateY(-1px); }
  .btn-ml:disabled { opacity:.5; cursor:not-allowed; transform:none; }
  .ml-grid { display:grid; grid-template-columns:repeat(auto-fit,minmax(280px,1fr)); gap:16px; }
  .ml-card { background:var(--card); border:1px solid var(--border); border-radius:var(--radius); padding:20px; box-shadow:var(--shadow); }
  .ml-card-header { display:flex; align-items:center; gap:10px; margin-bottom:14px; }
  .ml-card-icon { font-size:1.6rem; }
  .ml-card-label { font-size:.75rem; text-transform:uppercase; letter-spacing:.7px; color:var(--muted); }
  .ml-card-title { font-size:.95rem; font-weight:700; }
  .ml-result { display:flex; align-items:center; gap:10px; padding:12px 14px; border-radius:10px; margin-bottom:10px; }
  .ml-result-icon { font-size:1.4rem; flex-shrink:0; }
  .ml-result-text { font-size:.85rem; line-height:1.5; }
  .ml-result-val { font-size:1rem; font-weight:700; }
  .ml-bar-wrap { margin-top:10px; }
  .ml-bar-label { display:flex; justify-content:space-between; font-size:.74rem; color:var(--muted); margin-bottom:4px; }
  .ml-bar { height:6px; border-radius:99px; background:var(--border); overflow:hidden; margin-bottom:6px; }
  .ml-bar-fill { height:100%; border-radius:99px; transition:width .6s ease; }
  .ml-no-data { text-align:center; padding:32px; color:var(--muted); font-size:.85rem; }
  .ml-no-data .icon { font-size:2rem; opacity:.4; margin-bottom:10px; }
  .ml-meta { font-size:.72rem; color:var(--muted); margin-top:12px; padding-top:10px; border-top:1px solid var(--border); }
  .c-verde   { color:#00d4aa; } .bg-verde   { background:rgba(0,212,170,.1);  border:1px solid rgba(0,212,170,.2); }
  .c-amarillo{ color:#ffd93d; } .bg-amarillo{ background:rgba(255,217,61,.1); border:1px solid rgba(255,217,61,.2); }
  .c-naranja { color:#ff9f43; } .bg-naranja { background:rgba(255,159,67,.1); border:1px solid rgba(255,159,67,.2); }
  .c-rojo    { color:#ff6b6b; } .bg-rojo    { background:rgba(255,107,107,.1);border:1px solid rgba(255,107,107,.2); }
  .c-azul    { color:#74b9ff; } .bg-azul    { background:rgba(116,185,255,.1);border:1px solid rgba(116,185,255,.2); }
</style>
</head>
<body>

<header>
  <div class="logo">
    <div class="logo-icon">🐕</div>
    <div>
      <h1>Collar<span>Perro</span></h1>
      <div style="font-size:.78rem;color:var(--muted);">Monitor IoT en tiempo real</div>
    </div>
  </div>
  <div style="display:flex;flex-direction:column;align-items:flex-end;gap:6px;">
    <div class="status-badge">
      <span class="dot" id="dot"></span>
      <span id="conn-label">Conectando…</span>
    </div>
    <div style="display:flex;align-items:center;gap:10px;">
      <span style="font-size:.82rem;color:var(--muted);">👤 <?= htmlspecialchars($usuario['nombre']) ?></span>
      <a href="logout.php" style="font-size:.78rem;color:#ff6b6b;text-decoration:none;background:rgba(255,107,107,.1);padding:5px 12px;border-radius:8px;border:1px solid rgba(255,107,107,.25);">Salir</a>
    </div>
    <div id="last-update">—</div>
  </div>
</header>

<!-- SELECTOR DE MASCOTA -->
<div style="display:flex;align-items:center;gap:12px;margin-bottom:24px;flex-wrap:wrap;">
  <select id="sel-mascota" style="background:var(--card);border:1px solid var(--border);color:var(--text);padding:10px 16px;border-radius:10px;font-size:.9rem;cursor:pointer;min-width:200px;">
    <option value="">Cargando mascotas…</option>
  </select>
  <button class="btn" style="background:var(--accent2);color:#0f1117;padding:10px 18px;font-size:.85rem;" onclick="abrirAgregarMascota()">➕ Agregar mascota</button>
</div>

<!-- GAUGES -->
<div class="gauges-grid">
  <div class="gauge-card">
    <div class="gauge-label">Temperatura</div>
    <div class="gauge-wrap">
      <canvas id="gaugeTemp" width="140" height="140" role="img" aria-label="Gauge de temperatura"></canvas>
      <div class="gauge-center">
        <div class="gauge-val" id="g-temp-val">--</div>
        <div class="gauge-unit">°C</div>
      </div>
    </div>
    <div class="gauge-status" id="g-temp-status" style="background:rgba(120,120,140,.15);color:var(--muted);">Sin datos</div>
  </div>
  <div class="gauge-card">
    <div class="gauge-label">Nivel de estrés</div>
    <div class="gauge-wrap">
      <canvas id="gaugeEstres" width="140" height="140" role="img" aria-label="Gauge de estrés"></canvas>
      <div class="gauge-center">
        <div class="gauge-val" id="g-estres-icon" style="font-size:2rem;">--</div>
      </div>
    </div>
    <div class="gauge-status" id="g-estres-status" style="background:rgba(120,120,140,.15);color:var(--muted);">Sin datos</div>
  </div>
</div>

<!-- INFO CARDS -->
<div class="info-grid">
  <div class="info-card">
    <div class="info-icon" id="act-icon">😴</div>
    <div><div class="info-label">Actividad</div><div class="info-val" id="val-actividad">--</div></div>
  </div>
  <div class="info-card">
    <div class="info-icon">⏱️</div>
    <div><div class="info-label">Última lectura</div><div class="info-val" id="val-fecha">--</div></div>
  </div>
</div>

<!-- GRÁFICAS -->
<div class="charts-grid">
  <div class="chart-card">
    <div class="chart-title">🌡️ Temperatura (°C) — historial</div>
    <div style="position:relative;height:200px;">
      <canvas id="chartTemp" role="img" aria-label="Historial temperatura">Historial de temperatura.</canvas>
    </div>
  </div>
  <div class="chart-card">
    <div class="chart-title">❤️ Pulso (BPM) — historial</div>
    <div style="position:relative;height:200px;">
      <canvas id="chartBPM" role="img" aria-label="Historial BPM">Historial de pulso.</canvas>
    </div>
  </div>
</div>

<!-- BOTONES -->

<!-- ── SECCIÓN ML ─────────────────────────────────────────── -->
<div class="ml-section">
  <div class="ml-header">
    <div class="ml-title">
      🧠 Análisis de patrones
      <span class="ml-badge">IA Local</span>
    </div>
    <button class="btn-ml" id="btn-ml" onclick="ejecutarML()">
      🔍 Analizar patrones
    </button>
  </div>
  <div id="ml-contenido">
    <div class="ml-no-data">
      <div class="icon">🧬</div>
      <div>Presiona "Analizar patrones" para detectar tendencias,<br>estrés crónico y cambios en hábitos de tu mascota.</div>
    </div>
  </div>
</div>

<div class="action-buttons">
  <button class="btn btn-historial" onclick="abrirHistorial()">🦴 Ver historial reciente</button>
  <button class="btn btn-mapa"      onclick="abrirMapa()">🐾 Mapa GPS</button>
  <button class="btn btn-config"    onclick="abrirConfig()">⚙️ Configurar umbrales</button>
</div>


<!-- MODAL AGREGAR MASCOTA -->
<div class="overlay-sm" id="overlay-mascota">
  <div class="modal-sm">
    <div class="modal-header">
      <div class="modal-title">🐾 Agregar mascota</div>
      <button class="btn-close" onclick="cerrarOverlay('overlay-mascota')">✕</button>
    </div>
    <div class="modal-body">
      <div id="msg-mascota" style="display:none;margin-bottom:14px;padding:10px 14px;border-radius:8px;font-size:.85rem;"></div>
      <div class="mfield">
        <label>Nombre de la mascota</label>
        <input type="text" id="m-nombre" placeholder="Max">
        <label>Raza</label>
        <input type="text" id="m-raza" placeholder="Labrador">
        <label>Edad (años)</label>
        <input type="number" id="m-edad" min="0" max="30" placeholder="3">
        <label>MAC del ESP32 del collar</label>
        <input type="text" id="m-mac" placeholder="AA:BB:CC:DD:EE:FF" style="font-family:monospace;">
        <div style="font-size:.75rem;color:var(--muted);margin-top:-10px;margin-bottom:14px;">Encuéntrala en el Serial Monitor al iniciar el ESP32</div>
        <label>Correo del veterinario (opcional)</label>
        <input type="email" id="m-vet" placeholder="vet@clinica.com">
      </div>
      <button class="btn-guardar" onclick="guardarMascota()">Guardar mascota</button>
    </div>
  </div>
</div>

<!-- ═══ MODAL HISTORIAL ═══ -->
<div class="overlay" id="overlay-historial">
  <div class="modal">
    <div class="modal-header">
      <div class="modal-title">🦴 Historial reciente</div>
      <button class="btn-close" onclick="cerrarOverlay('overlay-historial')">✕</button>
    </div>
    <div class="modal-body">
      <table>
        <thead><tr>
          <th>#</th><th>Hora</th><th>Temp (°C)</th><th>BPM</th>
          <th>Actividad</th><th>Est. pulso</th><th>Est. temp</th><th>Estrés</th><th>Modo</th>
        </tr></thead>
        <tbody id="tabla-body">
          <tr><td colspan="9" class="loading">Cargando datos…</td></tr>
        </tbody>
      </table>
    </div>
  </div>
</div>

<!-- ═══ MODAL MAPA ═══ -->
<div class="overlay" id="overlay-mapa">
  <div class="modal">
    <div class="modal-header">
      <div class="modal-title">🐾 Ubicación del collar</div>
      <button class="btn-close" onclick="cerrarOverlay('overlay-mapa')">✕</button>
    </div>
    <div class="modal-body">
      <div id="gps-pending">
        <div class="gps-pending">
          <div class="icon">🛰️</div>
          <p>El módulo GPS aún no está conectado. Cuando se integre, la ubicación aparecerá aquí automáticamente.</p>
        </div>
        <div class="gps-info">
          <strong>¿Qué se mostrará aquí?</strong><br>
          • Posición en tiempo real del collar<br>
          • Historial del recorrido del perro<br>
          • Distancia desde el punto de origen<br>
          • Alertas si sale de una zona segura
        </div>
      </div>
      <div id="mapa-container" style="display:none;"><div id="map"></div></div>
    </div>
  </div>
</div>

<!-- ═══ MODAL CONFIGURACIÓN ═══ -->
<div class="overlay" id="overlay-config">
  <div class="modal modal-config">
    <div class="modal-header">
      <div class="modal-title">⚙️ Configurar umbrales de análisis</div>
      <button class="btn-close" onclick="cerrarOverlay('overlay-config')">✕</button>
    </div>
    <div class="modal-body">

      <!-- TEMPERATURA -->
      <div class="cfg-section">
        <div class="cfg-section-title">🌡️ Temperatura (°C)</div>
        <div class="cfg-grid">
          <div class="cfg-field">
            <label>Hipotermia — por debajo de</label>
            <input type="number" id="cfg-temp_hipotermia" step="0.1" min="30" max="40">
          </div>
          <div class="cfg-field">
            <label>Temperatura baja — por debajo de</label>
            <input type="number" id="cfg-temp_baja" step="0.1" min="30" max="41">
          </div>
          <div class="cfg-field">
            <label>Temperatura normal — hasta</label>
            <input type="number" id="cfg-temp_normal_max" step="0.1" min="35" max="42">
          </div>
          <div class="cfg-field">
            <label>Temperatura alta — hasta</label>
            <input type="number" id="cfg-temp_alta" step="0.1" min="38" max="43">
          </div>
          <div class="cfg-field">
            <label>Fiebre — hasta (sobre esto = peligroso)</label>
            <input type="number" id="cfg-temp_fiebre" step="0.1" min="38" max="44">
          </div>
        </div>
      </div>

      <!-- BPM ESTADO -->
      <div class="cfg-section">
        <div class="cfg-section-title">❤️ Pulso — clasificación (BPM)</div>
        <div class="cfg-grid">
          <div class="cfg-field">
            <label>Bradicardia — por debajo de</label>
            <input type="number" id="cfg-bpm_bradicardia" step="1" min="20" max="80">
          </div>
          <div class="cfg-field">
            <label>Pulso bajo — por debajo de</label>
            <input type="number" id="cfg-bpm_bajo" step="1" min="30" max="90">
          </div>
          <div class="cfg-field">
            <label>Pulso normal — hasta</label>
            <input type="number" id="cfg-bpm_normal_max" step="1" min="60" max="150">
          </div>
          <div class="cfg-field">
            <label>Pulso elevado — hasta</label>
            <input type="number" id="cfg-bpm_elevado" step="1" min="80" max="180">
          </div>
          <div class="cfg-field">
            <label>Pulso alto — hasta (sobre esto = taquicardia)</label>
            <input type="number" id="cfg-bpm_alto" step="1" min="100" max="220">
          </div>
        </div>
      </div>

      <!-- ESTRÉS -->
      <div class="cfg-section">
        <div class="cfg-section-title">🧠 Umbrales de estrés (BPM)</div>
        <div style="font-size:.78rem;color:var(--muted);margin-bottom:12px;">En reposo</div>
        <div class="cfg-grid" style="margin-bottom:14px;">
          <div class="cfg-field">
            <label>Estrés leve — sobre</label>
            <input type="number" id="cfg-bpm_estres_leve_reposo" step="1" min="40" max="200">
          </div>
          <div class="cfg-field">
            <label>Estrés alto — sobre</label>
            <input type="number" id="cfg-bpm_estres_alto_reposo" step="1" min="40" max="200">
          </div>
          <div class="cfg-field">
            <label>Estrés severo — sobre</label>
            <input type="number" id="cfg-bpm_estres_severo_reposo" step="1" min="40" max="220">
          </div>
        </div>
        <div style="font-size:.78rem;color:var(--muted);margin-bottom:12px;">En actividad moderada</div>
        <div class="cfg-grid" style="margin-bottom:14px;">
          <div class="cfg-field">
            <label>Estrés leve — sobre</label>
            <input type="number" id="cfg-bpm_estres_leve_moderado" step="1" min="40" max="220">
          </div>
          <div class="cfg-field">
            <label>Estrés alto — sobre</label>
            <input type="number" id="cfg-bpm_estres_alto_moderado" step="1" min="40" max="220">
          </div>
        </div>
        <div style="font-size:.78rem;color:var(--muted);margin-bottom:12px;">En alta actividad</div>
        <div class="cfg-grid">
          <div class="cfg-field">
            <label>Esfuerzo máximo — sobre</label>
            <input type="number" id="cfg-bpm_estres_alto_activo" step="1" min="40" max="220">
          </div>
        </div>
      </div>

      <div class="cfg-actions">
        <div class="cfg-toast" id="cfg-toast">✅ Umbrales guardados correctamente</div>
        <button class="btn-reset" onclick="resetUmbrales()">Restaurar valores por defecto</button>
        <button class="btn-save"  onclick="guardarUmbrales()">Guardar cambios</button>
      </div>
    </div>
  </div>
</div>

<script>
const API_URL    = 'leer.php';
const CONFIG_URL = 'umbrales.php';
const POLL_MS    = 3000;
let historialData = [];
let mapaIniciado = false, leafletMap = null, marker = null;

// Umbrales actuales en memoria (se usan para los gauges del frontend)
let umbrales = {
  temp_hipotermia:36, temp_baja:37.5, temp_normal_max:39.2, temp_alta:39.5, temp_fiebre:40.5,
  bpm_bradicardia:50, bpm_bajo:60, bpm_normal_max:100, bpm_elevado:140, bpm_alto:160,
  bpm_estres_leve_reposo:100, bpm_estres_alto_reposo:120, bpm_estres_severo_reposo:160,
  bpm_estres_leve_moderado:140, bpm_estres_alto_moderado:160, bpm_estres_alto_activo:160,
  accel_reposo:0.05, accel_moderado:0.20
};

// ── Gauge circular ─────────────────────────────────────────
function drawGauge(id, pct, color, track) {
  const c = document.getElementById(id);
  if (!c) return;
  const ctx = c.getContext('2d'), cx=70, cy=70, r=54, lw=10;
  const s = Math.PI*.75, e = Math.PI*2.25, full = e-s;
  ctx.clearRect(0,0,140,140);
  ctx.beginPath(); ctx.arc(cx,cy,r,s,e);
  ctx.strokeStyle=track||'#2e3354'; ctx.lineWidth=lw; ctx.lineCap='round'; ctx.stroke();
  if (pct>0) {
    ctx.beginPath(); ctx.arc(cx,cy,r,s,s+full*Math.min(pct,1));
    ctx.strokeStyle=color; ctx.lineWidth=lw; ctx.lineCap='round'; ctx.stroke();
  }
}

function tempInfo(t) {
  const u = umbrales;
  if (t===null||isNaN(t)) return {color:'#7a80a0',track:'#2e3354',label:'Sin datos',bg:'rgba(120,120,140,.15)',lc:'#7a80a0',pct:0};
  const min=34, max=42, pct=Math.max(0,Math.min(1,(t-min)/(max-min)));
  if (t < u.temp_hipotermia)  return {color:'#74b9ff',track:'#1a3a5c',label:'❄️ Hipotermia',  bg:'rgba(116,185,255,.15)',lc:'#74b9ff',pct};
  if (t < u.temp_baja)        return {color:'#85ecff',track:'#1a3a5c',label:'🌡 Temp. baja',  bg:'rgba(133,236,255,.12)',lc:'#85ecff',pct};
  if (t <= u.temp_normal_max) return {color:'#00d4aa',track:'#0a3028',label:'✅ Normal',        bg:'rgba(0,212,170,.12)',  lc:'#00d4aa',pct};
  if (t <= u.temp_alta)       return {color:'#ffd93d',track:'#2e2800',label:'⚠️ Temp. alta',   bg:'rgba(255,217,61,.12)', lc:'#ffd93d',pct};
  if (t <= u.temp_fiebre)     return {color:'#ff9f43',track:'#2e1800',label:'🔴 Fiebre',        bg:'rgba(255,159,67,.15)', lc:'#ff9f43',pct};
  return                             {color:'#ff6b6b',track:'#2e1010',label:'🚨 Peligroso',    bg:'rgba(255,107,107,.18)',lc:'#ff6b6b',pct};
}

function estresInfo(s) {
  const v = (s||'').toLowerCase();
  if (!s||s==='--')              return {color:'#7a80a0',track:'#2e3354',icon:'❔',pct:0,   label:'Sin datos',       bg:'rgba(120,120,140,.15)',lc:'#7a80a0'};
  if (v.includes('sin datos'))   return {color:'#7a80a0',track:'#2e3354',icon:'💤',pct:.05, label:'Sin datos pulso', bg:'rgba(120,120,140,.15)',lc:'#7a80a0'};
  if (v.includes('sin estres')||v.includes('sin estrés')||v.includes('normal')||v.includes('esfuerzo normal'))
                                 return {color:'#00d4aa',track:'#0a3028',icon:'💚',pct:.2,  label:'Sin estrés',      bg:'rgba(0,212,170,.12)',  lc:'#00d4aa'};
  if (v.includes('leve'))        return {color:'#ffd93d',track:'#2e2800',icon:'⚠️',pct:.5,  label:'Estrés leve',     bg:'rgba(255,217,61,.12)', lc:'#ffd93d'};
  if (v.includes('alto')||v.includes('excesivo')||v.includes('maximo')||v.includes('sobrecarga'))
                                 return {color:'#ff9f43',track:'#2e1800',icon:'🔶',pct:.75, label:'Estrés alto',     bg:'rgba(255,159,67,.15)', lc:'#ff9f43'};
  if (v.includes('severo')||v.includes('alerta')||v.includes('vet'))
                                 return {color:'#ff6b6b',track:'#2e1010',icon:'🆘',pct:1.0, label:'Crítico — vet',   bg:'rgba(255,107,107,.18)',lc:'#ff6b6b'};
  return {color:'#6c63ff',track:'#1a1840',icon:'🔵',pct:.3,label:s,bg:'rgba(108,99,255,.15)',lc:'#a09aff'};
}

function setStatus(id, bg, lc, text) {
  const el = document.getElementById(id);
  el.style.background = bg; el.style.color = lc; el.textContent = text;
}

// ── Charts ────────────────────────────────────────────────
const mkChart = (id, color, label) => new Chart(document.getElementById(id), {
  type:'line',
  data:{labels:[],datasets:[{label,data:[],borderColor:color,backgroundColor:color+'22',borderWidth:2,pointRadius:2,tension:0.4,fill:true}]},
  options:{responsive:true,maintainAspectRatio:false,animation:{duration:300},
    scales:{x:{ticks:{color:'#7a80a0',maxTicksLimit:6,maxRotation:0},grid:{color:'#2e3354'}},y:{ticks:{color:'#7a80a0'},grid:{color:'#2e3354'}}},
    plugins:{legend:{display:false}}}
});
const chartTemp = mkChart('chartTemp','#ff6b6b','Temperatura');
const chartBPM  = mkChart('chartBPM', '#74b9ff','BPM');
function setChart(c, labels, data) {
  c.data.labels = labels.slice();
  c.data.datasets[0].data = data.slice();
  c.update();
}

// ── Tabla ─────────────────────────────────────────────────
function buildTable(h) {
  const tb = document.getElementById('tabla-body');
  if (!h||!h.length){tb.innerHTML='<tr><td colspan="9" class="loading">Sin datos aún</td></tr>';return;}
  tb.innerHTML=[...h].reverse().slice(0,30).map((r,i)=>`
    <tr><td style="color:var(--muted)">${i+1}</td><td>${r.hora??'—'}</td>
    <td style="color:#ff6b6b">${r.temperatura??'—'}</td><td style="color:#74b9ff">${r.bpm??'—'}</td>
    <td>${r.actividad??'—'}</td><td>${r.estado_pulso??'—'}</td>
    <td>${r.estado_temp??'—'}</td><td>${r.estres??'—'}</td><td>${r.modo??'—'}</td></tr>`).join('');
}

// ── Polling ───────────────────────────────────────────────
async function poll() {
  try {
    const midParam = mascotaActual ? '&mascota_id='+mascotaActual : '';
    const res = await fetch(API_URL+'?_='+Date.now()+midParam);
    const data = await res.json();
    if (!data.ok) throw new Error('err');
    const d = data.latest||{};
    const t = parseFloat(d.temperatura);
    const ti = tempInfo(isNaN(t)?null:t);
    drawGauge('gaugeTemp',ti.pct,ti.color,ti.track);
    document.getElementById('g-temp-val').textContent = isNaN(t)?'--':t.toFixed(1);
    document.getElementById('g-temp-val').style.color = ti.color;
    setStatus('g-temp-status',ti.bg,ti.lc,ti.label);
    const ei = estresInfo(d.estres);
    drawGauge('gaugeEstres',ei.pct,ei.color,ei.track);
    document.getElementById('g-estres-icon').textContent = ei.icon;
    setStatus('g-estres-status',ei.bg,ei.lc,ei.label);
    const actMap = {
      'REPOSO':            { emoji:'😴', label:'😴 REPOSO' },
      'ACTIVIDAD MODERADA':{ emoji:'🚶', label:'🚶 ACTIVIDAD MODERADA' },
      'ALTA ACTIVIDAD':    { emoji:'🏃', label:'🏃 ALTA ACTIVIDAD' }
    };
    const actData = actMap[d.actividad] || { emoji:'🐕', label: d.actividad ?? '--' };
    document.getElementById('val-actividad').textContent = actData.label;
    const actIconEl = document.getElementById('act-icon');
    if (actIconEl) actIconEl.textContent = actData.emoji;
    document.getElementById('val-fecha').textContent     = d.fecha_hora??'--';
    const h = data.history||[];
    historialData = h;
    const validTemp = h.filter(r => !isNaN(parseFloat(r.temperatura)));
    const validBpm  = h.filter(r => !isNaN(parseInt(r.bpm)) && parseInt(r.bpm) > 0);
    setChart(chartTemp, validTemp.map(r=>r.hora), validTemp.map(r=>parseFloat(r.temperatura)));
    setChart(chartBPM,  validBpm.map(r=>r.hora),  validBpm.map(r=>parseInt(r.bpm)));
    if (document.getElementById('overlay-historial').classList.contains('open')) buildTable(h);
    if (d.lat&&d.lng) actualizarMapa(parseFloat(d.lat),parseFloat(d.lng));
    document.getElementById('dot').classList.add('online');
    document.getElementById('conn-label').textContent = 'En línea';
    document.getElementById('last-update').textContent = 'Actualizado: '+new Date().toLocaleTimeString('es');
  } catch {
    document.getElementById('dot').classList.remove('online');
    document.getElementById('conn-label').textContent = 'Sin conexión';
  }
}

// ── Configuración ─────────────────────────────────────────
async function cargarConfig() {
  try {
    const res = await fetch(CONFIG_URL+'?_='+Date.now());
    const data = await res.json();
    if (!data.ok) return;
    umbrales = Object.assign(umbrales, data.config);
    Object.keys(umbrales).forEach(k => {
      const el = document.getElementById('cfg-'+k);
      if (el) el.value = umbrales[k];
    });
  } catch(e) { console.warn('No se pudo cargar config', e); }
}

async function guardarUmbrales() {
  const campos = Object.keys(umbrales);
  const payload = {};
  let valido = true;
  campos.forEach(k => {
    const el = document.getElementById('cfg-'+k);
    if (el) {
      const v = parseFloat(el.value);
      if (isNaN(v)) { valido=false; el.style.borderColor='#ff6b6b'; }
      else { el.style.borderColor=''; payload[k] = v; }
    }
  });
  if (!valido) return;
  try {
    const res = await fetch(CONFIG_URL, {method:'POST', headers:{'Content-Type':'application/json'}, body:JSON.stringify(payload)});
    const data = await res.json();
    if (data.ok) {
      umbrales = Object.assign(umbrales, data.config);
      const toast = document.getElementById('cfg-toast');
      toast.classList.add('show');
      setTimeout(()=>toast.classList.remove('show'), 3000);
    }
  } catch(e) { alert('Error al guardar'); }
}

function resetUmbrales() {
  const defaults = {
    temp_hipotermia:36, temp_baja:37.5, temp_normal_max:39.2, temp_alta:39.5, temp_fiebre:40.5,
    bpm_bradicardia:50, bpm_bajo:60, bpm_normal_max:100, bpm_elevado:140, bpm_alto:160,
    bpm_estres_leve_reposo:100, bpm_estres_alto_reposo:120, bpm_estres_severo_reposo:160,
    bpm_estres_leve_moderado:140, bpm_estres_alto_moderado:160, bpm_estres_alto_activo:160,
    accel_reposo:0.05, accel_moderado:0.20
  };
  Object.keys(defaults).forEach(k => {
    const el = document.getElementById('cfg-'+k);
    if (el) el.value = defaults[k];
  });
}

function abrirConfig() {
  cargarConfig();
  document.getElementById('overlay-config').classList.add('open');
}

// ── Modales ───────────────────────────────────────────────
function abrirHistorial() { buildTable(historialData); document.getElementById('overlay-historial').classList.add('open'); }
function abrirMapa() {
  document.getElementById('overlay-mapa').classList.add('open');
  if (!mapaIniciado) {
    setTimeout(() => {
      leafletMap = L.map('map').setView([4.7110,-74.0721], 13);
      L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {attribution:'© OpenStreetMap'}).addTo(leafletMap);
      mapaIniciado = true;
      // Forzar redimensión después de que el modal esté visible
      setTimeout(() => leafletMap.invalidateSize(), 100);
    }, 150);
  } else {
    // Mapa ya iniciado: forzar redimensión cada vez que se abre
    setTimeout(() => {
      leafletMap.invalidateSize();
      if (marker) leafletMap.setView(marker.getLatLng(), leafletMap.getZoom());
    }, 150);
  }
}
function cerrarOverlay(id) { document.getElementById(id).classList.remove('open'); }
document.querySelectorAll('.overlay').forEach(ov=>{
  ov.addEventListener('click',e=>{if(e.target===ov) ov.classList.remove('open');});
});
function actualizarMapa(lat,lng) {
  document.getElementById('gps-pending').style.display='none';
  document.getElementById('mapa-container').style.display='block';
  if (!mapaIniciado) return;
  if (!marker) {
    marker=L.marker([lat,lng],{icon:L.divIcon({html:'<div style="font-size:26px">🐕</div>',className:'',iconAnchor:[13,26]})}).addTo(leafletMap);
    marker.bindPopup('<b>Tu perro</b>').openPopup();
  } else marker.setLatLng([lat,lng]);
  leafletMap.setView([lat,lng],16);
}


// ── Mascotas ──────────────────────────────────────────────
let mascotaActual = null;

async function cargarMascotas() {
  try {
    const res  = await fetch('api_mascotas.php');
    const data = await res.json();
    if (!data.ok || !data.mascotas.length) {
      document.getElementById('sel-mascota').innerHTML = '<option value="">Sin mascotas — agrega una</option>';
      return;
    }
    const sel = document.getElementById('sel-mascota');
    sel.innerHTML = data.mascotas.map(m =>
      `<option value="${m.id}">${m.nombre} (${m.raza||'sin raza'})</option>`
    ).join('');
    mascotaActual = data.mascotas[0].id;
    sel.addEventListener('change', () => { mascotaActual = sel.value; poll(); });
  } catch(e) { console.warn('mascotas', e); }
}

function abrirAgregarMascota() {
  document.getElementById('overlay-mascota').classList.add('open');
}

async function guardarMascota() {
  const nombre = document.getElementById('m-nombre').value.trim();
  const raza   = document.getElementById('m-raza').value.trim();
  const edad   = parseInt(document.getElementById('m-edad').value) || 0;
  const mac    = document.getElementById('m-mac').value.trim().toUpperCase();
  const vet    = document.getElementById('m-vet').value.trim();
  const msg    = document.getElementById('msg-mascota');

  if (!nombre) { showMsg(msg, 'Escribe el nombre de la mascota.', 'error'); return; }

  showMsg(msg, 'Guardando…', 'ok');
  try {
    const res  = await fetch('api_mascotas.php', {
      method:'POST',
      headers:{'Content-Type':'application/json'},
      body: JSON.stringify({nombre, raza, edad, mac_esp32: mac, vet_email: vet})
    });
    let data;
    const text = await res.text();
    try { data = JSON.parse(text); }
    catch(e) { showMsg(msg, '❌ Respuesta inesperada del servidor: ' + text.substring(0,120), 'error'); return; }
    if (data.ok) {
      showMsg(msg, '✅ Mascota guardada correctamente.', 'ok');
      setTimeout(() => { cerrarOverlay('overlay-mascota'); 
// ── Machine Learning ──────────────────────────────────────
async function ejecutarML() {
  if (!mascotaActual) { alert('Selecciona una mascota primero'); return; }
  const btn = document.getElementById('btn-ml');
  btn.disabled = true;
  btn.textContent = '⏳ Analizando…';
  document.getElementById('ml-contenido').innerHTML = `
    <div class="ml-no-data"><div class="icon">⏳</div><div>Analizando patrones históricos…</div></div>`;
  try {
    const res  = await fetch('ml_api.php?mascota_id=' + mascotaActual);
    const data = await res.json();
    renderML(data);
  } catch(e) {
    document.getElementById('ml-contenido').innerHTML =
      '<div class="ml-no-data"><div class="icon">❌</div><div>Error al ejecutar el análisis. Verifica que Python esté instalado.</div></div>';
  }
  btn.disabled = false;
  btn.innerHTML = '🔍 Analizar patrones';
}

function renderML(data) {
  const el = document.getElementById('ml-contenido');
  if (!data.ok) { el.innerHTML = `<div class="ml-no-data"><div class="icon">❌</div><div>${data.error}</div></div>`; return; }
  if (!data.suficientes_datos) {
    el.innerHTML = `<div class="ml-no-data"><div class="icon">📊</div><div>${data.mensaje}</div></div>`; return;
  }

  const t = data.temperatura;
  const e = data.estres;
  const a = data.actividad;

  el.innerHTML = `
  <div class="ml-grid">

    <!-- Temperatura -->
    <div class="ml-card">
      <div class="ml-card-header">
        <div class="ml-card-icon">🌡️</div>
        <div><div class="ml-card-label">Tendencia</div><div class="ml-card-title">Temperatura corporal</div></div>
      </div>
      ${t.disponible ? `
      <div class="ml-result bg-${t.color}">
        <div class="ml-result-icon">${t.icono}</div>
        <div class="ml-result-text">
          <div class="ml-result-val c-${t.color}">${t.tendencia}</div>
          <div>${t.interpretacion}</div>
        </div>
      </div>
      <div class="ml-bar-wrap">
        <div class="ml-bar-label"><span>Media: ${t.media}°C</span><span>Proyección: ${t.proyeccion}°C</span></div>
        ${t.anomalias > 0 ? `<div style="font-size:.75rem;color:#ffd93d;">⚠️ ${t.anomalias} lecturas atípicas detectadas</div>` : ''}
      </div>` : `<div class="ml-no-data"><div>${t.razon}</div></div>`}
    </div>

    <!-- Estrés -->
    <div class="ml-card">
      <div class="ml-card-header">
        <div class="ml-card-icon">🧠</div>
        <div><div class="ml-card-label">Patrón crónico</div><div class="ml-card-title">Nivel de estrés</div></div>
      </div>
      ${e.disponible ? `
      <div class="ml-result bg-${e.color}">
        <div class="ml-result-icon">${e.icono}</div>
        <div class="ml-result-text">
          <div class="ml-result-val c-${e.color}">${e.nivel}</div>
          <div>${e.mensaje}</div>
        </div>
      </div>
      <div class="ml-bar-wrap">
        <div class="ml-bar-label"><span>Sin estrés</span><span>${e.pct_sin_estres}%</span></div>
        <div class="ml-bar"><div class="ml-bar-fill" style="width:${e.pct_sin_estres}%;background:#00d4aa"></div></div>
        <div class="ml-bar-label"><span>Leve</span><span>${e.pct_leve}%</span></div>
        <div class="ml-bar"><div class="ml-bar-fill" style="width:${e.pct_leve}%;background:#ffd93d"></div></div>
        <div class="ml-bar-label"><span>Alto/Severo</span><span>${e.pct_alto}%</span></div>
        <div class="ml-bar"><div class="ml-bar-fill" style="width:${e.pct_alto}%;background:#ff6b6b"></div></div>
      </div>
      <div style="font-size:.78rem;color:var(--muted);margin-top:8px;">Tendencia reciente: <strong>${e.tendencia_reciente}</strong></div>
      ` : `<div class="ml-no-data"><div>${e.razon}</div></div>`}
    </div>

    <!-- Actividad -->
    <div class="ml-card">
      <div class="ml-card-header">
        <div class="ml-card-icon">🐾</div>
        <div><div class="ml-card-label">Hábitos</div><div class="ml-card-title">Patrones de actividad</div></div>
      </div>
      ${a.disponible ? `
      <div class="ml-result bg-${a.color}">
        <div class="ml-result-icon">${a.icono}</div>
        <div class="ml-result-text">
          <div class="ml-result-val c-${a.color}">${a.estado}</div>
          <div>${a.mensaje}</div>
        </div>
      </div>
      <div class="ml-bar-wrap">
        <div class="ml-bar-label"><span>😴 Reposo</span><span>${a.pct_reposo}%</span></div>
        <div class="ml-bar"><div class="ml-bar-fill" style="width:${a.pct_reposo}%;background:#74b9ff"></div></div>
        <div class="ml-bar-label"><span>🚶 Moderada</span><span>${a.pct_moderada}%</span></div>
        <div class="ml-bar"><div class="ml-bar-fill" style="width:${a.pct_moderada}%;background:#ffd93d"></div></div>
        <div class="ml-bar-label"><span>🏃 Alta</span><span>${a.pct_alta}%</span></div>
        <div class="ml-bar"><div class="ml-bar-fill" style="width:${a.pct_alta}%;background:#00d4aa"></div></div>
      </div>` : `<div class="ml-no-data"><div>${a.razon}</div></div>`}
    </div>

  </div>
  <div class="ml-meta">📊 ${data.total_registros} registros analizados · Análisis: ${data.fecha_analisis} · Último entrenamiento: ${data.ultimo_entrenamiento}</div>
  `;
}

cargarMascotas(); }, 1200);
    } else {
      showMsg(msg, '❌ ' + (data.error || 'Error desconocido'), 'error');
    }
  } catch(e) {
    showMsg(msg, '❌ Error de red: ' + e.message, 'error');
  }
}

function showMsg(el, text, type) {
  el.textContent = text;
  el.style.display = 'block';
  el.style.background = type==='ok' ? 'rgba(0,212,170,.12)' : 'rgba(255,107,107,.12)';
  el.style.color      = type==='ok' ? '#00d4aa' : '#ff6b6b';
  el.style.border     = '1px solid ' + (type==='ok' ? 'rgba(0,212,170,.3)' : 'rgba(255,107,107,.3)');
}


// ── Machine Learning ──────────────────────────────────────
async function ejecutarML() {
  if (!mascotaActual) { alert('Selecciona una mascota primero'); return; }
  const btn = document.getElementById('btn-ml');
  btn.disabled = true;
  btn.textContent = '⏳ Analizando…';
  document.getElementById('ml-contenido').innerHTML = `
    <div class="ml-no-data"><div class="icon">⏳</div><div>Analizando patrones históricos…</div></div>`;
  try {
    const res  = await fetch('ml_api.php?mascota_id=' + mascotaActual);
    const data = await res.json();
    renderML(data);
  } catch(e) {
    document.getElementById('ml-contenido').innerHTML =
      '<div class="ml-no-data"><div class="icon">❌</div><div>Error al ejecutar el análisis. Verifica que Python esté instalado.</div></div>';
  }
  btn.disabled = false;
  btn.innerHTML = '🔍 Analizar patrones';
}

function renderML(data) {
  const el = document.getElementById('ml-contenido');
  if (!data.ok) { el.innerHTML = `<div class="ml-no-data"><div class="icon">❌</div><div>${data.error}</div></div>`; return; }
  if (!data.suficientes_datos) {
    el.innerHTML = `<div class="ml-no-data"><div class="icon">📊</div><div>${data.mensaje}</div></div>`; return;
  }

  const t = data.temperatura;
  const e = data.estres;
  const a = data.actividad;

  el.innerHTML = `
  <div class="ml-grid">

    <!-- Temperatura -->
    <div class="ml-card">
      <div class="ml-card-header">
        <div class="ml-card-icon">🌡️</div>
        <div><div class="ml-card-label">Tendencia</div><div class="ml-card-title">Temperatura corporal</div></div>
      </div>
      ${t.disponible ? `
      <div class="ml-result bg-${t.color}">
        <div class="ml-result-icon">${t.icono}</div>
        <div class="ml-result-text">
          <div class="ml-result-val c-${t.color}">${t.tendencia}</div>
          <div>${t.interpretacion}</div>
        </div>
      </div>
      <div class="ml-bar-wrap">
        <div class="ml-bar-label"><span>Media: ${t.media}°C</span><span>Proyección: ${t.proyeccion}°C</span></div>
        ${t.anomalias > 0 ? `<div style="font-size:.75rem;color:#ffd93d;">⚠️ ${t.anomalias} lecturas atípicas detectadas</div>` : ''}
      </div>` : `<div class="ml-no-data"><div>${t.razon}</div></div>`}
    </div>

    <!-- Estrés -->
    <div class="ml-card">
      <div class="ml-card-header">
        <div class="ml-card-icon">🧠</div>
        <div><div class="ml-card-label">Patrón crónico</div><div class="ml-card-title">Nivel de estrés</div></div>
      </div>
      ${e.disponible ? `
      <div class="ml-result bg-${e.color}">
        <div class="ml-result-icon">${e.icono}</div>
        <div class="ml-result-text">
          <div class="ml-result-val c-${e.color}">${e.nivel}</div>
          <div>${e.mensaje}</div>
        </div>
      </div>
      <div class="ml-bar-wrap">
        <div class="ml-bar-label"><span>Sin estrés</span><span>${e.pct_sin_estres}%</span></div>
        <div class="ml-bar"><div class="ml-bar-fill" style="width:${e.pct_sin_estres}%;background:#00d4aa"></div></div>
        <div class="ml-bar-label"><span>Leve</span><span>${e.pct_leve}%</span></div>
        <div class="ml-bar"><div class="ml-bar-fill" style="width:${e.pct_leve}%;background:#ffd93d"></div></div>
        <div class="ml-bar-label"><span>Alto/Severo</span><span>${e.pct_alto}%</span></div>
        <div class="ml-bar"><div class="ml-bar-fill" style="width:${e.pct_alto}%;background:#ff6b6b"></div></div>
      </div>
      <div style="font-size:.78rem;color:var(--muted);margin-top:8px;">Tendencia reciente: <strong>${e.tendencia_reciente}</strong></div>
      ` : `<div class="ml-no-data"><div>${e.razon}</div></div>`}
    </div>

    <!-- Actividad -->
    <div class="ml-card">
      <div class="ml-card-header">
        <div class="ml-card-icon">🐾</div>
        <div><div class="ml-card-label">Hábitos</div><div class="ml-card-title">Patrones de actividad</div></div>
      </div>
      ${a.disponible ? `
      <div class="ml-result bg-${a.color}">
        <div class="ml-result-icon">${a.icono}</div>
        <div class="ml-result-text">
          <div class="ml-result-val c-${a.color}">${a.estado}</div>
          <div>${a.mensaje}</div>
        </div>
      </div>
      <div class="ml-bar-wrap">
        <div class="ml-bar-label"><span>😴 Reposo</span><span>${a.pct_reposo}%</span></div>
        <div class="ml-bar"><div class="ml-bar-fill" style="width:${a.pct_reposo}%;background:#74b9ff"></div></div>
        <div class="ml-bar-label"><span>🚶 Moderada</span><span>${a.pct_moderada}%</span></div>
        <div class="ml-bar"><div class="ml-bar-fill" style="width:${a.pct_moderada}%;background:#ffd93d"></div></div>
        <div class="ml-bar-label"><span>🏃 Alta</span><span>${a.pct_alta}%</span></div>
        <div class="ml-bar"><div class="ml-bar-fill" style="width:${a.pct_alta}%;background:#00d4aa"></div></div>
      </div>` : `<div class="ml-no-data"><div>${a.razon}</div></div>`}
    </div>

  </div>
  <div class="ml-meta">📊 ${data.total_registros} registros analizados · Análisis: ${data.fecha_analisis} · Último entrenamiento: ${data.ultimo_entrenamiento}</div>
  `;
}

cargarMascotas();

drawGauge('gaugeTemp',0,'#7a80a0','#2e3354');
drawGauge('gaugeEstres',0,'#7a80a0','#2e3354');
cargarConfig();
poll();
setInterval(poll,POLL_MS);
</script>
</body>
</html>
