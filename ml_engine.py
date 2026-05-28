#!/usr/bin/env python3
# -*- coding: utf-8 -*-
import sys
import io
sys.stdout = io.TextIOWrapper(sys.stdout.buffer, encoding='utf-8')
sys.stderr = io.TextIOWrapper(sys.stderr.buffer, encoding='utf-8')
# ================================================================
#  ml_engine.py  -  Motor de Machine Learning para CollarPerro
#  Uso:
#    python ml_engine.py analizar  <mascota_id>   -> JSON con resultados
#    python ml_engine.py entrenar  <mascota_id>   -> entrena y guarda modelos
#    python ml_engine.py entrenar  all            -> entrena todas las mascotas
# ================================================================

import os
import json
import warnings
import math
warnings.filterwarnings('ignore')

import numpy  as np
import pandas as pd
import pickle

# DB config - debe coincidir con auth.php
DB_CONFIG = {
    'host':   'localhost',
    'user':   'root',
    'passwd': '',
    'db':     'sensores_db',
    'charset':'utf8mb4'
}

MODELS_DIR = os.path.join(os.path.dirname(__file__), 'ml_models')
os.makedirs(MODELS_DIR, exist_ok=True)

MIN_ROWS_TRAIN   = 50   # minimo de registros para entrenar
MIN_ROWS_ANALYZE = 20   # minimo para analizar


# -- Conexion a MySQL ------------------------------------------
def get_connection():
    try:
        import pymysql
        return pymysql.connect(**DB_CONFIG)
    except ImportError:
        sys.exit(json.dumps({"ok": False, "error": "Instala pymysql: pip install pymysql"}))
    except Exception as e:
        sys.exit(json.dumps({"ok": False, "error": f"DB error: {e}"}))


# -- Cargar datos de una mascota -------------------------------
def cargar_datos(mascota_id, limite=2000):
    conn = get_connection()
    query = """
        SELECT temperatura, bpm, actividad, estres, estado_temp, estado_pulso,
               lat, lng, fecha_hora
        FROM sensores
        WHERE mascota_id = %s AND fecha_hora IS NOT NULL
        ORDER BY fecha_hora DESC
        LIMIT %s
    """
    df = pd.read_sql(query, conn, params=(mascota_id, limite))
    conn.close()
    if df.empty:
        return df
    df['fecha_hora']  = pd.to_datetime(df['fecha_hora'])
    df['temperatura'] = pd.to_numeric(df['temperatura'], errors='coerce')
    df['bpm']         = pd.to_numeric(df['bpm'], errors='coerce')
    df = df.dropna(subset=['temperatura'])
    df = df.sort_values('fecha_hora')
    return df


# ============================================================
#  MODELO 1: Tendencia de temperatura (regresion lineal)
# ============================================================
def analizar_tendencia_temp(df):
    temps = df['temperatura'].dropna().values
    if len(temps) < MIN_ROWS_ANALYZE:
        return {"disponible": False, "razon": "Datos insuficientes"}

    # Regresion lineal simple sobre los ultimos N puntos
    x = np.arange(len(temps)).reshape(-1, 1)
    from sklearn.linear_model import LinearRegression
    model = LinearRegression().fit(x, temps)
    pendiente = model.coef_[0]

    # Media y desviacion estandar
    media  = float(np.mean(temps))
    std    = float(np.std(temps))
    ultimo = float(temps[-1])

    # Proyeccion a 24h (asumiendo 1 lectura cada 5s -> ~17280/dia, tomamos los ultimos 288 = 24min)
    n_futuro = min(len(temps) // 2, 100)
    x_fut    = np.array([[len(temps) + n_futuro]])
    proyeccion = float(model.predict(x_fut)[0])

    # Clasificar tendencia
    if abs(pendiente) < 0.0005:
        tendencia = "ESTABLE"
        icono     = "OK"
        color     = "verde"
    elif pendiente > 0:
        tendencia = "ASCENDENTE"
        icono     = "SUBE"
        color     = "naranja" if pendiente > 0.002 else "amarillo"
    else:
        tendencia = "DESCENDENTE"
        icono     = "BAJA"
        color     = "azul"

    # Deteccion de anomalias (valores fuera de media +/- 2*std)
    anomalias = int(np.sum(np.abs(temps - media) > 2 * std))

    return {
        "disponible":  True,
        "tendencia":   tendencia,
        "icono":       icono,
        "color":       color,
        "pendiente":   round(float(pendiente), 6),
        "media":       round(media, 2),
        "std":         round(std, 2),
        "ultimo":      round(ultimo, 2),
        "proyeccion":  round(proyeccion, 2),
        "anomalias":   anomalias,
        "total_datos": len(temps),
        "interpretacion": _interpretar_temp(tendencia, media, proyeccion, anomalias)
    }


def _interpretar_temp(tendencia, media, proyeccion, anomalias):
    msgs = []
    if tendencia == "ASCENDENTE":
        msgs.append("La temperatura muestra una tendencia sostenida al alza.")
        if proyeccion > 39.5:
            msgs.append("ADVERTENCIA: Si la tendencia continua, podria llegar a zona de fiebre.")
    elif tendencia == "DESCENDENTE":
        msgs.append("La temperatura muestra una tendencia a la baja.")
        if proyeccion < 36.5:
            msgs.append("ADVERTENCIA: Riesgo de hipotermia si la tendencia continua.")
    else:
        msgs.append("La temperatura se mantiene estable dentro del rango normal.")
    if anomalias > 3:
        msgs.append(f"Se detectaron {anomalias} lecturas atipicas fuera del rango habitual.")
    return " ".join(msgs)


# ============================================================
#  MODELO 2: Estres cronico (clasificacion + frecuencia)
# ============================================================
def analizar_estres_cronico(df):
    if len(df) < MIN_ROWS_ANALYZE:
        return {"disponible": False, "razon": "Datos insuficientes"}

    estres_col = df['estres'].fillna('').str.upper()
    total      = len(estres_col)

    sin_estres = estres_col.str.contains('SIN ESTR', na=False).sum()
    leve       = estres_col.str.contains('LEVE', na=False).sum()
    alto       = estres_col.str.contains('ALTO|SEVERO|CRITICO|CRITICO', na=False).sum()
    sin_datos  = estres_col.str.contains('SIN DATOS', na=False).sum()

    validos    = total - sin_datos
    if validos == 0:
        return {"disponible": False, "razon": "Sin lecturas de pulso validas"}

    pct_sin    = round(sin_estres / validos * 100, 1)
    pct_leve   = round(leve       / validos * 100, 1)
    pct_alto   = round(alto       / validos * 100, 1)

    # Clasificar nivel cronico
    if pct_alto >= 30:
        nivel   = "CRONICO ALTO"
        icono   = "CRITICO"
        color   = "rojo"
        mensaje = f"El {pct_alto}% de las lecturas muestran estres alto o severo. Se recomienda evaluacion veterinaria."
    elif pct_alto >= 15 or pct_leve >= 40:
        nivel   = "ESTRES RECURRENTE"
        icono   = "ALERTA"
        color   = "naranja"
        mensaje = f"Patron de estres recurrente detectado ({pct_leve}% leve, {pct_alto}% alto). Monitorear de cerca."
    elif pct_leve >= 20:
        nivel   = "ESTRES LEVE OCASIONAL"
        icono   = "MODERADO"
        color   = "amarillo"
        mensaje = f"Episodios de estres leve ocasionales ({pct_leve}%). Dentro de rangos aceptables."
    else:
        nivel   = "SIN ESTRES CRONICO"
        icono   = "BIEN"
        color   = "verde"
        mensaje = f"La mascota muestra niveles de estres saludables ({pct_sin}% sin estres)."

    # Tendencia reciente (ultimas 24h vs anterior)
    mitad = len(df) // 2
    reciente  = df.tail(mitad)['estres'].fillna('').str.upper()
    anterior  = df.head(mitad)['estres'].fillna('').str.upper()
    alto_rec  = reciente.str.contains('ALTO|SEVERO', na=False).mean()
    alto_ant  = anterior.str.contains('ALTO|SEVERO', na=False).mean()

    if alto_rec > alto_ant * 1.3:
        tendencia_reciente = "EMPEORANDO"
    elif alto_rec < alto_ant * 0.7:
        tendencia_reciente = "MEJORANDO"
    else:
        tendencia_reciente = "ESTABLE"

    return {
        "disponible":         True,
        "nivel":              nivel,
        "icono":              icono,
        "color":              color,
        "mensaje":            mensaje,
        "pct_sin_estres":     pct_sin,
        "pct_leve":           pct_leve,
        "pct_alto":           pct_alto,
        "tendencia_reciente": tendencia_reciente,
        "total_validos":      int(validos)
    }


# ============================================================
#  MODELO 3: Cambios en habitos de actividad (anomalias)
# ============================================================
def analizar_habitos_actividad(df):
    if len(df) < MIN_ROWS_ANALYZE:
        return {"disponible": False, "razon": "Datos insuficientes"}

    act_col = df['actividad'].fillna('').str.upper()
    total   = len(act_col)

    reposo   = (act_col == 'REPOSO').sum()
    moderada = act_col.str.contains('MODERADA', na=False).sum()
    alta     = act_col.str.contains('ALTA', na=False).sum()

    pct_reposo   = round(reposo   / total * 100, 1)
    pct_moderada = round(moderada / total * 100, 1)
    pct_alta     = round(alta     / total * 100, 1)

    # Comparar primera mitad vs segunda mitad
    mitad    = len(df) // 2
    rec_act  = df.tail(mitad)['actividad'].fillna('').str.upper()
    ant_act  = df.head(mitad)['actividad'].fillna('').str.upper()

    alta_rec = (rec_act.str.contains('ALTA', na=False)).mean()
    alta_ant = (ant_act.str.contains('ALTA', na=False)).mean()
    rep_rec  = (rec_act == 'REPOSO').mean()
    rep_ant  = (ant_act == 'REPOSO').mean()

    alertas = []
    if rep_rec > rep_ant * 1.5 and rep_rec > 0.7:
        alertas.append("ALERTA: Aumento significativo en tiempo de reposo. Posible letargo.")
    if alta_rec > alta_ant * 1.5 and alta_rec > 0.5:
        alertas.append("ALERTA: Aumento en actividad alta. Verificar si es normal.")
    if pct_reposo > 85:
        alertas.append("ALERTA: La mascota pasa mas del 85% del tiempo en reposo.")

    if not alertas:
        estado  = "HABITUAL"
        icono   = "OK"
        color   = "verde"
        mensaje = "Los habitos de actividad son consistentes y normales."
    elif len(alertas) == 1:
        estado  = "CAMBIO LEVE"
        icono   = "MODERADO"
        color   = "amarillo"
        mensaje = alertas[0]
    else:
        estado  = "CAMBIO SIGNIFICATIVO"
        icono   = "ALERTA"
        color   = "naranja"
        mensaje = " | ".join(alertas)

    return {
        "disponible":    True,
        "estado":        estado,
        "icono":         icono,
        "color":         color,
        "mensaje":       mensaje,
        "pct_reposo":    pct_reposo,
        "pct_moderada":  pct_moderada,
        "pct_alta":      pct_alta,
        "alertas":       alertas,
        "total_datos":   int(total)
    }


# ============================================================
#  ENTRENAR Y GUARDAR MODELOS (para el reentrenamiento diario)
# ============================================================
def entrenar(mascota_id):
    df = cargar_datos(mascota_id, limite=5000)
    if len(df) < MIN_ROWS_TRAIN:
        return {"ok": False, "error": f"Solo {len(df)} registros. Se necesitan al menos {MIN_ROWS_TRAIN}."}

    from sklearn.linear_model import LinearRegression
    from sklearn.preprocessing import StandardScaler

    resultados = {}

    # Modelo temperatura
    temps = df['temperatura'].dropna().values
    x     = np.arange(len(temps)).reshape(-1, 1)
    reg   = LinearRegression().fit(x, temps)
    model_path = os.path.join(MODELS_DIR, f'temp_model_{mascota_id}.pkl')
    with open(model_path, 'wb') as f:
        pickle.dump({'model': reg, 'n': len(temps), 'media': np.mean(temps), 'std': np.std(temps)}, f)
    resultados['temperatura'] = f"Entrenado con {len(temps)} muestras"

    # Guardar metadatos del entrenamiento
    meta = {
        "mascota_id":     mascota_id,
        "total_registros": len(df),
        "fecha_entrenamiento": str(pd.Timestamp.now()),
        "modelos": resultados
    }
    meta_path = os.path.join(MODELS_DIR, f'meta_{mascota_id}.json')
    with open(meta_path, 'w') as f:
        json.dump(meta, f, indent=2)

    return {"ok": True, "mascota_id": mascota_id, "meta": meta}


# ============================================================
#  ANALIZAR - punto de entrada principal
# ============================================================
def analizar(mascota_id):
    df = cargar_datos(mascota_id)

    if df.empty or len(df) < MIN_ROWS_ANALYZE:
        return {
            "ok": True,
            "mascota_id": mascota_id,
            "total_registros": len(df),
            "suficientes_datos": False,
            "mensaje": f"Se necesitan al menos {MIN_ROWS_ANALYZE} registros para el analisis. Actualmente: {len(df)}."
        }

    # Cargar metadatos si existen
    meta_path = os.path.join(MODELS_DIR, f'meta_{mascota_id}.json')
    meta = None
    if os.path.exists(meta_path):
        with open(meta_path) as f:
            meta = json.load(f)

    resultado = {
        "ok":               True,
        "mascota_id":       mascota_id,
        "total_registros":  len(df),
        "suficientes_datos": True,
        "fecha_analisis":   str(pd.Timestamp.now().strftime('%d/%m/%Y %H:%M')),
        "ultimo_entrenamiento": meta['fecha_entrenamiento'][:16] if meta else "Pendiente",
        "temperatura":      analizar_tendencia_temp(df),
        "estres":           analizar_estres_cronico(df),
        "actividad":        analizar_habitos_actividad(df)
    }

    return resultado


# ============================================================
#  MAIN
# ============================================================
if __name__ == '__main__':
    if len(sys.argv) < 3:
        print(json.dumps({"ok": False, "error": "Uso: python ml_engine.py [analizar|entrenar] <mascota_id|all>"}))
        sys.exit(1)

    accion     = sys.argv[1].lower()
    mascota_id = sys.argv[2]

    if accion == 'analizar':
        print(json.dumps(analizar(int(mascota_id)), ensure_ascii=False))

    elif accion == 'entrenar':
        if mascota_id == 'all':
            # Obtener todas las mascotas con datos
            conn = get_connection()
            cur  = conn.cursor()
            cur.execute("SELECT DISTINCT mascota_id FROM sensores WHERE mascota_id IS NOT NULL")
            ids  = [r[0] for r in cur.fetchall()]
            conn.close()
            resultados = [entrenar(mid) for mid in ids]
            print(json.dumps({"ok": True, "entrenados": resultados}, ensure_ascii=False))
        else:
            print(json.dumps(entrenar(int(mascota_id)), ensure_ascii=False))
    else:
        print(json.dumps({"ok": False, "error": f"Accion desconocida: {accion}"}))
