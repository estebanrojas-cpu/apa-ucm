# Guía de Testing Completo — Sistema APA UCM

> **Versión:** 2026-1 | **Última actualización:** Junio 2026

---

## Tabla de Contenidos

1. [Setup Inicial](#1-setup-inicial)
2. [Usuarios de Prueba](#2-usuarios-de-prueba)
3. [Flujo Completo End-to-End](#3-flujo-completo-end-to-end)
   - [Fase 0 — Admin + Analista (configura desde cero)](#fase-0--admin--analista-configura-desde-cero)
   - [Etapa 1 — Carga de evidencias, Jefatura y Validación](#etapa-1--carga-de-evidencias-jefatura-y-validación)
   - [Etapa 2 — Evaluación CCA](#etapa-2--evaluación-cca)
   - [Etapa 3 — Apelaciones, Cierre y Vicerrectora](#etapa-3--apelaciones-cierre-y-vicerrectora)
4. [Reportes CCDA](#4-reportes-ccda)
5. [Casos Borde](#5-casos-borde)
6. [Mensajes Flash a Verificar](#6-mensajes-flash-a-verificar)
7. [Troubleshooting](#7-troubleshooting)
8. [Checklist Final](#8-checklist-final)

---

## 1. Setup Inicial

### Opción A — Reset completo (estado inicial limpio, sin período)

```bash
# Levantar contenedores
docker compose up -d

# Solo estructura base: facultades + categorías APA + usuarios de prueba
docker compose exec app php artisan migrate:fresh
docker compose exec app php artisan db:seed --class=FacultadesSeeder
docker compose exec app php artisan db:seed --class=CategoriasApaSeeder
docker compose exec app php artisan db:seed --class=UsuariosPruebaSeeder
```

Usar cuando quieras probar la Fase 0 completa (el analista crea el período desde cero).

### Opción B — Reset con datos demo (período + nóminas precargadas)

```bash
docker compose exec app php artisan migrate:fresh --seed
```

Crea: período activo 2026-1, cronograma 8 etapas, 6 académicos FCI + 2 FCAF,
compromisos APA confirmados para `academico@ucm.cl`, evidencias demo.

### Verificar que todo está OK

```bash
docker compose exec app php artisan migrate:status
docker compose exec app php artisan tinker --execute="echo App\Models\User::count();"
```

- **URL:** http://localhost:8080
- **Password universal:** `password`

---

## 2. Usuarios de Prueba

| Email | Rol | Facultad | Notas |
|---|---|---|---|
| `admin@ucm.cl` | Admin | — | Configura semestres S1/S2 |
| `analista@ucm.cl` | Analista CCDA | — | Crea períodos, carga nóminas, reportes |
| `secretario@ucm.cl` | Secretario | FCI | Solo ve expedientes FCI |
| `cca@ucm.cl` | Miembro CCA | FCI | Evalúa académicos FCI |
| `jefe@ucm.cl` | Jefe Académico | FCI | Emite informes FCI |
| `vicerrectora@ucm.cl` | Vicerrectora | — | Solo lectura global |
| `academico@ucm.cl` | Académico | FCI (adjunto) | S1 y S2 declarados en demo |
| `secretario.fcaf@ucm.cl` | Secretario | FCAF | Solo ve expedientes FCAF |
| `cca.fcaf@ucm.cl` | Miembro CCA | FCAF | Evalúa académicos FCAF |
| `jefe.fcaf@ucm.cl` | Jefe Académico | FCAF | Informes FCAF |
| `academico.fcaf@ucm.cl` | Académico | FCAF (titular) | Solo S1 declarado en demo |

---

## 3. Flujo Completo End-to-End

---

### Fase 0 — Admin + Analista (configura desde cero)

> Usar con **Opción A** del setup. Si usaste Opción B, el período ya existe — saltar a Etapa 1.

#### T-00: Admin configura semestres académicos

- Login: `admin@ucm.cl`
- URL: `/admin/configuracion-semestres`
- **Pasos:** Fijar fecha de cierre S1 y S2 del año → guardar
- **Verificar:**
  - [ ] Se muestran 2 fechas configurables (S1 y S2)
  - [ ] Fecha S1 < Fecha S2
  - [ ] Mensaje de éxito al guardar

---

#### T-01: Analista crea período y cronograma de 8 etapas

- Login: `analista@ucm.cl`
- URL: `/analista/periodos/crear`
- **Pasos:**
  1. Completar nombre (`2026-1 - Calificación APA 2025`), año, fecha inicio y cierre
  2. Definir fechas para las **8 etapas** del cronograma:

     | Etapa | Tipo | Descripción |
     |---|---|---|
     | `carga_evidencias` | Paralela | Académico declara APA y sube evidencias |
     | `validacion_secretario` | Paralela | Secretario valida a medida que llegan |
     | `informe_jefatura` | Paralela | Jefe emite informe |
     | `evaluacion_cca` | Secuencial | Inicia al cerrar las 3 anteriores |
     | `comunicacion_resultados` | Secuencial | — |
     | `apelaciones` | Secuencial | — |
     | `registro_ccda` | Secuencial | — |
     | `revision_vicerrectoria` | Secuencial | Al cerrar → período pasa a "cerrado" |

  3. Guardar
- **Verificar:**
  - [ ] Período creado con estado `activo` en `/analista/periodos`
  - [ ] Cronograma con las 8 etapas visible
  - [ ] PDF del cronograma descargable (`/analista/periodos/{id}/cronograma/pdf`)

---

#### T-02: Analista descarga plantilla de nómina

- URL: `/analista/nominas/plantilla`
- **Verificar:**
  - [ ] Descarga `plantilla_nomina_ucm.xlsx` con columnas SAPD vacías (RUT, nombre, apellidos, facultad, categoría, horas contrato I/II sem, etc.)

---

#### T-03: Analista carga nómina vía Excel SAPD

- URL: `/analista/periodos/{periodo}/nominas/crear`
- **Pasos:**
  1. Panel izquierdo **"Importar Excel SAPD"** → seleccionar archivo `.xlsx`
  2. Click **"Cargar"** → revisar vista previa (primeras 4 filas)
  3. Verificar auto-detección de columnas SAPD
  4. Ajustar manualmente los selects si algún campo no se detectó
  5. Click **"Importar nómina"**
- **Verificar:**
  - [ ] Tabla preview aparece con primeras filas
  - [ ] Mensaje en verde con campos auto-detectados (ícono ✓)
  - [ ] Aviso amarillo si hay columnas no reconocidas
  - [ ] Botón "Importar nómina" deshabilitado hasta que RUT y Nombre estén asignados
  - [ ] Mensaje `"X académico(s) importado(s)."` al confirmar
  - [ ] Si RUT duplicado: `"X ya estaban en la nómina (datos actualizados)."`
  - [ ] Si filas con RUT o nombre vacío: `"X fila(s) con errores omitidas."`

**Probar idempotencia:**
  - [ ] Importar el mismo archivo dos veces → `"0 importado(s). X ya estaban en la nómina (datos actualizados)."`

**Probar columnas mínimas:**
  - [ ] Subir Excel con solo RUT y Nombre → importa correctamente, campos SAPD quedan `—`

---

#### T-04: Analista agrega académico individual

- URL: `/analista/periodos/{periodo}/nominas/crear`
- **Pasos:** Click **"+ Agregar académico"** → completar modal:
  - RUT: `11.111.111-1`, Nombre: `Prueba Test`, Categoría: `adjunto`, Horas: `18`
- **Verificar:**
  - [ ] Modal abre y cierra correctamente
  - [ ] Académico aparece en tabla con estado `pendiente`
  - [ ] Intentar agregar mismo RUT → `"Ya está en la nómina de este período."`

---

#### T-05: Analista exporta nómina

- URL: `/analista/periodos/{periodo}/nominas/exportar`
- **Verificar:**
  - [ ] Descarga Excel con todos los académicos del período
  - [ ] Exportar `?solo_excelentes=1` → solo académicos con concepto `Excelente`

---

#### T-06: Analista ve detalle de académico (historial y vigencia)

- URL: `/analista/periodos/{periodo}/nominas/{nomina}/detalle`
- **Verificar:**
  - [ ] Datos SAPD completos (N° Personal, RUT, Nombre, Adscripción, Unidad, Posición, Horas, Categoría)
  - [ ] Sección "Nota vigente": nota, estado (Vigente/Vencida), fecha de vencimiento
  - [ ] Vigencia: `auxiliar` = 1 año; `adjunto/titular` = 2 años
  - [ ] Historial de calificaciones ordenado por año desc
  - [ ] Historial de categorías

---

### Etapa 1 — Carga de evidencias, Jefatura y Validación

> Activar con el seeder de etapa:

```bash
docker compose exec app php artisan db:seed --class=FlujoEtapa1CargaSeeder
```

Abre `carga_evidencias`, `validacion_secretario`, `informe_jefatura` (hasta D+20). Reseteará el cronograma a fechas activas desde hoy.

---

#### T-07: Secretario configura plazo de su facultad

- Login: `secretario@ucm.cl`
- URL: `/secretario/dashboard` → sección plazos
- **Pasos:** Definir fecha límite de carga para la facultad FCI
- **Verificar:**
  - [ ] Fecha límite guardada
  - [ ] Académicos de FCI pueden subir evidencias hasta esa fecha

---

#### T-08: Académico declara APA semestral

- Login: `academico@ucm.cl`
- URL: `/academico/declaracion-apa` (redirige a S1 si no ha declarado)
- **Pasos para S1:**
  1. Completar horas por dimensión (Docencia, Investigación, Extensión, Administración, Otras)
  2. Verificar que el porcentaje calculado sume 100%
  3. Click **"Confirmar S1"**
- **Pasos para S2:** Repetir en pestaña S2
- **Verificar:**
  - [ ] Contador de porcentaje en tiempo real
  - [ ] Botón "Confirmar" deshabilitado si no suma 100%
  - [ ] Al confirmar ambos semestres → redirige a dashboard académico
  - [ ] Si vuelve a `/academico/declaracion-apa` → ya no redirige a declaración

**Probar con `academico.fcaf@ucm.cl`:**
  - [ ] Solo S1 declarado → redirige automáticamente a declarar S2
  - [ ] Declarar S2 → accede a carga de evidencias

---

#### T-09: Académico sube evidencias

- URL: `/academico/evidencias`
- **Verificar (plazo abierto):**
  - [ ] Puede subir archivos (PDF, Word, imágenes)
  - [ ] Archivo aparece listado con nombre, categoría y fecha/hora
  - [ ] Puede eliminar sus propias evidencias
  - [ ] Puede descargar sus propias evidencias

---

#### T-10: Jefe académico emite informe de jefatura

- Login: `jefe@ucm.cl`
- URL: `/jefe/academicos`
- **Pasos:** Ver lista → abrir académico asignado → completar informe → guardar → click "Imprimir"
- **Verificar:**
  - [ ] Lista muestra solo académicos asignados a su jefatura
  - [ ] Puede guardar informe con comentarios
  - [ ] PDF de informe generado con datos del académico
  - [ ] Estado "Informe emitido" visible en la lista

---

#### T-11: Secretario gestiona solicitud de exclusión

- Login: `secretario@ucm.cl`
- URL: `/secretario/solicitudes`
- **Pasos:** Click **"+ Nueva solicitud"** → seleccionar tipo → completar datos → adjuntar documento
- **Tipos disponibles:** `licencia_medica`, `perfeccionamiento`, `cargo_administrativo`, `otro`
- **Verificar:**
  - [ ] Solicitud creada con estado `activa`
  - [ ] Académico bloqueado: login como ese académico → redirige a `/academico/bloqueado`
  - [ ] Click "Reincorporar" → académico recupera acceso
  - [ ] Puede descargar documento adjunto

---

#### T-12: Secretario valida expediente

- URL: `/secretario/expedientes`
- **Pasos:** Abrir expediente de `academico@ucm.cl` → revisar evidencias → click **"Validar"**
- **Verificar:**
  - [ ] Lista muestra solo académicos de FCI (no FCAF)
  - [ ] Puede descargar evidencias del académico
  - [ ] Ve compromisos APA (S1 y S2)
  - [ ] Estado cambia a `validado` al validar
  - [ ] Click **"Reabrir"** → vuelve a estado anterior

---

#### T-13: Secretario cierra recepción de su facultad

- URL: `/secretario/expedientes`
- **Pasos:** Click **"Cerrar recepción"**
- **Verificar:**
  - [ ] Plazo de la facultad queda con `cerrado_en` = fecha actual
  - [ ] Académicos ya no pueden subir más evidencias

---

### Etapa 2 — Evaluación CCA

> Activar con el seeder de etapa (cierra carga, abre evaluación_cca):

```bash
docker compose exec app php artisan db:seed --class=FlujoEtapa2CcaSeeder
```

Cierra `carga_evidencias`, `validacion_secretario`, `informe_jefatura`. Abre `evaluacion_cca` (hasta D+20). Marca expedientes con evidencias + compromiso como `carga_cerrada`.

---

#### T-14: CCA revisa expediente

- Login: `cca@ucm.cl`
- URL: `/cca/expedientes`
- **Pasos:** Ver lista → abrir expediente de `academico@ucm.cl`
- **Verificar:**
  - [ ] Lista muestra solo expedientes `carga_cerrada` de FCI
  - [ ] Vista del expediente: declaración APA (S1/S2 con pesos), evidencias descargables, informe de jefatura, historial académico

---

#### T-15: CCA evalúa por dimensión

- URL: `/cca/expedientes/{nomina}`
- **Pasos:** Ingresar nota numérica (1.0–5.0, step 0.1) en cada dimensión → guardar
- **Verificar:**
  - [ ] Solo acepta valores entre 1.0 y 5.0 con step 0.1
  - [ ] Evaluación guardada; puede volver a editar antes de finalizar
  - [ ] Un miembro CCA no puede ver/editar la evaluación de otro

---

#### T-16: CCA finaliza calificación con actividades extra

- **Pasos:**
  1. Agregar valor en **"Otras actividades"** (fuera del 100% APA)
  2. Agregar observación (máx 600 chars) → contador visible `{n}/600`
  3. Click **"Finalizar"**
- **Verificar:**
  - [ ] Nota final calculada: `min(suma_ponderada + extra, 5.0)`
  - [ ] Concepto asignado automáticamente (Excelente/Muy Bueno/Bueno/Regular/Deficiente)
  - [ ] Si observación supera 600 chars → campo bloqueado o error de validación
  - [ ] Expediente pasa a estado `evaluado`

---

#### T-17: CCA genera PDF de calificación

- URL: `/cca/expedientes/{nomina}/calificacion-pdf`
- **Verificar:**
  - [ ] PDF se abre con 5 cajas de firma (3 miembros CCA con nombres pre-impresos + académico + secretario)

---

### Etapa 3 — Apelaciones, Cierre y Vicerrectora

> Activar con el seeder de etapa (cierra CCA, abre etapas finales):

```bash
docker compose exec app php artisan db:seed --class=FlujoEtapa3CierreSeeder
```

Cierra `evaluacion_cca`. Abre `comunicacion_resultados`, `apelaciones`, `registro_ccda`, `revision_vicerrectoria`. Auto-completa expedientes sin calificación final.

---

#### T-18: Académico ve su calificación y apela

- Login: `academico@ucm.cl`
- URL: `/academico/dashboard`
- **Pasos:** Ver nota/concepto publicado → click **"Apelar"** → completar motivo → adjuntar evidencia de apelación → enviar
- **Verificar:**
  - [ ] Nota y concepto visibles en dashboard
  - [ ] Apelación enviada → estado `en_apelacion`
  - [ ] Puede subir evidencias de apelación (lista separada)
  - [ ] Puede eliminar evidencias de apelación antes de que la resuelvan

---

#### T-19: Secretario gestiona apelación (nivel CCA)

- Login: `secretario@ucm.cl`
- URL: `/secretario/expedientes/{nomina}`
- **Para concepto Excelente/Muy Bueno/Bueno:**
  - **Pasos:** Resolver apelación → registrar resultado → cerrar
  - **Verificar:** [ ] Apelación resuelta; estado actualizado
- **Para concepto Regular/Deficiente:**
  - **Verificar:** [ ] Apelación derivada automáticamente a CCDA (`destino=ccda`)

---

#### T-20: Analista CCDA resuelve apelación de 2do nivel

- Login: `analista@ucm.cl`
- URL: `/analista/apelaciones`
- **Pasos:** Ver apelaciones derivadas → abrir una → revisar evidencias → evaluar → finalizar
- **Verificar:**
  - [ ] Solo aparecen apelaciones con `destino=ccda`
  - [ ] Puede descargar evidencias de la apelación
  - [ ] Al finalizar → resultado registrado y visible para académico

---

#### T-21: Analista CCDA hace Registro CCDA

- URL: `/analista/registro-ccda`
- **Pasos:** Ver tabla por facultad → verificar automáticamente los checks → guardar verificación
- **Checks automáticos por académico:**
  - ¿Tiene nota/concepto final?
  - ¿Apelación resuelta (si la hubo)?
  - ¿Retroalimentación registrada?
- **Verificar:**
  - [ ] Tabla muestra todas las facultades con conteos
  - [ ] Verificación guardada por académico y por facultad

---

#### T-22: Secretario cierra el proceso

- URL: `/secretario/expedientes`
- **Pasos:** Click **"Cerrar proceso"** → confirmar
- **Verificar:**
  - [ ] Si hay apelaciones pendientes → no puede cerrar (error)
  - [ ] Al cerrar → acta de cierre generada
  - [ ] URL `/secretario/acta-cierre/{id}` → PDF descargable del acta

---

#### T-23: Vicerrectora revisa y comenta

- Login: `vicerrectora@ucm.cl`
- URL: `/vicerrectora/academicos`
- **Pasos:**
  1. Filtrar por facultad (selector)
  2. Filtrar por concepto (Excelente, Muy Bueno, etc.)
  3. Buscar por nombre o RUT
  4. Abrir expediente → `/vicerrectora/academicos/{nomina}`
  5. Dejar comentario en una evaluación
- **Verificar:**
  - [ ] Ve académicos de todas las facultades (vista global)
  - [ ] Filtros funcionan en tiempo real sin recarga
  - [ ] Expediente: datos académico, calificación final (nota+concepto+observación), retroalimentación CCA por evaluador, listado de evidencias
  - [ ] Solo lectura: no puede editar nada del expediente
  - [ ] Comentario guardado y visible en la lista con texto truncado
  - [ ] Volver a abrir el mismo comentario → texto previo cargado

---

## 4. Reportes CCDA

> Accesibles en cualquier etapa del proceso con `analista@ucm.cl`.

#### T-24: Estado proceso por facultad

- URL: `/analista/estado-proceso`
- **Verificar:**
  - [ ] Tabla con conteos por facultad (pendientes, en carga, evaluados, cerrados)
  - [ ] Muestra etapa actual del cronograma

---

#### T-25: Reporte de calificaciones

- URL: `/analista/reporte-calificaciones`
- **Verificar:**
  - [ ] Reporte agrupado por facultad con nota/concepto por académico

---

#### T-26: Reporte de incumplimientos

- URL: `/analista/incumplimientos`
- **Verificar:**
  - [ ] Lista de académicos sin evidencias, sin declaración APA o fuera de plazo

---

#### T-27: Ver solicitudes de exclusión (como analista)

- URL: `/analista/solicitudes`
- **Verificar:**
  - [ ] Lista con todas las solicitudes de todas las facultades
  - [ ] Puede descargar documento respaldo de cada solicitud

---

## 5. Casos Borde

| # | Caso | Cómo probarlo | Resultado esperado |
|---|---|---|---|
| E-01 | Académico con licencia médica | Login `maria.soto@ucm.cl` / `password` | Redirige a `/academico/bloqueado` |
| E-02 | Límite 600 chars en observación CCA | En T-16, pegar 601 caracteres | Campo bloqueado o validación 422 |
| E-03 | Nota fuera de rango (0.9 o 5.1) | En T-15, escribir valor inválido | `type=number min=1 max=5 step=0.1` bloquea en HTML5 |
| E-04 | Cap nota final ≤ 5.0 con extra | En T-16, nota base 4.8 + extra 0.3 | Nota final = 5.0 (no 5.1) |
| E-05 | Cerrar proceso con apelación pendiente | En T-22, no resolver apelación antes | Error bloqueante: "Hay apelaciones pendientes" |
| E-06 | RBAC — acceder a URL de otro rol | Login como académico → ir a `/cca/expedientes` | 403 o redirección |
| E-07 | Login con credenciales incorrectas | Password erróneo | Error visible en pantalla |
| E-08 | Secretario ve solo su facultad | Login `secretario@ucm.cl` → expedientes | Solo expedientes FCI; FCAF no aparece |
| E-09 | Multifacultad paralela | Flujo completo con usuarios FCAF | Ambas facultades operan independientemente |
| E-10 | Excel con fila vacía al final | Importar con fila vacía | Se omite silenciosamente |
| E-11 | Excel con RUT sin formato | RUT como `12345678` sin puntos/guión | Importa o muestra error claro |
| E-12 | Declaración APA que no suma 100% | En T-08, ingresar 50+30+10+5 | Botón "Confirmar" deshabilitado |
| E-13 | Reimportar Excel con académico con licencia | Mismo Excel, académico tiene `con_licencia=true` | Datos SAPD actualizados; flag licencia intacto |

---

## 6. Mensajes Flash a Verificar

| Acción | Mensaje esperado |
|---|---|
| Importación exitosa | `"X académico(s) importado(s)."` |
| Importación con duplicados | `"... X ya estaban en la nómina (datos actualizados)."` |
| Todos duplicados | `"0 importado(s). X ya estaban en la nómina (datos actualizados)."` |
| Agregar individual exitoso | `"Nombre Apellido agregado a la nómina."` |
| Agregar individual duplicado | `"Nombre Apellido ya está en la nómina de este período."` |
| Expediente validado | Estado cambia visualmente a `validado` |
| Calificación finalizada | Nota + concepto visible en expediente |
| Apelación enviada | Estado `en_apelacion` |
| Proceso cerrado | Botón para descargar acta de cierre |

---

## 7. Troubleshooting

### Error al hacer login
```bash
docker compose exec app php artisan cache:clear
docker compose exec app php artisan config:clear
docker compose exec app php artisan view:clear
```

### Cambios en código no se reflejan
```bash
# Cambios React (.jsx)
docker compose restart vite

# Cambios PHP
docker compose restart app
```

### Volver a un estado específico del flujo

```bash
# Reiniciar todo (período + nóminas + usuarios)
docker compose exec app php artisan migrate:fresh --seed

# Solo regresar a Etapa 1 (sin borrar BD)
docker compose exec app php artisan db:seed --class=FlujoEtapa1CargaSeeder

# Avanzar a Etapa 2 (cierra carga, abre CCA)
docker compose exec app php artisan db:seed --class=FlujoEtapa2CcaSeeder

# Avanzar a Etapa 3 (cierra CCA, abre apelaciones y vicerrectoría)
docker compose exec app php artisan db:seed --class=FlujoEtapa3CierreSeeder
```

### Forzar académico sin declaración APA (para probar flujo desde S1)
```bash
docker compose exec app php artisan tinker
> $u = App\Models\User::where('email','academico@ucm.cl')->first();
> $n = App\Models\Nomina::where('user_id', $u->id)->first();
> App\Models\CompromisoApa::where('nomina_id', $n->id)->delete();
> exit
```

### Forzar cierre de S1 (para desbloquear S2)
```bash
docker compose exec app php artisan tinker
> App\Models\SemestreAcademico::where('numero', 1)->update(['fecha_cierre' => now()->subDays(5)]);
> exit
```

### Importación CSV no detecta columnas
- Verificar que el CSV tenga encabezados en la primera fila
- Usar `"Categoría 2026"` (con año) para la categoría actual
- Auxiliar: fecha categorización 2025 → vence 2026; Adjunto/Titular: 2024 → vence 2026

---

## 8. Checklist Final

### Admin
- [ ] T-00: Configurar fechas semestres S1 y S2

### Analista CCDA — Configuración inicial
- [ ] T-01: Crear período con cronograma 8 etapas
- [ ] T-02: Descargar plantilla nómina SAPD
- [ ] T-03: Importar nómina Excel SAPD (auto-detección + idempotencia)
- [ ] T-04: Agregar académico individual (+ rechazo duplicado)
- [ ] T-05: Exportar nómina completa y solo Excelentes
- [ ] T-06: Ver detalle académico (historial + vigencia nota)

### Etapa 1 — Carga, Jefatura y Validación
- [ ] T-07: Secretario configura plazo de facultad
- [ ] T-08: Académico FCI declara APA (S1 + S2 suman 100%)
- [ ] T-08b: Académico FCAF declara S2 pendiente
- [ ] T-09: Académico sube y gestiona evidencias
- [ ] T-10: Jefe emite informe de jefatura + PDF
- [ ] T-11: Secretario crea solicitud de exclusión (licencia médica)
- [ ] T-12: Secretario valida expediente + puede reabrir
- [ ] T-13: Secretario cierra recepción de su facultad

### Etapa 2 — Evaluación CCA
- [ ] T-14: CCA revisa expediente completo (APA + evidencias + jefatura)
- [ ] T-15: CCA evalúa por dimensión (notas 1.0–5.0, step 0.1)
- [ ] T-16: CCA finaliza con actividades extra (cap ≤ 5.0) + observación 600 chars
- [ ] T-17: CCA genera PDF de calificación con 5 firmas

### Etapa 3 — Apelaciones, Cierre y Vicerrectora
- [ ] T-18: Académico ve calificación y apela con evidencia
- [ ] T-19: Secretario resuelve apelación CCA / deriva a CCDA
- [ ] T-20: Analista CCDA resuelve apelación 2do nivel
- [ ] T-21: Analista CCDA hace Registro CCDA (verificación por facultad)
- [ ] T-22: Secretario cierra proceso → acta de cierre PDF
- [ ] T-23: Vicerrectora revisa (filtros + búsqueda + comentario)

### Reportes CCDA
- [ ] T-24: Estado proceso por facultad
- [ ] T-25: Reporte de calificaciones
- [ ] T-26: Reporte de incumplimientos
- [ ] T-27: Solicitudes de exclusión como analista

### Casos borde
- [ ] E-01: Académico con licencia → bloqueado
- [ ] E-02: Observación CCA > 600 chars → bloqueado
- [ ] E-04: Cap nota 5.0 con actividades extra
- [ ] E-05: Cerrar proceso con apelación pendiente → error
- [ ] E-06: RBAC — URL de otro rol → 403
- [ ] E-09: Multifacultad FCI/FCAF independientes
- [ ] E-12: Declaración APA sin suma 100% → botón deshabilitado

---

**URL local:** http://localhost:8080 | **Password universal:** `password`
