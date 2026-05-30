#!/usr/bin/env python3
"""
================================================================
Script: setup_github_project.py
Proyecto: Sistema de Gestión Calificación Académica UCM
Descripción: Crea automáticamente en GitHub:
  - Labels por épica
  - Milestones por Sprint
  - 26 Issues (HUs) con label, milestone y body completo
================================================================
INSTRUCCIONES:
  1. Instala dependencias: pip install requests
  2. Rellena las variables GITHUB_TOKEN, OWNER y REPO
  3. Ejecuta: python3 setup_github_project.py
================================================================
"""

import os
import requests
import json
import time

# ── CONFIGURACIÓN ─────────────────────────────────────────────
GITHUB_TOKEN = os.environ.get("GITHUB_TOKEN", "")  # exporta: export GITHUB_TOKEN=ghp_...
OWNER        = "Tban1"    # Tu usuario u organización
REPO         = "apa-ucm"      # Nombre del repositorio
# ──────────────────────────────────────────────────────────────

BASE = f"https://api.github.com/repos/{OWNER}/{REPO}"
HEADERS = {
    "Authorization": f"Bearer {GITHUB_TOKEN}",
    "Accept": "application/vnd.github+json",
    "X-GitHub-Api-Version": "2022-11-28"
}

def req(method, url, data=None):
    r = requests.request(method, url, headers=HEADERS, json=data)
    if r.status_code not in (200, 201, 204, 422):
        print(f"  ⚠️  {r.status_code}: {r.text[:120]}")
    return r

# ════════════════════════════════════════════════════════════════
# 1. LABELS
# ════════════════════════════════════════════════════════════════
LABELS = [
    {"name": "E1: Autenticación",        "color": "1d3557", "description": "Autenticación y gestión de roles"},
    {"name": "E2: Calendarización",      "color": "457b9d", "description": "Calendarización y nóminas CCDA"},
    {"name": "E3: Secretario",           "color": "2a9d8f", "description": "Panel del secretario de facultad"},
    {"name": "E4: Evidencias",           "color": "52b5a8", "description": "Carga de evidencias por académico"},
    {"name": "E5: Evaluación CCA",       "color": "e9c46a", "description": "Evaluación y calificación CCA"},
    {"name": "E6: Apelaciones",          "color": "f4a261", "description": "Módulo de apelaciones"},
    {"name": "E7: Cierre",               "color": "e76f51", "description": "Cierre formal y actas"},
    {"name": "E8: Notificaciones",       "color": "2d6a4f", "description": "Notificaciones automáticas"},
    {"name": "E9: Reportes CCDA",        "color": "74c69d", "description": "Reportes y acceso CCDA"},
    {"name": "Sprint 1",                 "color": "d0e8ff", "description": "Sprint 1 — Semanas 5-6"},
    {"name": "Sprint 2",                 "color": "b8d4f0", "description": "Sprint 2 — Semanas 7-8"},
    {"name": "Sprint 3",                 "color": "a0c0e0", "description": "Sprint 3 — Semanas 9-10"},
    {"name": "Sprint 4",                 "color": "88acd0", "description": "Sprint 4 — Semanas 11-12"},
    {"name": "Sprint 5",                 "color": "7098c0", "description": "Sprint 5 — Semanas 13-14"},
    {"name": "historia-de-usuario",      "color": "0075ca", "description": "Historia de usuario"},
    {"name": "pruebas",                  "color": "e4e669", "description": "Pruebas funcionales"},
]

def create_labels():
    print("\n📌 Creando labels...")
    for label in LABELS:
        r = req("POST", f"{BASE}/labels", label)
        status = "✓" if r.status_code == 201 else "↩ ya existe"
        print(f"  {status}  {label['name']}")
        time.sleep(0.3)

# ════════════════════════════════════════════════════════════════
# 2. MILESTONES
# ════════════════════════════════════════════════════════════════
MILESTONES = [
    {"title": "Sprint 1",        "description": "E1: Autenticación y Roles — HU-001 a HU-003"},
    {"title": "Sprint 2",        "description": "E2+E3: Calendarización y Secretario (inicio) — HU-004 a HU-008"},
    {"title": "Sprint 3",        "description": "E3+E4: Secretario (cierre) y Evidencias — HU-009 a HU-013"},
    {"title": "Sprint 4",        "description": "E5+E6: Evaluación CCA y Apelaciones — HU-014 a HU-018"},
    {"title": "Sprint 5",        "description": "E7+E8+E9: Cierre, Notificaciones y Reportes — HU-019 a HU-026"},
    {"title": "Pruebas",         "description": "Pruebas funcionales e integración — Semanas 15-16"},
    {"title": "Documentación",   "description": "Manual de usuario y memoria — Semanas 17-18"},
]

milestone_ids = {}

def create_milestones():
    print("\n🏁 Creando milestones...")
    for ms in MILESTONES:
        r = req("POST", f"{BASE}/milestones", ms)
        if r.status_code == 201:
            milestone_ids[ms["title"]] = r.json()["number"]
            print(f"  ✓  {ms['title']} (#{r.json()['number']})")
        else:
            # Si ya existe, obtenerlo
            existing = req("GET", f"{BASE}/milestones?state=open&per_page=50")
            for m in existing.json():
                if m["title"] == ms["title"]:
                    milestone_ids[ms["title"]] = m["number"]
                    print(f"  ↩ ya existe  {ms['title']} (#{m['number']})")
        time.sleep(0.3)

# ════════════════════════════════════════════════════════════════
# 3. ISSUES (HUs)
# ════════════════════════════════════════════════════════════════
def hu_body(codigo, epica, detalle, criterios):
    crit_str = "\n".join([f"- [ ] {c}" for c in criterios])
    return f"""## {codigo} — {epica}

### Detalle
> {detalle}

### Criterios de Aceptación
{crit_str}

### Definición de Hecho
- [ ] Criterios de aceptación validados
- [ ] Diagrama de caso de uso (DCU) elaborado
- [ ] Código en rama feature mergeado a main
- [ ] Sprint Review aprobada con profesor guía
"""

ISSUES = [
    # ── E1: Autenticación (Sprint 1) ──────────────────────────
    {
        "title": "HU-001: Inicio de sesión con credenciales institucionales",
        "labels": ["E1: Autenticación", "Sprint 1", "historia-de-usuario"],
        "milestone": "Sprint 1",
        "body": hu_body(
            "HU-001", "E1: Autenticación y Gestión de Roles",
            "Como actor institucional, necesito iniciar sesión con mis credenciales para acceder al sistema de forma segura.",
            [
                "El sistema valida usuario y contraseña correctamente.",
                "Si las credenciales son incorrectas, muestra mensaje de error.",
                "La sesión expira tras un período de inactividad definido.",
                "El acceso redirige al panel correspondiente al rol del usuario."
            ]
        )
    },
    {
        "title": "HU-002: Control de acceso diferenciado por rol",
        "labels": ["E1: Autenticación", "Sprint 1", "historia-de-usuario"],
        "milestone": "Sprint 1",
        "body": hu_body(
            "HU-002", "E1: Autenticación y Gestión de Roles",
            "Como administrador del sistema, necesito que cada usuario acceda únicamente a las funciones correspondientes a su rol, para garantizar la seguridad y privacidad de la información.",
            [
                "El sistema reconoce los roles: académico, secretario, CCA, CCDA.",
                "Cada rol visualiza solo el menú y funciones que le corresponden.",
                "El acceso a rutas no autorizadas retorna error 403.",
                "El middleware bloquea solicitudes sin autenticación activa."
            ]
        )
    },
    {
        "title": "HU-003: Cierre de sesión seguro",
        "labels": ["E1: Autenticación", "Sprint 1", "historia-de-usuario"],
        "milestone": "Sprint 1",
        "body": hu_body(
            "HU-003", "E1: Autenticación y Gestión de Roles",
            "Como usuario autenticado, necesito cerrar sesión de forma segura para proteger mi información en equipos compartidos.",
            [
                "El botón de cierre de sesión está visible en todo momento.",
                "Al cerrar sesión, el token de autenticación es invalidado.",
                "El sistema redirige al formulario de inicio de sesión.",
                "No es posible volver a la sesión cerrada con el botón atrás."
            ]
        )
    },
    # ── E2: Calendarización (Sprint 2) ────────────────────────
    {
        "title": "HU-004: Registro de período académico y plazos",
        "labels": ["E2: Calendarización", "Sprint 2", "historia-de-usuario"],
        "milestone": "Sprint 2",
        "body": hu_body(
            "HU-004", "E2: Calendarización y Nóminas (CCDA)",
            "Como analista CCDA, necesito registrar el período académico y los plazos del proceso para que todos los actores sean notificados automáticamente del inicio del proceso.",
            [
                "El sistema permite ingresar fecha de inicio y término del proceso.",
                "Los plazos quedan registrados y son visibles para todos los actores.",
                "Al guardar el período, se envían notificaciones automáticas.",
                "No se puede registrar un período con fechas inconsistentes."
            ]
        )
    },
    {
        "title": "HU-005: Carga de nómina de académicos evaluados",
        "labels": ["E2: Calendarización", "Sprint 2", "historia-de-usuario"],
        "milestone": "Sprint 2",
        "body": hu_body(
            "HU-005", "E2: Calendarización y Nóminas (CCDA)",
            "Como analista CCDA, necesito cargar la nómina de académicos a evaluar por facultad para que el secretario pueda gestionar los expedientes correspondientes.",
            [
                "El sistema permite cargar la nómina por facultad.",
                "Cada académico queda asociado a su facultad y período.",
                "El secretario puede visualizar la nómina de su facultad.",
                "No se permiten académicos duplicados en la misma nómina."
            ]
        )
    },
    {
        "title": "HU-006: Registro de caso especial (licencia médica)",
        "labels": ["E2: Calendarización", "Sprint 2", "historia-de-usuario"],
        "milestone": "Sprint 2",
        "body": hu_body(
            "HU-006", "E2: Calendarización y Nóminas (CCDA)",
            "Como analista CCDA, necesito registrar casos especiales como licencias médicas para que el académico afectado quede excluido o con plazo extendido en el proceso.",
            [
                "El sistema permite marcar a un académico con caso especial.",
                "El caso especial queda registrado con fecha y motivo.",
                "El académico con caso especial no recibe bloqueo automático de plazo.",
                "El secretario visualiza el estado especial en el panel de expedientes."
            ]
        )
    },
    # ── E3: Secretario inicio (Sprint 2) ──────────────────────
    {
        "title": "HU-007: Visualización centralizada del estado de expedientes",
        "labels": ["E3: Secretario", "Sprint 2", "historia-de-usuario"],
        "milestone": "Sprint 2",
        "body": hu_body(
            "HU-007", "E3: Panel del Secretario de Facultad",
            "Como secretario de facultad, necesito visualizar el estado de avance de cada expediente para hacer seguimiento sin revisar carpetas manualmente.",
            [
                "El panel muestra todos los académicos de la nómina con su estado.",
                "Los estados posibles son: pendiente, en revisión, completo, cerrado.",
                "El panel se actualiza en tiempo real al recibir nuevas evidencias.",
                "El secretario puede filtrar por estado y buscar por nombre."
            ]
        )
    },
    {
        "title": "HU-008: Configuración de plazos internos por facultad",
        "labels": ["E3: Secretario", "Sprint 2", "historia-de-usuario"],
        "milestone": "Sprint 2",
        "body": hu_body(
            "HU-008", "E3: Panel del Secretario de Facultad",
            "Como secretario de facultad, necesito configurar los plazos internos de entrega de evidencias para que los académicos sean notificados y el sistema bloquee la carga al vencer el plazo.",
            [
                "El secretario puede definir fecha límite de entrega de evidencias.",
                "El plazo queda visible para los académicos de la facultad.",
                "Al vencer el plazo, el sistema bloquea la carga de nuevas evidencias.",
                "El secretario puede extender el plazo si es necesario."
            ]
        )
    },
    # ── E3: Secretario cierre (Sprint 3) ──────────────────────
    {
        "title": "HU-009: Validación de documentación del expediente",
        "labels": ["E3: Secretario", "Sprint 3", "historia-de-usuario"],
        "milestone": "Sprint 3",
        "body": hu_body(
            "HU-009", "E3: Panel del Secretario de Facultad",
            "Como secretario de facultad, necesito validar la documentación entregada por cada académico para certificar que el expediente está completo antes de enviarlo a la CCA.",
            [
                "El secretario puede revisar los archivos cargados por categoría.",
                "Puede marcar el expediente como completo o con observaciones.",
                "Si hay observaciones, el académico es notificado automáticamente.",
                "El expediente completo queda disponible para la CCA."
            ]
        )
    },
    {
        "title": "HU-010: Cierre formal de recepción de evidencias",
        "labels": ["E3: Secretario", "Sprint 3", "historia-de-usuario"],
        "milestone": "Sprint 3",
        "body": hu_body(
            "HU-010", "E3: Panel del Secretario de Facultad",
            "Como secretario de facultad, necesito registrar formalmente el cierre de la recepción de evidencias para que quede constancia del término del período de entrega.",
            [
                "El secretario puede ejecutar el cierre formal desde el panel.",
                "El cierre registra fecha, hora y usuario que lo ejecutó.",
                "Tras el cierre, no se aceptan nuevas evidencias salvo apelación.",
                "Todos los actores son notificados del cierre."
            ]
        )
    },
    # ── E4: Evidencias (Sprint 3) ─────────────────────────────
    {
        "title": "HU-011: Carga de archivos por categoría APA",
        "labels": ["E4: Evidencias", "Sprint 3", "historia-de-usuario"],
        "milestone": "Sprint 3",
        "body": hu_body(
            "HU-011", "E4: Carga de Evidencias (Académico)",
            "Como académico evaluado, necesito cargar mis evidencias organizadas por las cinco categorías APA para acreditar el cumplimiento de mis compromisos de desempeño.",
            [
                "El académico visualiza las cinco categorías APA habilitadas.",
                "Puede subir uno o más archivos por categoría.",
                "El sistema registra fecha, hora y autor de cada carga.",
                "Se aceptan formatos PDF, JPG, PNG y DOCX."
            ]
        )
    },
    {
        "title": "HU-012: Bloqueo automático de carga al vencer el plazo",
        "labels": ["E4: Evidencias", "Sprint 3", "historia-de-usuario"],
        "milestone": "Sprint 3",
        "body": hu_body(
            "HU-012", "E4: Carga de Evidencias (Académico)",
            "Como secretario de facultad, necesito que el sistema bloquee automáticamente la carga de evidencias al vencer el plazo definido para garantizar el cumplimiento de los plazos.",
            [
                "Al vencer el plazo, el formulario de carga queda deshabilitado.",
                "El académico visualiza un mensaje indicando que el plazo venció.",
                "El sistema registra la fecha y hora exacta del bloqueo.",
                "Solo una apelación aprobada puede reabrir la carga."
            ]
        )
    },
    {
        "title": "HU-013: Visualización del estado del propio expediente",
        "labels": ["E4: Evidencias", "Sprint 3", "historia-de-usuario"],
        "milestone": "Sprint 3",
        "body": hu_body(
            "HU-013", "E4: Carga de Evidencias (Académico)",
            "Como académico evaluado, necesito visualizar el estado de mi expediente para saber qué evidencias he entregado y cuáles están pendientes.",
            [
                "El académico ve un resumen de sus evidencias por categoría.",
                "Visualiza el estado de cada categoría: pendiente, entregada, observada.",
                "Puede descargar los archivos que ha subido previamente.",
                "El plazo vigente es visible en todo momento."
            ]
        )
    },
    # ── E5: Evaluación CCA (Sprint 4) ─────────────────────────
    {
        "title": "HU-014: Revisión de expediente completo por miembro CCA",
        "labels": ["E5: Evaluación CCA", "Sprint 4", "historia-de-usuario"],
        "milestone": "Sprint 4",
        "body": hu_body(
            "HU-014", "E5: Evaluación CCA",
            "Como miembro de la CCA, necesito revisar el expediente completo de cada académico para evaluar sus evidencias por categoría y registrar mi calificación individual.",
            [
                "El formulario de evaluación se precarga automáticamente con los datos del académico (nombre, RUT, facultad, categoría, línea de desarrollo, horas por semestre, % tiempo asignado por área APA y nota anterior). Solo la CCA llena la nota por área y retroalimentación.",
                "Puede registrar su evaluación por cada categoría APA.",
                "La evaluación individual queda registrada con fecha y autor.",
                "No puede modificar la evaluación de otro miembro de la comisión."
            ]
        )
    },
    {
        "title": "HU-015: Determinación de calificación final",
        "labels": ["E5: Evaluación CCA", "Sprint 4", "historia-de-usuario"],
        "milestone": "Sprint 4",
        "body": hu_body(
            "HU-015", "E5: Evaluación CCA",
            "Como comisión CCA, necesito determinar y registrar la calificación final del académico para formalizar el resultado del proceso de evaluación.",
            [
                "La nota final se calcula con la fórmula nota_final = min(Σ(%T_i × N_i) / 100, 5.0), donde %T_i es el porcentaje de tiempo asignado a cada área APA y N_i la nota registrada por el miembro CCA en esa área.",
                "El concepto se determina automáticamente según la escala UCM: Excelente (4.5–5.0), Muy Bueno (4.0–4.4), Bueno (3.5–3.9), Regular (2.7–3.4), Deficiente (<2.7).",
                "La calificación final queda registrada en el sistema con fecha y período.",
                "El sistema notifica automáticamente al académico evaluado con el resultado."
            ]
        )
    },
    {
        "title": "HU-016: Generación de informe de calificación",
        "labels": ["E5: Evaluación CCA", "Sprint 4", "historia-de-usuario"],
        "milestone": "Sprint 4",
        "body": hu_body(
            "HU-016", "E5: Evaluación CCA",
            "Como comisión CCA, necesito generar el informe formal de calificación para enviárselo al académico evaluado y registrarlo en el sistema.",
            [
                "El sistema genera el informe con los datos del académico y calificación.",
                "El informe puede ser descargado en formato PDF.",
                "El informe queda registrado en el sistema con fecha de emisión.",
                "El académico recibe notificación con el informe adjunto."
            ]
        )
    },
    # ── E6: Apelaciones (Sprint 4) ────────────────────────────
    {
        "title": "HU-017: Presentación de apelación por el académico",
        "labels": ["E6: Apelaciones", "Sprint 4", "historia-de-usuario"],
        "milestone": "Sprint 4",
        "body": hu_body(
            "HU-017", "E6: Módulo de Apelaciones",
            "Como académico evaluado, necesito presentar una apelación con antecedentes adicionales para solicitar la revisión de mi calificación dentro del plazo establecido.",
            [
                "El académico puede presentar apelación solo dentro del plazo habilitado.",
                "Puede adjuntar documentos adicionales como respaldo.",
                "La apelación queda registrada con fecha, hora y motivo.",
                "El secretario y la CCA son notificados automáticamente."
            ]
        )
    },
    {
        "title": "HU-018: Resolución de apelación por la CCA",
        "labels": ["E6: Apelaciones", "Sprint 4", "historia-de-usuario"],
        "milestone": "Sprint 4",
        "body": hu_body(
            "HU-018", "E6: Módulo de Apelaciones",
            "Como comisión CCA, necesito revisar y resolver la apelación presentada para registrar la calificación definitiva del académico.",
            [
                "La CCA accede a los antecedentes de apelación desde su panel.",
                "Puede aceptar o rechazar la apelación con resolución registrada.",
                "La resolución queda registrada con fecha y autor.",
                "El académico es notificado del resultado de su apelación."
            ]
        )
    },
    # ── E7: Cierre (Sprint 5) ─────────────────────────────────
    {
        "title": "HU-019: Registro formal de cierre del proceso por facultad",
        "labels": ["E7: Cierre", "Sprint 5", "historia-de-usuario"],
        "milestone": "Sprint 5",
        "body": hu_body(
            "HU-019", "E7: Cierre y Actas",
            "Como secretario de facultad, necesito registrar el cierre formal del proceso cuando no existan apelaciones pendientes para dar por finalizado el período de evaluación.",
            [
                "El cierre solo puede ejecutarse si no hay apelaciones pendientes.",
                "El sistema registra la fecha y hora del cierre formal.",
                "Todos los actores son notificados del cierre del proceso.",
                "Los expedientes quedan en estado de solo lectura tras el cierre."
            ]
        )
    },
    {
        "title": "HU-020: Generación de acta de cierre",
        "labels": ["E7: Cierre", "Sprint 5", "historia-de-usuario"],
        "milestone": "Sprint 5",
        "body": hu_body(
            "HU-020", "E7: Cierre y Actas",
            "Como secretario de facultad, necesito generar el acta de cierre del proceso para dejar constancia formal del término de la evaluación académica del período.",
            [
                "El sistema genera el acta con todos los académicos y calificaciones.",
                "El acta puede descargarse en formato PDF.",
                "Queda registrada en el sistema con fecha de generación.",
                "El acta es accesible para la CCDA desde su panel."
            ]
        )
    },
    # ── E8: Notificaciones (Sprint 5) ─────────────────────────
    {
        "title": "HU-021: Notificación de inicio del proceso al académico",
        "labels": ["E8: Notificaciones", "Sprint 5", "historia-de-usuario"],
        "milestone": "Sprint 5",
        "body": hu_body(
            "HU-021", "E8: Notificaciones Automáticas",
            "Como académico evaluado, necesito recibir una notificación al inicio del proceso para saber que debo comenzar a subir mis evidencias y conocer el plazo disponible.",
            [
                "Al registrar el período, el sistema envía notificación a todos los académicos.",
                "La notificación incluye fecha de inicio, plazo y enlace al sistema.",
                "La notificación queda registrada en el historial del sistema.",
                "El académico puede acceder al sistema desde el enlace de la notificación."
            ]
        )
    },
    {
        "title": "HU-022: Notificación de vencimiento de plazo",
        "labels": ["E8: Notificaciones", "Sprint 5", "historia-de-usuario"],
        "milestone": "Sprint 5",
        "body": hu_body(
            "HU-022", "E8: Notificaciones Automáticas",
            "Como académico evaluado, necesito recibir una notificación previa al vencimiento del plazo para asegurarme de completar la entrega de mis evidencias a tiempo.",
            [
                "El sistema envía alerta 48 horas antes del vencimiento del plazo.",
                "La notificación indica la fecha y hora exacta del cierre.",
                "Se envía una segunda alerta el día del vencimiento.",
                "El académico con expediente completo no recibe la segunda alerta."
            ]
        )
    },
    {
        "title": "HU-023: Notificación de cambios de estado en el expediente",
        "labels": ["E8: Notificaciones", "Sprint 5", "historia-de-usuario"],
        "milestone": "Sprint 5",
        "body": hu_body(
            "HU-023", "E8: Notificaciones Automáticas",
            "Como académico evaluado, necesito recibir notificaciones ante cambios relevantes en mi expediente para estar informado del avance de mi proceso de evaluación.",
            [
                "El académico recibe notificación al validarse su expediente.",
                "Recibe notificación cuando se emite su informe de calificación.",
                "Recibe notificación al resolverse una apelación.",
                "Todas las notificaciones quedan registradas con fecha y tipo."
            ]
        )
    },
    # ── E9: Reportes (Sprint 5) ───────────────────────────────
    {
        "title": "HU-024: Visualización del estado del proceso por facultad",
        "labels": ["E9: Reportes CCDA", "Sprint 5", "historia-de-usuario"],
        "milestone": "Sprint 5",
        "body": hu_body(
            "HU-024", "E9: Reportes y Acceso CCDA",
            "Como analista CCDA, necesito visualizar el estado del proceso en cada facultad para tener visibilidad institucional en tiempo real sobre el avance de la evaluación académica.",
            [
                "El panel CCDA muestra el estado por facultad y por académico.",
                "Permite filtrar por facultad, estado y período.",
                "La información se actualiza en tiempo real.",
                "Puede acceder al detalle de cada expediente en modo lectura."
            ]
        )
    },
    {
        "title": "HU-025: Generación de reporte consolidado de calificaciones",
        "labels": ["E9: Reportes CCDA", "Sprint 5", "historia-de-usuario"],
        "milestone": "Sprint 5",
        "body": hu_body(
            "HU-025", "E9: Reportes y Acceso CCDA",
            "Como analista CCDA, necesito generar un reporte consolidado de calificaciones por facultad y período para apoyar la toma de decisiones institucionales.",
            [
                "El sistema genera reporte con todas las calificaciones del período.",
                "El reporte puede filtrarse por facultad y exportarse en PDF.",
                "Incluye estadísticas básicas por categoría APA.",
                "Queda registrado en el historial con fecha de generación."
            ]
        )
    },
    {
        "title": "HU-026: Identificación de académicos con incumplimientos",
        "labels": ["E9: Reportes CCDA", "Sprint 5", "historia-de-usuario"],
        "milestone": "Sprint 5",
        "body": hu_body(
            "HU-026", "E9: Reportes y Acceso CCDA",
            "Como analista CCDA, necesito identificar académicos que no entregaron evidencias o no completaron el proceso para gestionar los casos de incumplimiento institucional.",
            [
                "El sistema marca automáticamente expedientes sin evidencias al vencer el plazo.",
                "El panel CCDA lista los académicos con incumplimiento por facultad.",
                "El listado puede exportarse en formato PDF.",
                "Los casos especiales (licencia médica) quedan excluidos del listado."
            ]
        )
    },
]

def create_issues():
    print(f"\n📝 Creando {len(ISSUES)} issues...")
    for i, issue in enumerate(ISSUES, 1):
        data = {
            "title":     issue["title"],
            "body":      issue["body"],
            "labels":    issue["labels"],
            "milestone": milestone_ids.get(issue["milestone"])
        }
        r = req("POST", f"{BASE}/issues", data)
        if r.status_code == 201:
            print(f"  ✓ [{i:02d}/26] {issue['title'][:60]}")
        else:
            print(f"  ✗ [{i:02d}/26] {issue['title'][:60]}")
        time.sleep(0.5)  # Respetar rate limit de GitHub

# ════════════════════════════════════════════════════════════════
# MAIN
# ════════════════════════════════════════════════════════════════
if __name__ == "__main__":
    print("=" * 60)
    print("  Setup GitHub Project — Sistema APA UCM")
    print(f"  Repo: {OWNER}/{REPO}")
    print("=" * 60)

    if GITHUB_TOKEN == "ghp_TU_TOKEN_AQUI":
        print("\n❌ ERROR: Configura tu GITHUB_TOKEN antes de ejecutar.")
        print("   Ve a: GitHub → Settings → Developer settings")
        print("         → Personal access tokens → Tokens (classic)")
        print("   Permisos necesarios: repo (full)")
        exit(1)

    create_labels()
    create_milestones()
    create_issues()

    print("\n" + "=" * 60)
    print("  ✅ Setup completado.")
    print(f"  🔗 https://github.com/{OWNER}/{REPO}/issues")
    print(f"  🔗 https://github.com/{OWNER}/{REPO}/milestones")
    print("=" * 60)
