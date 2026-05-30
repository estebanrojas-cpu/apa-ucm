# Sistema de Gestión de Calificaciones Académicas — UCM
### Plataforma web de soporte al proceso APA · Universidad Católica del Maule

**Estudiante:** Esteban Ignacio Rojas Calderón  
**Carrera:** Ingeniería Civil Informática  
**Asignatura:** INF613 – Módulo Integrador de Formación Profesional  
**Repositorio:** [github.com/Tban1/apa-ucm](https://github.com/Tban1/apa-ucm)

---

## Descripción

Sistema web institucional que provee infraestructura tecnológica para el proceso de **Calificación Académica Docente (APA)** de la UCM. El sistema no modifica el proceso — lo digitaliza: gestión de expedientes, carga de evidencias, evaluación CCA, apelaciones, notificaciones automáticas y generación de documentos PDF.

---

## Stack tecnológico

| Capa | Tecnología |
|------|-----------|
| Frontend | React 18 + Inertia.js (SPA) |
| Backend | Laravel 11 (PHP 8.3) |
| Base de datos | PostgreSQL 16 |
| Caché / Colas | Redis 7 |
| Servidor web | Nginx 1.25 |
| Contenedores | Docker + Docker Compose |
| Estilos | Tailwind CSS |

---

## Requisitos previos

- Docker Desktop (con WSL2 habilitado en Windows) o Docker Engine en Linux/macOS
- Git

---

## Instalación (primera vez)

```bash
# 1. Clonar el repositorio
git clone https://github.com/Tban1/apa-ucm.git
cd apa-ucm

# 2. Dar permisos al script
chmod +x setup.sh

# 3. Ejecutar instalación completa
./setup.sh
```

El script levanta los contenedores, instala dependencias, genera la `APP_KEY` y ejecuta las migraciones. La app queda disponible en **http://localhost:8080**.

### Cargar datos de prueba

```bash
docker compose exec app php artisan db:seed
```

Esto crea las facultades, las categorías APA y los usuarios de prueba (ver tabla de roles más abajo).

---

## Comandos del día a día

```bash
# Levantar el entorno
docker compose up -d

# Detener el entorno
docker compose down

# Ver logs de la app
docker compose logs -f app

# Ejecutar migraciones
docker compose exec app php artisan migrate

# Ejecutar comando Artisan
docker compose exec app php artisan <comando>

# Compilar assets (producción)
docker compose exec app npm run build
```

---

## Servicios Docker

| Servicio | Imagen | Puerto | Descripción |
|----------|--------|--------|-------------|
| `app` | `apa-ucm-app` | — | Laravel PHP-FPM |
| `nginx` | `nginx:1.25-alpine` | `8080` | Servidor web |
| `db` | `postgres:16-alpine` | `5432` | Base de datos |
| `redis` | `redis:7-alpine` | `6379` | Caché y colas |
| `vite` | `apa-ucm-app` | `5173` | Dev server HMR |
| `queue` | `apa-ucm-app` | — | Worker de colas Laravel |

---

## Roles y usuarios de prueba

Todos los usuarios tienen contraseña `password`.

| Email | Rol | Acceso |
|-------|-----|--------|
| `admin@ucm.cl` | Administrador | Panel de administración |
| `analista@ucm.cl` | Analista CCDA | Períodos, nóminas, reportes globales |
| `secretario@ucm.cl` | Secretario | Expedientes de su facultad |
| `cca@ucm.cl` | Miembro CCA | Evaluación de expedientes |
| `jefe@ucm.cl` | Jefe Académico | Informe de jefatura por académico |
| `academico@ucm.cl` | Académico | Carga de evidencias propias |

---

## Módulos implementados (HU-001 a HU-026)

### E1 · Autenticación y roles
- **HU-001** — Inicio de sesión con credenciales institucionales
- **HU-002** — Control de acceso diferenciado por rol (RBAC)
- **HU-003** — Cierre de sesión seguro

### E2 · Calendarización
- **HU-004** — Registro de período académico y cronograma de plazos
- **HU-005** — Carga de nómina de académicos evaluados (CSV/manual)
- **HU-006** — Registro de caso especial por licencia médica (plazo individual)

### E3 · Gestión del secretario
- **HU-007** — Panel centralizado de seguimiento de expedientes
- **HU-008** — Configuración de plazos internos por facultad
- **HU-009** — Validación de documentación del expediente
- **HU-010** — Cierre formal de recepción de evidencias

### E4 · Carga de evidencias
- **HU-011** — Carga de archivos por las 5 categorías APA
- **HU-012** — Bloqueo automático al vencer el plazo
- **HU-013** — Visualización del estado del propio expediente

### E5 · Evaluación CCA
- **HU-014** — Revisión del expediente completo por miembro CCA
- **HU-015** — Determinación de calificación final (Excelente / Muy Bueno / Bueno / Regular / Deficiente)
- **HU-016** — Informe PDF de calificación por académico

### E6 · Apelaciones
- **HU-017** — Presentación de apelación por el académico
- **HU-018** — Resolución de apelación por la CCA

### E7 · Cierre
- **HU-019** — Registro formal de cierre del proceso por facultad
- **HU-020** — Generación de acta de cierre en PDF

### E8 · Notificaciones
- **HU-021** — Notificación de inicio del proceso al académico
- **HU-022** — Notificación de vencimiento de plazo (scheduler diario 08:00)
- **HU-023** — Notificación automática de cambios de estado en el expediente

### E9 · Reportes CCDA
- **HU-024** — Panel de visualización del estado del proceso por facultad
- **HU-025** — Reporte consolidado de calificaciones en PDF
- **HU-026** — Listado de académicos con incumplimientos en PDF

---

## Notificaciones automáticas

El scheduler de Laravel envía notificaciones diarias a las 08:00. Para que funcione en producción se requiere un cron en el servidor:

```bash
* * * * * cd /var/www && php artisan schedule:run >> /dev/null 2>&1
```

En desarrollo, se puede ejecutar manualmente:

```bash
docker compose exec app php artisan apa:notificar-vencimiento
```

---

## Estructura del repositorio

```
apa-ucm/
├── docker/
│   ├── nginx/default.conf
│   └── php/php.ini
├── src/                        ← Proyecto Laravel
│   ├── app/
│   │   ├── Console/Commands/   ← Comandos Artisan (notificaciones)
│   │   ├── Http/Controllers/   ← Controladores por rol
│   │   └── Models/             ← Eloquent ORM
│   ├── database/
│   │   ├── migrations/         ← 20 migraciones
│   │   └── seeders/            ← Facultades, categorías, usuarios prueba
│   ├── resources/
│   │   ├── js/Pages/           ← Componentes React por módulo
│   │   └── views/              ← Blade (PDFs imprimibles)
│   └── routes/
│       ├── web.php             ← Rutas por rol con middleware RBAC
│       └── console.php         ← Scheduler
├── docker-compose.yml
├── Dockerfile
└── setup.sh
```

---

## Base de datos

| Parámetro | Valor (desarrollo) |
|-----------|-------------------|
| Host | `localhost:5432` |
| Base de datos | `apa_ucm` |
| Usuario | `apa_user` |
| Contraseña | `secret` |

Compatible con DBeaver, TablePlus o cualquier cliente PostgreSQL.

---

## Convenciones del proyecto

- **Commits:** `feat(sprintN): descripción en español`
- **Ramas:** `main` (única rama, desarrollo incremental por sprint)
- **Migraciones:** numeradas secuencialmente (`000001` → `000020`)
- **PDFs:** generados como vistas Blade con `window.print()` (sin librerías externas)
