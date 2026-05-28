<?php
require_once 'auth.php';
requireLogin('veterinario');
$usuario = usuarioActual();
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>CollarPerro — Panel Veterinario</title>
<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.1/chart.umd.min.js"></script>
<style>
  *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
  :root {
    --bg:#0f1117; --surface:#1a1d27; --card:#21253a; --border:#2e3354;
    --text:#e8eaf0; --muted:#7a80a0; --radius:14px;
    --accent1:#6c63ff; --accent2:#00d4aa; --accent3:#ff6b6b;
    --shadow:0 4px 24px rgba(0,0,0,.4);
  }
  body { font-family:'Segoe UI',system-ui,sans-serif; background:var(--bg); color:var(--text); min-height:100vh; padding:24px 16px 40px; }

  header { display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:12px; margin-bottom:28px; }
  .logo { display:flex; align-items:center; gap:12px; }
  .logo-icon { width:44px; height:44px; background:linear-gradient(135deg,#00b894,#00d4aa); border-radius:12px; display:flex; align-items:center; justify-content:center; font-size:22px; }
  h1 { font-size:1.4rem; font-weight:700; }
  h1 span { color:var(--accent2); }
  .badge-vet { background:rgba(0,212,170,.12); border:1px solid rgba(0,212,170,.3); color:var(--accent2); padding:4px 12px; border-radius:99px; font-size:.75rem; font-weight:600; }

  .top-right { display:flex; flex-direction:column; align-items:flex-end; gap:6px; }
  .user-info { font-size:.82rem; color:var(--muted); }
  .btn-logout { font-size:.78rem; color:var(--accent3); text-decoration:none; background:rgba(255,107,107,.1); padding:5px 12px; border-radius:8px; border:1px solid rgba(255,107,107,.25); }

  .mascota-selector { display:flex; align-items:center; gap:12px; margin-bottom:28px; flex-wrap:wrap; }
  .mascota-selector select { background:var(--card); border:1px solid var(--border); color:var(--text); padding:10px 16px; border-radius:10px; font-size:.9rem; cursor:pointer; min-width:220px; outline:none; }
  .mascota-info { background:var(--card); border:1px solid var(--border); border-radius:10px; padding:10px 16px; font-size:.85rem; color:var(--muted); }
  .mascota-info strong { color:var(--text); }

  .stats-grid { display:grid; grid-template-columns:repeat(auto-fit,minmax(150px,1fr)); gap:16px; margin-bottom:24px; }
  .stat-card { background:var(--card); border:1px solid var(--border); border-radius:var(--radius); padding:18px; box-shadow:var(--shadow); }
  .stat-label { font-size:.7rem; text-transform:uppercase; letter-spacing:.8px; color:var(--muted); margin-bottom:8px; }
  .stat-val { font-size:1.7rem; font-weight:700; line-height:1; margin-bottom:4px; }
  .stat-sub { font-size:.78rem; color:var(--muted); }

  .section-title { font-size:.82rem; font-weight:600; text-transform:uppercase; letter-spacing:.8px; color:var(--muted); margin-bottom:14px; }

  .charts-grid { display:grid; grid-template-columns:repeat(auto-fit,minmax(300px,1fr)); gap:16px; margin-bottom:24px; }
  .chart-card { background:var(--card); border:1px solid var(--border); border-radius:var(--radius); padding:20px; box-shadow:var(--shadow); }

  .table-card { background:var(--card); border:1px solid var(--border); border-radius:var(--radius); padding:20px; box-shadow:var(--shadow); margin-bottom:24px; }
  .table-controls { display:flex; gap:10px; align-items:center; margin-bottom:16px; flex-wrap:wrap; }
  .table-controls select, .table-controls input[type=date] { background:#12151e; border:1px solid var(--border); color:var(--text); padding:8px 12px; border-radius:8px; font-size:.82rem; outline:none; }
  .tbl-wrap { overflow-x:auto; }
  table { width:100%; border-collapse:collapse; font-size:.82rem; }
  th { text-align:left; padding:8px 12px; border-bottom:1px solid var(--border); color:var(--muted); font-weight:500; white-space:nowrap; }
  td { padding:9px 12px; border-bottom:1px solid rgba(46,51,84,.5); white-space:nowrap; }
  tr:last-child td { border-bottom:none; }
  tr:hover td { background:rgba(108,99,255,.06); }
  .badge { padding:3px 10px; border-radius:99px; font-size:.72rem; font-weight:600; }
  .b-ok    { background:rgba(0,212,170,.12); color:#00d4aa; }
  .b-warn  { background:rgba(255,217,61,.12); color:#ffd93d; }
  .b-alert { background:rgba(255,107,107,.12); color:#ff6b6b; }

  .pagination { display:flex; gap:8px; align-items:center; margin-top:14px; justify-content:flex-end; }
  .btn-page { background:var(--card); border:1px solid var(--border); color:var(--text); padding:6px 14px; border-radius:8px; cursor:pointer; font-size:.82rem; transition:background .2s; }
  .btn-page:hover { background:#2e3354; }
  .btn-page.active { background:var(--accent1); border-color:var(--accent1); color:#fff; }

  .empty-state { text-align:center; padding:48px 24px; color:var(--muted); }
  .empty-state .icon { font-size:3rem; opacity:.35; margin-bottom:14px; }

  .alerta-banner { background:rgba(255,107,107,.1); border:1px solid rgba(255,107,107,.3); border-radius:10px; padding:12px 16px; margin-bottom:20px; font-size:.85rem; color:#ff9f9f; display:none; }
  .alerta-banner.show { display:block; }

  .ml-section { margin-top:28px; margin-bottom:24px; }
  .ml-header { display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:10px; margin-bottom:16px; }
  .ml-title { font-size:.9rem; font-weight:700; text-transform:uppercase; letter-spacing:.8px; color:var(--muted); display:flex; align-items:center; gap:8px; }
  .ml-badge { font-size:.72rem; padding:3px 10px; border-radius:99px; background:rgba(0,212,170,.12); color:var(--accent2); border:1px solid rgba(0,212,170,.25); }
  .btn-ml { display:flex; align-items:center; gap:8px; padding:9px 18px; border-radius:9px; border:none; background:linear-gradient(135deg,#00b894,#00d4aa); color:#0f1117; font-size:.85rem; font-weight:700; cursor:pointer; transition:all .2s; }
  .btn-ml:hover { filter:brightness(1.1); transform:translateY(-1px); }
  .btn-ml:disabled { opacity:.5; cursor:not-allowed; transform:none; }
  .ml-grid { display:grid; grid-template-columns:repeat(auto-fit,minmax(300px,1fr)); gap:16px; }
  .ml-card { background:var(--card); border:1px solid var(--border); border-radius:14px; padding:20px; }
  .ml-card-header { display:flex; align-items:center; gap:10px; margin-bottom:14px; }
  .ml-card-icon { font-size:1.5rem; }
  .ml-card-label { font-size:.72rem; text-transform:uppercase; color:var(--muted); letter-spacing:.7px; }
  .ml-card-title { font-size:.95rem; font-weight:700; }
  .ml-result { display:flex; align-items:flex-start; gap:10px; padding:12px 14px; border-radius:10px; margin-bottom:10px; }
  .ml-result-icon { font-size:1.3rem; flex-shrink:0; }
  .ml-result-val { font-size:1rem; font-weight:700; margin-bottom:2px; }
  .ml-result-text { font-size:.82rem; line-height:1.5; color:var(--muted); }
  .ml-stat-row { display:flex; justify-content:space-between; align-items:center; padding:6px 0; border-bottom:1px solid rgba(46,51,84,.4); font-size:.82rem; }
  .ml-stat-row:last-child { border-bottom:none; }
  .ml-stat-label { color:var(--muted); }
  .ml-stat-val { font-weight:600; }
  .ml-bar-wrap { margin-top:8px; }
  .ml-bar-label { display:flex; justify-content:space-between; font-size:.72rem; color:var(--muted); margin-bottom:3px; }
  .ml-bar { height:5px; border-radius:99px; background:var(--border); overflow:hidden; margin-bottom:5px; }
  .ml-bar-fill { height:100%; border-radius:99px; transition:width .6s; }
  .ml-no-data { text-align:center; padding:28px; color:var(--muted); font-size:.85rem; }
  .ml-meta { font-size:.72rem; color:var(--muted); margin-top:14px; padding-top:10px; border-top:1px solid var(--border); }
  .btn-reentrenar { padding:6px 14px; border-radius:8px; border:1px solid rgba(0,212,170,.3); background:transparent; color:var(--accent2); font-size:.78rem; cursor:pointer; transition:all .2s; }
  .btn-reentrenar:hover { background:rgba(0,212,170,.1); }
  .c-verde{color:#00d4aa;} .bg-verde{background:rgba(0,212,170,.1);border:1px solid rgba(0,212,170,.2);}
  .c-amarillo{color:#ffd93d;} .bg-amarillo{background:rgba(255,217,61,.1);border:1px solid rgba(255,217,61,.2);}
  .c-naranja{color:#ff9f43;} .bg-naranja{background:rgba(255,159,67,.1);border:1px solid rgba(255,159,67,.2);}
  .c-rojo{color:#ff6b6b;} .bg-rojo{background:rgba(255,107,107,.1);border:1px solid rgba(255,107,107,.2);}
  .c-azul{color:#74b9ff;} .bg-azul{background:rgba(116,185,255,.1);border:1px solid rgba(116,185,255,.2);}
</style>
</head>
<body>

<header>
  <div class="logo">
    <div class="logo-icon">🩺</div>
    <div>
      <div style="display:flex;align-items:center;gap:10px;">
        <h1>Collar<span>Perro</span></h1>
        <span class="badge-vet">Veterinario</span>
      </div>
      <div style="font-size:.78rem;color:var(--muted);">Panel de seguimiento clínico</div>
    </div>
  </div>
  <div class="top-right">
    <div class="user-info">🩺 <?= htmlspecialchars($usuario['nombre']) ?></div>
    <a href="logout.php" class="btn-logout">Cerrar sesión</a>
  </div>
</header>

<!-- ALERTA -->
<div class="alerta-banner" id="alerta-banner"></div>

<!-- SELECTOR MASCOTA -->
<div class="mascota-selector">
  <select id="sel-mascota" onchange="seleccionarMascota(this.value)">
    <option value="">Cargando mascotas…</option>
  </select>
  <div class="mascota-info" id="info-mascota">Selecciona una mascota</div>
</div>

<!-- STATS -->
<div class="stats-grid">
  <div class="stat-card">
    <div class="stat-label">Última temp.</div>
    <div class="stat-val" id="s-temp" style="color:#ff6b6b;">--</div>
    <div class="stat-sub">°C</div>
  </div>
  <div class="stat-card">
    <div class="stat-label">Último BPM</div>
    <div class="stat-val" id="s-bpm" style="color:#74b9ff;">--</div>
    <div class="stat-sub">pulsaciones/min</div>
  </div>
  <div class="stat-card">
    <div class="stat-label">Estado temp.</div>
    <div class="stat-val" id="s-est-temp" style="font-size:1rem;margin-top:6px;">--</div>
    <div class="stat-sub">&nbsp;</div>
  </div>
  <div class="stat-card">
    <div class="stat-label">Estado pulso</div>
    <div class="stat-val" id="s-est-pulso" style="font-size:1rem;margin-top:6px;">--</div>
    <div class="stat-sub">&nbsp;</div>
  </div>
  <div class="stat-card">
    <div class="stat-label">Nivel estrés</div>
    <div class="stat-val" id="s-estres" style="font-size:1rem;margin-top:6px;">--</div>
    <div class="stat-sub">&nbsp;</div>
  </div>
  <div class="stat-card">
    <div class="stat-label">Última lectura</div>
    <div class="stat-val" id="s-fecha" style="font-size:.95rem;margin-top:6px;">--</div>
    <div class="stat-sub">&nbsp;</div>
  </div>
</div>

<!-- GRÁFICAS -->
<div class="charts-grid">
  <div class="chart-card">
    <div class="section-title">🌡️ Temperatura — últimas lecturas</div>
    <div style="position:relative;height:200px;">
      <canvas id="chartTemp" role="img" aria-label="Temperatura en el tiempo">Historial temperatura.</canvas>
    </div>
  </div>
  <div class="chart-card">
    <div class="section-title">❤️ Pulso (BPM) — últimas lecturas</div>
    <div style="position:relative;height:200px;">
      <canvas id="chartBPM" role="img" aria-label="BPM en el tiempo">Historial pulso.</canvas>
    </div>
  </div>
</div>

<!-- HISTORIAL COMPLETO -->
<div class="table-card">
  <div class="section-title">📋 Historial completo de datos</div>
  <div class="table-controls">
    <select id="filtro-estado" onchange="filtrarTabla()">
      <option value="">Todos los estados</option>
      <option value="NORMAL">Normal</option>
      <option value="FIEBRE">Fiebre</option>
      <option value="HIPOTERMIA">Hipotermia</option>
      <option value="ESTRES">Estrés</option>
      <option value="TAQUICARDIA">Taquicardia</option>
    </select>
    <input type="date" id="filtro-fecha" onchange="filtrarTabla()">
    <span id="total-registros" style="font-size:.8rem;color:var(--muted);margin-left:auto;"></span>
  </div>
  <div class="tbl-wrap">
    <table>
      <thead><tr>
        <th>#</th><th>Fecha y hora</th><th>Temp (°C)</th><th>BPM</th>
        <th>Actividad</th><th>Estado temp.</th><th>Estado pulso</th><th>Estrés</th>
      </tr></thead>
      <tbody id="vet-tabla"></tbody>
    </table>
  </div>
  <div class="pagination" id="paginacion"></div>
</div>

<script>
const POLL_MS  = 5000;
const POR_PAG  = 20;
let mascotaId  = null;
let todosRegistros = [];
let registrosFiltrados = [];
let paginaActual = 1;

// ── Charts ──────────────────────────────────────────────────
const mkChart = (id, color, label) => new Chart(document.getElementById(id), {
  type:'line',
  data:{labels:[],datasets:[{label,data:[],borderColor:color,backgroundColor:color+'22',borderWidth:2,pointRadius:2,tension:0.4,fill:true}]},
  options:{responsive:true,maintainAspectRatio:false,animation:{duration:300},
    scales:{x:{ticks:{color:'#7a80a0',maxTicksLimit:6,maxRotation:0},grid:{color:'#2e3354'}},
            y:{ticks:{color:'#7a80a0'},grid:{color:'#2e3354'}}},
    plugins:{legend:{display:false}}}
});
const chartTemp = mkChart('chartTemp','#ff6b6b','Temperatura');
const chartBPM  = mkChart('chartBPM', '#74b9ff','BPM');

function setChart(c,l,d){c.data.labels=l.slice();c.data.datasets[0].data=d.slice();c.update();}

// ── Mascotas ─────────────────────────────────────────────────
async function cargarMascotas() {
  const res  = await fetch('api_mascotas.php');
  const data = await res.json();
  const sel  = document.getElementById('sel-mascota');
  if (!data.ok || !data.mascotas.length) {
    sel.innerHTML = '<option value="">Sin mascotas asignadas</option>';
    return;
  }
  sel.innerHTML = data.mascotas.map(m =>
    `<option value="${m.id}" data-info="${m.nombre} | ${m.raza||'—'} | ${m.edad||'—'} años | Dueño: ${m.dueno_nombre}">${m.nombre} (${m.dueno_nombre})</option>`
  ).join('');
  seleccionarMascota(data.mascotas[0].id);
}

function seleccionarMascota(id) {
  mascotaId = id;
  const sel = document.getElementById('sel-mascota');
  const opt = sel.options[sel.selectedIndex];
  document.getElementById('info-mascota').innerHTML =
    `<strong>${opt.dataset.info || 'Sin info'}</strong>`;
  cargarHistorial();
}

// ── Historial completo (para tabla y gráficas) ───────────────
async function cargarHistorial() {
  if (!mascotaId) return;
  try {
    const res  = await fetch(`leer.php?mascota_id=${mascotaId}&limite=200&_=`+Date.now());
    const data = await res.json();
    if (!data.ok) return;

    const d = data.latest || {};
    document.getElementById('s-temp').textContent      = d.temperatura ? parseFloat(d.temperatura).toFixed(1) : '--';
    document.getElementById('s-bpm').textContent       = d.bpm         ?? '--';
    document.getElementById('s-est-temp').textContent  = d.estado_temp  ?? '--';
    document.getElementById('s-est-pulso').textContent = d.estado_pulso ?? '--';
    document.getElementById('s-estres').textContent    = d.estres       ?? '--';
    document.getElementById('s-fecha').textContent     = d.fecha_hora   ?? '--';

    const h = data.history || [];
    todosRegistros = [...h].reverse();

    const vT = h.filter(r=>!isNaN(parseFloat(r.temperatura)));
    const vB = h.filter(r=>parseInt(r.bpm)>0);
    setChart(chartTemp, vT.map(r=>r.hora||r.fecha_hora), vT.map(r=>parseFloat(r.temperatura)));
    setChart(chartBPM,  vB.map(r=>r.hora||r.fecha_hora), vB.map(r=>parseInt(r.bpm)));

    // Alerta si hay fiebre o taquicardia
    const alerta = ['FIEBRE','HIPOTERMIA','TAQUICARDIA','URGENTE','SEVERO'].some(k =>
      (d.estado_temp||'').includes(k) || (d.estado_pulso||'').includes(k) || (d.estres||'').includes(k)
    );
    const banner = document.getElementById('alerta-banner');
    if (alerta) {
      banner.textContent = `⚠️ Alerta clínica detectada en ${document.getElementById('sel-mascota').options[document.getElementById('sel-mascota').selectedIndex]?.text}: ${d.estado_temp} | ${d.estado_pulso} | ${d.estres}`;
      banner.classList.add('show');
    } else {
      banner.classList.remove('show');
    }

    filtrarTabla();
  } catch(e) { console.warn(e); }
}

// ── Filtrar y paginar ────────────────────────────────────────
function filtrarTabla() {
  const filtEst  = document.getElementById('filtro-estado').value.toUpperCase();
  const filtFech = document.getElementById('filtro-fecha').value;
  registrosFiltrados = todosRegistros.filter(r => {
    const matchEst = !filtEst || (r.estado_temp||'').includes(filtEst) ||
                     (r.estado_pulso||'').includes(filtEst) || (r.estres||'').includes(filtEst);
    const matchFech = !filtFech || (r.fecha_hora||r.hora||'').startsWith(filtFech);
    return matchEst && matchFech;
  });
  paginaActual = 1;
  renderTabla();
}

function renderTabla() {
  const inicio = (paginaActual-1)*POR_PAG;
  const pagina = registrosFiltrados.slice(inicio, inicio+POR_PAG);
  const tbody  = document.getElementById('vet-tabla');

  document.getElementById('total-registros').textContent =
    `${registrosFiltrados.length} registros`;

  if (!pagina.length) {
    tbody.innerHTML = '<tr><td colspan="8" style="text-align:center;padding:32px;color:var(--muted);">Sin datos para este filtro</td></tr>';
    document.getElementById('paginacion').innerHTML = '';
    return;
  }

  tbody.innerHTML = pagina.map((r,i) => {
    const estBadge = badgeClass(r.estado_temp);
    const pulBadge = badgeClass(r.estado_pulso);
    const esBadge  = badgeClass(r.estres);
    return `<tr>
      <td style="color:var(--muted)">${inicio+i+1}</td>
      <td>${r.fecha_hora||r.hora||'—'}</td>
      <td style="color:#ff6b6b;font-weight:600">${parseFloat(r.temperatura).toFixed(1)}</td>
      <td style="color:#74b9ff;font-weight:600">${r.bpm||'—'}</td>
      <td>${r.actividad||'—'}</td>
      <td><span class="badge ${estBadge}">${r.estado_temp||'—'}</span></td>
      <td><span class="badge ${pulBadge}">${r.estado_pulso||'—'}</span></td>
      <td><span class="badge ${esBadge}">${r.estres||'—'}</span></td>
    </tr>`;
  }).join('');

  // Paginación
  const totalPags = Math.ceil(registrosFiltrados.length / POR_PAG);
  let pgHtml = '';
  for (let p=1; p<=totalPags; p++) {
    if (p===1 || p===totalPags || Math.abs(p-paginaActual)<=2) {
      pgHtml += `<button class="btn-page ${p===paginaActual?'active':''}" onclick="irPagina(${p})">${p}</button>`;
    } else if (Math.abs(p-paginaActual)===3) {
      pgHtml += `<span style="color:var(--muted)">…</span>`;
    }
  }
  document.getElementById('paginacion').innerHTML = pgHtml;
}

function irPagina(p) { paginaActual = p; renderTabla(); }

function badgeClass(val) {
  const v = (val||'').toUpperCase();
  if (['URGENTE','PELIGRO','SEVERO','FIEBRE','TAQUICARDIA','CRITICO'].some(k=>v.includes(k))) return 'b-alert';
  if (['ALTO','ELEVADO','LEVE','BAJA','BAJA','MODERADO'].some(k=>v.includes(k))) return 'b-warn';
  return 'b-ok';
}

// ── Arrancar ─────────────────────────────────────────────────
cargarMascotas();
setInterval(cargarHistorial, POLL_MS);
</script>

<!-- ── SECCIÓN ML VETERINARIO ────────────────────────────── -->
<div class="ml-section">
  <div class="ml-header">
    <div class="ml-title">
      🧠 Análisis clínico de patrones
      <span class="ml-badge">IA Local</span>
    </div>
    <div style="display:flex;gap:10px;flex-wrap:wrap;">
      <button class="btn-reentrenar" onclick="reentrenarModelos()" id="btn-reent">🔄 Reentrenar modelos</button>
      <button class="btn-ml" id="btn-ml-vet" onclick="ejecutarMLVet()">🔬 Análisis detallado</button>
    </div>
  </div>
  <div id="ml-vet-contenido">
    <div class="ml-no-data">
      <div style="font-size:2rem;opacity:.3;margin-bottom:10px;">🧬</div>
      <div>Selecciona una mascota y presiona "Análisis detallado"<br>para ver el reporte clínico de patrones.</div>
    </div>
  </div>
</div>

<script>
async function ejecutarMLVet() {
  if (!mascotaId) { alert('Selecciona una mascota'); return; }
  const btn = document.getElementById('btn-ml-vet');
  btn.disabled = true; btn.textContent = '⏳ Analizando…';
  document.getElementById('ml-vet-contenido').innerHTML =
    '<div class="ml-no-data"><div style="font-size:2rem;opacity:.3;margin-bottom:10px;">⏳</div><div>Procesando historial clínico…</div></div>';
  try {
    const res  = await fetch('ml_api.php?mascota_id=' + mascotaId);
    const data = await res.json();
    renderMLVet(data);
  } catch(e) {
    document.getElementById('ml-vet-contenido').innerHTML =
      '<div class="ml-no-data"><div>❌ Error al ejecutar el análisis.</div></div>';
  }
  btn.disabled = false; btn.innerHTML = '🔬 Análisis detallado';
}

async function reentrenarModelos() {
  if (!mascotaId) { alert('Selecciona una mascota'); return; }
  const btn = document.getElementById('btn-reent');
  btn.disabled = true; btn.textContent = '⏳ Entrenando…';
  try {
    const res  = await fetch(`ml_entrenar.php?clave=collar2025ml&mascota_id=${mascotaId}`);
    const data = await res.json();
    btn.textContent = data.ok ? '✅ Listo' : '❌ Error';
    setTimeout(() => { btn.disabled=false; btn.textContent='🔄 Reentrenar modelos'; }, 3000);
  } catch(e) {
    btn.disabled=false; btn.textContent='🔄 Reentrenar modelos';
  }
}

function renderMLVet(data) {
  const el = document.getElementById('ml-vet-contenido');
  if (!data.ok) { el.innerHTML = `<div class="ml-no-data">❌ ${data.error}</div>`; return; }
  if (!data.suficientes_datos) {
    el.innerHTML = `<div class="ml-no-data"><div style="font-size:2rem;opacity:.3;margin-bottom:10px;">📊</div><div>${data.mensaje}</div></div>`; return;
  }
  const iconMap = {'OK':'✅','SUBE':'⬆️','BAJA':'⬇️','CRITICO':'🚨','ALERTA':'⚠️','MODERADO':'🔶','BIEN':'💚','LEVE':'🔶'};
  function mapIcon(i) { return iconMap[i] || i; }
  if (data.temperatura?.icono) data.temperatura.icono = mapIcon(data.temperatura.icono);
  if (data.estres?.icono)      data.estres.icono      = mapIcon(data.estres.icono);
  if (data.actividad?.icono)   data.actividad.icono   = mapIcon(data.actividad.icono);
  const t = data.temperatura, e = data.estres, a = data.actividad;
  el.innerHTML = `
  <div class="ml-grid">

    <div class="ml-card">
      <div class="ml-card-header"><div class="ml-card-icon">🌡️</div>
        <div><div class="ml-card-label">Análisis clínico</div><div class="ml-card-title">Tendencia térmica</div></div></div>
      ${t.disponible ? `
      <div class="ml-result bg-${t.color}">
        <div class="ml-result-icon">${t.icono}</div>
        <div><div class="ml-result-val c-${t.color}">${t.tendencia}</div><div class="ml-result-text">${t.interpretacion}</div></div>
      </div>
      <div class="ml-stat-row"><span class="ml-stat-label">Media histórica</span><span class="ml-stat-val">${t.media}°C</span></div>
      <div class="ml-stat-row"><span class="ml-stat-label">Desviación estándar</span><span class="ml-stat-val">±${t.std}°C</span></div>
      <div class="ml-stat-row"><span class="ml-stat-label">Pendiente de tendencia</span><span class="ml-stat-val">${t.pendiente > 0 ? '+':''}${t.pendiente}°C/lectura</span></div>
      <div class="ml-stat-row"><span class="ml-stat-label">Proyección próxima</span><span class="ml-stat-val c-${t.color}">${t.proyeccion}°C</span></div>
      <div class="ml-stat-row"><span class="ml-stat-label">Lecturas atípicas</span><span class="ml-stat-val ${t.anomalias>3?'c-rojo':''}">${t.anomalias}</span></div>
      ` : `<div class="ml-no-data">${t.razon}</div>`}
    </div>

    <div class="ml-card">
      <div class="ml-card-header"><div class="ml-card-icon">🧠</div>
        <div><div class="ml-card-label">Análisis clínico</div><div class="ml-card-title">Estrés crónico</div></div></div>
      ${e.disponible ? `
      <div class="ml-result bg-${e.color}">
        <div class="ml-result-icon">${e.icono}</div>
        <div><div class="ml-result-val c-${e.color}">${e.nivel}</div><div class="ml-result-text">${e.mensaje}</div></div>
      </div>
      <div class="ml-bar-wrap">
        <div class="ml-bar-label"><span>Sin estrés</span><span>${e.pct_sin_estres}%</span></div>
        <div class="ml-bar"><div class="ml-bar-fill" style="width:${e.pct_sin_estres}%;background:#00d4aa"></div></div>
        <div class="ml-bar-label"><span>Estrés leve</span><span>${e.pct_leve}%</span></div>
        <div class="ml-bar"><div class="ml-bar-fill" style="width:${e.pct_leve}%;background:#ffd93d"></div></div>
        <div class="ml-bar-label"><span>Estrés alto/severo</span><span>${e.pct_alto}%</span></div>
        <div class="ml-bar"><div class="ml-bar-fill" style="width:${e.pct_alto}%;background:#ff6b6b"></div></div>
      </div>
      <div class="ml-stat-row" style="margin-top:10px;"><span class="ml-stat-label">Tendencia reciente</span>
        <span class="ml-stat-val ${e.tendencia_reciente==='EMPEORANDO'?'c-rojo':e.tendencia_reciente==='MEJORANDO'?'c-verde':''}">
        ${e.tendencia_reciente}</span></div>
      <div class="ml-stat-row"><span class="ml-stat-label">Lecturas válidas</span><span class="ml-stat-val">${e.total_validos}</span></div>
      ` : `<div class="ml-no-data">${e.razon}</div>`}
    </div>

    <div class="ml-card">
      <div class="ml-card-header"><div class="ml-card-icon">🐾</div>
        <div><div class="ml-card-label">Análisis clínico</div><div class="ml-card-title">Hábitos de actividad</div></div></div>
      ${a.disponible ? `
      <div class="ml-result bg-${a.color}">
        <div class="ml-result-icon">${a.icono}</div>
        <div><div class="ml-result-val c-${a.color}">${a.estado}</div><div class="ml-result-text">${a.mensaje}</div></div>
      </div>
      <div class="ml-bar-wrap">
        <div class="ml-bar-label"><span>😴 Reposo</span><span>${a.pct_reposo}%</span></div>
        <div class="ml-bar"><div class="ml-bar-fill" style="width:${a.pct_reposo}%;background:#74b9ff"></div></div>
        <div class="ml-bar-label"><span>🚶 Moderada</span><span>${a.pct_moderada}%</span></div>
        <div class="ml-bar"><div class="ml-bar-fill" style="width:${a.pct_moderada}%;background:#ffd93d"></div></div>
        <div class="ml-bar-label"><span>🏃 Alta</span><span>${a.pct_alta}%</span></div>
        <div class="ml-bar"><div class="ml-bar-fill" style="width:${a.pct_alta}%;background:#00d4aa"></div></div>
      </div>
      <div class="ml-stat-row" style="margin-top:10px;"><span class="ml-stat-label">Total registros</span><span class="ml-stat-val">${a.total_datos}</span></div>
      ` : `<div class="ml-no-data">${a.razon}</div>`}
    </div>

  </div>
  <div class="ml-meta">📊 ${data.total_registros} registros · Análisis: ${data.fecha_analisis} · Último entrenamiento: ${data.ultimo_entrenamiento}</div>
  `;
}
</script>
</body>
</html>
