# Contexto sesión — continuación prueba funcional APA UCM
> Generado automáticamente el 2026-06-30. Pull este archivo antes de continuar.

---

## Estado actual del sistema (NO resetear)

La BD tiene datos reales de la prueba funcional. **No ejecutar `reset_test.sh`**.

### Cargos configurados y confirmados

**FCI — Ciencias de la Ingeniería** (comisión confirmada ✅)
| Cargo | Persona | Password |
|---|---|---|
| Secretario + Dir. Escuela | Carlos Eduardo Fuentes Pinto (20.222.333-4) | `20222333` |
| Decano | María Elena Rodríguez Mora (20.111.222-3) | `20111222` |
| Dir. Departamento | Claudia Fernanda Vega Soto (21.000.111-2) | `21000111` |
| CCA 1 (externo FCAF) | Patricia Carmen Lagos Ríos (22.333.444-5) | `22333444` |
| CCA 2 (externo FCAF) | Rosa Edith Morales Vega (22.111.222-3) | `22111222` |
| CCA Sindicato | Roberto Ignacio Jiménez Díaz (20.555.666-7) | `20555666` |

**FCAF — Ciencias Agropecuarias y Forestales** (comisión confirmada ✅)
| Cargo | Persona | Password |
|---|---|---|
| Secretario + Dir. Escuela | Jorge Andrés Silva Mora (22.222.333-4) | `22222333` |
| Decano | Paula Andrea Morales Vega (22.555.666-7) | `22555666` |
| Dir. Departamento | Rosa Edith Morales Vega (22.111.222-3) | `22111222` |
| CCA 1 (externo FCI) | Diego Mauricio Espinoza Araya (21.111.222-3) | `21111222` |
| CCA 2 (externo FCI) | María Elena Rodríguez Mora (20.111.222-3) | `20111222` |
| CCA Sindicato | Patricia Carmen Lagos Ríos (22.333.444-5) | `22333444` |

**Institucionales**
| Rol | Email | Password |
|---|---|---|
| Analista CCDA | analista@ucm.cl | `password` |
| Vicerrectora | vicerrectora@ucm.cl | `password` |

---

## Estado del flujo de prueba

| # | Paso | Estado |
|---|---|---|
| 1 | APA S1 declarado (Carlos + Roberto) | ✅ hecho |
| 2 | **Desbloquear S2** (tinker) | ⬅ **PRIMER PASO** |
| 3 | Declarar S2 (Carlos + Roberto) | pendiente |
| 4 | Informe Decano: María Elena → Carlos | pendiente |
| 5 | Informe Dir. Departamento: Claudia → académicos | pendiente |
| 6 | Secretario (Carlos) valida expedientes | pendiente |
| 7 | CCA evalúa (Patricia + Rosa) | pendiente |
| 8 | Analista CCDA registra | pendiente |
| 9 | Vicerrectora revisa | pendiente |

---

## PRIMER COMANDO al retomar

Desbloquear S2 (la fecha de cierre S1 está en el futuro, hay que moverla al pasado):

```bash
docker compose exec -T app php artisan tinker --execute="App\Models\SemestreAcademico::whereHas('periodo', fn(\$q) => \$q->where('estado','activo'))->where('numero',1)->update(['fecha_cierre' => now()->subDay()->toDateString()]); echo 'OK';"
```

> Si no usas Docker, en WSL dentro de src/:
> ```bash
> php artisan tinker --execute="App\Models\SemestreAcademico::whereHas('periodo', fn(\$q) => \$q->where('estado','activo'))->where('numero',1)->update(['fecha_cierre' => now()->subDay()->toDateString()]); echo 'OK';"
> ```

---

## Académicos evaluables en FCI

| Persona | Situación |
|---|---|
| Carlos Fuentes | Evaluable (sin nota previa). Evaluado por Decano (secretario/dir. escuela) |
| Roberto Jiménez | Evaluable (auxiliar, sin nota reciente). Evaluado por CCA |
| Diego Espinoza | **NO evaluable** — nota 2024 vigente hasta dic 2026 |
| Gabriel Morales | **NO evaluable** — nota 2024 vigente hasta dic 2026 |
| Claudia Vega | **Solo da conocer** — Director Departamento |
| María Elena Rodríguez | **Solo da conocer** — Decano |

---

## Lo que se implementó en esta sesión

- **CCA externos**: CCA 1 y 2 son de otra facultad. `facultadDondeActuaComoCca()` determina qué expedientes ven.
- **Sindicato solo lectura**: puede ver expedientes pero `store()` y `finalize()` retornan 403.
- **Director Departamento**: cargo implementado en enum, `esSoloDaConocer()`, vistas de informe.
- **Informe jefatura simplificado**: un textarea → PDF institucional UCM con dos firmas (jefe + académico). Sin puntaje ni categorías.
- **CCA ve informe de jefatura**: sección "Informe de Jefatura" en azul en `EvaluarExpediente.jsx`.
- **Selección cargos**: CCA 1 y 2 muestran solo personas de otras facultades; resto solo de la misma facultad.

---

## Rutas clave para la prueba

| Rol | Ruta |
|---|---|
| Decano | `/decano/directivos` |
| Director Departamento | `/jefe/academicos` |
| Secretario | `/secretario/expedientes` |
| CCA | `/cca/expedientes` |
| Académico | `/academico/dashboard` o `/` |
| Analista | `/analista/periodos` |
